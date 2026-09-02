<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\StoreProductCollectionLineRequest;
use App\Http\Requests\StoreProductCollectionRequest;
use App\Http\Requests\StoreProductRequest;
use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductAttribute;
use App\Modules\Products\Models\ProductAttributeValue;
use App\Modules\Products\Models\ProductCategory;
use App\Modules\Products\Models\ProductCollection;
use App\Modules\Products\Models\ProductCollectionLine;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Models\ProductSetting;
use App\Modules\Products\Models\ProductType;
use App\Modules\Products\Models\ProductVariant;
use App\Tenancy\TenantAccountAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductsController extends Controller
{
    public function index(Request $request): View
    {
        $settings = $this->settingsRecord();
        $attributes = $this->enabledAttributes();
        $query = Product::query()
            ->with([
                'category:id,name',
                'line:id,name',
                'catalogImage' => fn ($image) => $image->select([
                    'product_images.id',
                    'product_images.product_id',
                    'product_images.path',
                    'product_images.alt_text',
                    'product_images.is_primary',
                    'product_images.sort_order',
                ]),
                'variants' => fn ($variants) => $variants
                    ->select([
                        'id',
                        'product_id',
                        'sku',
                        'pvp1_with_tax',
                    ])
                    ->orderBy('sku'),
                'variants.attributeValues' => fn ($values) => $values
                    ->select([
                        'product_attribute_values.id',
                        'product_attribute_values.product_attribute_id',
                        'product_attribute_values.value',
                    ])
                    ->whereHas(
                        'attribute',
                        fn (Builder $attribute) => $attribute->where('code', 'size'),
                    ),
                'variants.attributeValues.attribute:id,code',
                'variants.inventoryStocks:id,warehouse_id,product_variant_id,quantity',
                'variants.inventoryStocks.warehouse:id,branch_id,name',
                'variants.inventoryStocks.warehouse.branch:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('catalog_code', 'like', "%{$search}%")
                    ->orWhereHas(
                        'variants',
                        fn (Builder $variant) => $variant->where('sku', 'like', "%{$search}%"),
                    );
            });
        }

        foreach (['category_id', 'product_collection_id', 'product_collection_line_id', 'product_type_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->integer($field));
            }
        }

        if ($request->filled('parent_category_id')) {
            $query->whereHas(
                'category',
                fn (Builder $category) => $category->where(
                    'parent_id',
                    $request->integer('parent_category_id'),
                ),
            );
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        foreach ($attributes as $attribute) {
            $field = 'attribute_' . $attribute->id;
            if ($request->filled($field)) {
                $query->whereHas(
                    'variants.attributeValues',
                    fn (Builder $value) => $value->whereKey($request->integer($field)),
                );
            }
        }

        $products = $query->latest()->paginate(15)->withQueryString();
        $inventoryBranches = Branch::query()
            ->select(['id', 'name'])
            ->where('status', 'active')
            ->with([
                'warehouses' => fn ($warehouses) => $warehouses
                    ->select(['id', 'branch_id', 'name'])
                    ->where('is_active', true)
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $products->getCollection()->each(
            fn (Product $product) => $this->prepareCatalogProduct(
                $product,
                $inventoryBranches,
            )
        );

        return view('tenant.products.index', [
            'products' => $products,
            'settings' => $settings,
            'attributes' => $attributes,
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
            'collections' => ProductCollection::where('is_active', true)->orderBy('name')->get(),
            'lines' => ProductCollectionLine::where('is_active', true)
                ->with('collection:id,name')
                ->orderBy('name')
                ->get(),
            'productTypes' => ProductType::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'summary' => [
                'total' => Product::count(),
                'active' => Product::where('is_active', true)->count(),
                'inactive' => Product::where('is_active', false)->count(),
                'variants' => ProductVariant::count(),
            ],
        ]);
    }

    private function prepareCatalogProduct(
        Product $product,
        Collection $inventoryBranches,
    ): void {
        $variantRows = $product->variants
            ->map(function (ProductVariant $variant): array {
                $size = $variant->attributeValues
                    ->first(
                        fn (ProductAttributeValue $value): bool => $value->attribute?->code === 'size'
                    )?->value;

                return [
                    'sku' => $variant->sku,
                    'size' => $size,
                ];
            })
            ->values();
        $prices = $product->variants
            ->pluck('pvp1_with_tax')
            ->filter(fn ($price): bool => $price !== null)
            ->map(fn ($price): float => round((float) $price, 2))
            ->unique()
            ->sort()
            ->values();
        $stockByWarehouse = $product->variants
            ->flatMap(
                fn (ProductVariant $variant): Collection => $variant->inventoryStocks
            )
            ->groupBy('warehouse_id')
            ->map(
                fn (Collection $stocks): int => $stocks->sum(
                    fn ($stock): int => (int) round((float) $stock->quantity * 1000)
                )
            );
        $stockRows = $inventoryBranches
            ->filter(fn (Branch $branch): bool => $branch->warehouses->isNotEmpty())
            ->map(function (Branch $branch) use ($stockByWarehouse): array {
                $warehouses = $branch->warehouses
                    ->map(function ($warehouse) use ($stockByWarehouse): array {
                        $quantity = (int) ($stockByWarehouse[$warehouse->id] ?? 0);

                        return [
                            'name' => $warehouse->name,
                            'quantity' => $this->formatInventoryQuantity($quantity),
                            'quantity_millis' => $quantity,
                        ];
                    })
                    ->values();
                $branchTotal = (int) $warehouses->sum('quantity_millis');

                return [
                    'name' => $branch->name,
                    'quantity' => $this->formatInventoryQuantity($branchTotal),
                    'quantity_millis' => $branchTotal,
                    'warehouses' => $warehouses,
                ];
            })
            ->values();
        $stockTotal = (int) $stockRows->sum('quantity_millis');

        $product->setAttribute('catalog_variant_rows', $variantRows->all());
        $product->setAttribute(
            'catalog_price_display',
            $this->formatCatalogPrice($prices),
        );
        $product->setAttribute('catalog_stock_rows', $stockRows->all());
        $product->setAttribute(
            'catalog_stock_total',
            $this->formatInventoryQuantity($stockTotal),
        );
        $product->setAttribute(
            'catalog_has_stock_records',
            $product->variants->contains(
                fn (ProductVariant $variant): bool => $variant->inventoryStocks->isNotEmpty()
            ),
        );
    }

    private function formatCatalogPrice(Collection $prices): string
    {
        if ($prices->isEmpty()) {
            return '—';
        }

        $minimum = (float) $prices->first();
        $maximum = (float) $prices->last();

        if ($minimum === $maximum) {
            return '$' . number_format($minimum, 2);
        }

        return '$' . number_format($minimum, 2)
            . ' – $' . number_format($maximum, 2);
    }

    private function formatInventoryQuantity(int $millis): string
    {
        return rtrim(
            rtrim(number_format($millis / 1000, 3, '.', ''), '0'),
            '.',
        );
    }

    public function create(): View
    {
        return view('tenant.products.form', $this->formData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->validateImageUploads($request);
        $product = DB::connection('tenant')->transaction(
            fn () => $this->saveProduct(new Product(), $request->validated()),
        );
        $this->storeFormImages($request, $product);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Producto creado.');
    }

    public function show(Product $product): View
    {
        $product->load([
            'type',
            'category.parent',
            'collection',
            'line',
            'generalImages',
            'variants.images',
            'variants.inventoryStocks.warehouse.branch',
            'variants.attributeValues.attribute',
        ]);

        return view('tenant.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('tenant.products.form', $this->formData([
            'product' => $product->load(
                'generalImages',
                'variants.images',
                'variants.attributeValues',
            ),
        ]));
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $this->validateImageUploads($request);
        DB::connection('tenant')->transaction(
            fn () => $this->saveProduct($product, $request->validated()),
        );
        $this->storeFormImages($request, $product);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Producto actualizado.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->requestItems()->exists()) {
            return back()->withErrors([
                'product' => 'No se puede eliminar este producto porque tiene solicitudes de suministros asociadas.',
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($product): void {
            $product->variants()->each(function (ProductVariant $variant): void {
                $variant->attributeValues()->detach();
                $variant->delete();
            });
            $product->delete();
        });

        return redirect()
            ->route('products.index')
            ->with('success', 'Producto eliminado.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:tenant.products,id'],
            'category_id' => ['nullable', 'integer', 'exists:tenant.product_categories,id'],
            'product_collection_id' => ['nullable', 'integer', 'exists:tenant.product_collections,id'],
            'product_collection_line_id' => ['nullable', 'integer', 'exists:tenant.product_collection_lines,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['product_collection_line_id'] ?? null) {
            $this->ensureLine(
                (int) $data['product_collection_line_id'],
                isset($data['product_collection_id']) ? (int) $data['product_collection_id'] : null,
                $data['product_ids'],
            );
        }

        $updates = array_filter([
            'category_id' => $data['category_id'] ?? null,
            'product_collection_id' => $data['product_collection_id'] ?? null,
            'product_collection_line_id' => $data['product_collection_line_id'] ?? null,
        ], fn ($value) => $value !== null);

        if ($request->has('is_active')) {
            $updates['is_active'] = $request->boolean('is_active');
        }

        if ($updates === []) {
            throw ValidationException::withMessages([
                'bulk' => 'Selecciona al menos un cambio.',
            ]);
        }

        Product::whereIn('id', $data['product_ids'])->update($updates);

        return back()->with('success', 'Productos actualizados masivamente.');
    }

    public function categories(Request $request, TenantAccountAccess $access): View
    {
        return view('tenant.products.categories', [
            'categories' => ProductCategory::with('parent')
                ->withCount('products')
                ->orderBy('parent_key')
                ->orderBy('name')
                ->paginate(20),
            'canManage' => $this->canManage($request, $access),
        ]);
    }

    public function createCategory(): View
    {
        return view('tenant.products.category-form', [
            'parents' => ProductCategory::whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(StoreProductCategoryRequest $request): RedirectResponse
    {
        $this->saveCategory(new ProductCategory(), $request->validated());

        return redirect()
            ->route('products.categories')
            ->with('success', 'Categoría creada.');
    }

    public function editCategory(ProductCategory $category): View
    {
        return view('tenant.products.category-form', [
            'category' => $category,
            'parents' => ProductCategory::whereNull('parent_id')
                ->whereKeyNot($category)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateCategory(
        StoreProductCategoryRequest $request,
        ProductCategory $category,
    ): RedirectResponse {
        $this->saveCategory($category, $request->validated());

        return redirect()
            ->route('products.categories')
            ->with('success', 'Categoría actualizada.');
    }

    public function toggleCategory(ProductCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->withErrors([
                'category' => 'No se puede eliminar esta categoría porque tiene productos o subcategorías asociados.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('products.categories')
            ->with('success', 'Categoría eliminada.');
    }

    public function collections(Request $request, TenantAccountAccess $access): View
    {
        return view('tenant.products.collections', [
            'collections' => ProductCollection::withCount(['lines', 'products'])
                ->orderBy('name')
                ->paginate(20),
            'canManage' => $this->canManage($request, $access),
        ]);
    }

    public function createCollection(): View
    {
        return view('tenant.products.collection-form');
    }

    public function storeCollection(StoreProductCollectionRequest $request): RedirectResponse
    {
        $collection = $this->saveCollection(
            new ProductCollection(),
            $request->validated(),
        );

        return redirect()
            ->route('products.collections.show', $collection)
            ->with('success', 'Colección creada.');
    }

    public function showCollection(
        Request $request,
        ProductCollection $collection,
        TenantAccountAccess $access,
    ): View {
        return view('tenant.products.collection-show', [
            'collection' => $collection->load('lines'),
            'products' => $collection->products()->count(),
            'canManage' => $this->canManage($request, $access),
        ]);
    }

    public function editCollection(ProductCollection $collection): View
    {
        return view('tenant.products.collection-form', compact('collection'));
    }

    public function updateCollection(
        StoreProductCollectionRequest $request,
        ProductCollection $collection,
    ): RedirectResponse {
        $this->saveCollection($collection, $request->validated());

        return redirect()
            ->route('products.collections.show', $collection)
            ->with('success', 'Colección actualizada.');
    }

    public function toggleCollection(ProductCollection $collection): RedirectResponse
    {
        $collection->update(['is_active' => ! $collection->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function destroyCollection(ProductCollection $collection): RedirectResponse
    {
        if ($collection->products()->exists() || $collection->lines()->exists()) {
            return back()->withErrors([
                'collection' => 'No se puede eliminar esta colección porque tiene productos o líneas asociadas.',
            ]);
        }

        $collection->delete();

        return redirect()
            ->route('products.collections')
            ->with('success', 'Colección eliminada.');
    }

    public function storeLine(
        StoreProductCollectionLineRequest $request,
        ProductCollection $collection,
    ): RedirectResponse {
        $data = $request->validated();
        $data['product_collection_id'] = $collection->id;
        $data['normalized_name'] = $this->normal($data['name']);
        ProductCollectionLine::create($data);

        return back()->with('success', 'Línea creada.');
    }

    public function updateLine(
        StoreProductCollectionLineRequest $request,
        ProductCollection $collection,
        ProductCollectionLine $line,
    ): RedirectResponse {
        abort_unless($line->product_collection_id === $collection->id, 404);
        $data = $request->validated();
        $data['normalized_name'] = $this->normal($data['name']);
        $line->update($data);

        return back()->with('success', 'Línea actualizada.');
    }

    public function destroyLine(
        ProductCollection $collection,
        ProductCollectionLine $line,
    ): RedirectResponse {
        abort_unless($line->product_collection_id === $collection->id, 404);

        if ($line->products()->exists()) {
            return back()->withErrors([
                'line' => 'No se puede eliminar una línea que tiene productos asociados. Puedes marcarla como inactiva.',
            ]);
        }

        $line->delete();

        return back()->with('success', 'Línea eliminada.');
    }

    public function settings(): View
    {
        return view('tenant.products.settings', [
            'settings' => $this->settingsRecord(),
            'attributes' => ProductAttribute::with('values')->orderBy('sort_order')->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'manages_collections' => ['nullable', 'boolean'],
            'manages_collection_lines' => ['nullable', 'boolean'],
            'manages_taxes' => ['nullable', 'boolean'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ice_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'manages_multiple_prices' => ['nullable', 'boolean'],
            'manages_distribution_price' => ['nullable', 'boolean'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.name' => ['required', 'string', 'max:100'],
            'attributes.*.is_enabled' => ['nullable', 'boolean'],
            'attributes.*.values' => ['nullable', 'string'],
        ]);

        $settings = $this->settingsRecord();
        $collections = $request->boolean('manages_collections');
        $settings->update([
            'manages_collections' => $collections,
            'manages_collection_lines' => $collections
                && $request->boolean('manages_collection_lines'),
            'manages_taxes' => $request->boolean('manages_taxes'),
            'vat_percent' => $data['vat_percent'] ?? null,
            'ice_percent' => $data['ice_percent'] ?? null,
            'manages_multiple_prices' => $request->boolean('manages_multiple_prices'),
            'manages_distribution_price' => $request->boolean('manages_distribution_price'),
        ]);

        foreach (ProductAttribute::all() as $attribute) {
            $input = $data['attributes'][$attribute->id] ?? [];
            $attribute->update([
                'name' => $input['name'] ?? $attribute->name,
                'is_enabled' => $request->boolean("attributes.{$attribute->id}.is_enabled"),
            ]);

            $values = array_filter(
                array_map('trim', explode(',', $input['values'] ?? '')),
            );
            foreach ($values as $value) {
                ProductAttributeValue::firstOrCreate(
                    [
                        'product_attribute_id' => $attribute->id,
                        'normalized_value' => $this->normal($value),
                    ],
                    ['value' => Str::squish($value)],
                );
            }
        }

        return back()->with('success', 'Configuración de Productos guardada.');
    }

    private function saveProduct(Product $product, array $data): Product
    {
        $settings = $this->settingsRecord();
        if (! $settings->manages_collections) {
            $data['product_collection_id'] = null;
            $data['product_collection_line_id'] = null;
        } elseif (! $settings->manages_collection_lines) {
            $data['product_collection_line_id'] = null;
        }

        if ($data['product_collection_line_id'] ?? null) {
            $this->ensureLine(
                (int) $data['product_collection_line_id'],
                (int) ($data['product_collection_id'] ?? 0),
            );
        }

        $variants = $data['variants'] ?? [];
        unset($data['variants']);
        $product->fill($data)->save();

        $existingIds = [];
        foreach ($variants as $row) {
            $attributeIds = $row['attribute_value_ids'] ?? [];
            $values = ProductAttributeValue::whereIn('id', $attributeIds)
                ->whereHas(
                    'attribute',
                    fn (Builder $attribute) => $attribute->where('is_enabled', true),
                )
                ->pluck('id')
                ->all();

            if (count($values) !== count($attributeIds)) {
                throw ValidationException::withMessages([
                    'variants' => 'Una variante contiene atributos no habilitados.',
                ]);
            }

            unset($row['attribute_value_ids'], $row['stock']);
            $variant = $product->variants()->updateOrCreate(
                ['sku' => $row['sku']],
                $row,
            );
            $variant->attributeValues()->sync($values);
            $existingIds[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $existingIds)->delete();

        return $product;
    }

    private function validateImageUploads(Request $request): void
    {
        $request->validate([
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable', 'array'],
            'variant_images.*.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    private function storeFormImages(Request $request, Product $product): void
    {
        foreach ($request->file('images', []) as $file) {
            $this->storeImage($request, $product, null, $file);
        }

        foreach ($request->file('variant_images', []) as $key => $files) {
            $variant = $product->variants()->find($key)
                ?: $product->variants()->where('sku', $request->input("variants.{$key}.sku"))->first();
            if (! $variant) {
                continue;
            }

            foreach ($files as $file) {
                $this->storeImage($request, $product, $variant, $file);
            }
        }
    }

    private function storeImage(
        Request $request,
        Product $product,
        ?ProductVariant $variant,
        mixed $file,
    ): void {
        if (! $file->isValid()) {
            return;
        }

        $account = $request->attributes->get('tenantAccount');
        $directory = $variant
            ? "tenants/{$account->id}/products/{$product->id}/variants/{$variant->id}"
            : "tenants/{$account->id}/products/{$product->id}/images";
        $images = $variant ? $variant->ownImages() : $product->generalImages();
        $contentHash = hash_file('sha256', $file->getRealPath());

        if (is_string($contentHash) && ProductImage::query()
            ->where('product_id', $product->id)
            ->where('content_hash', $contentHash)
            ->exists()) {
            return;
        }

        $path = $file->store($directory, 'local');

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'content_hash' => $contentHash ?: null,
            'is_primary' => ! $images->exists(),
            'sort_order' => (int) $images->max('sort_order') + 1,
        ]);
    }

    private function saveCategory(ProductCategory $category, array $data): ProductCategory
    {
        $parent = $data['parent_id'] ? ProductCategory::findOrFail($data['parent_id']) : null;
        if ($parent?->parent_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Solo se permiten dos niveles de categorías.',
            ]);
        }

        $data['parent_key'] = $parent?->id ?? 0;
        $data['normalized_name'] = $this->normal($data['name']);
        $data['slug'] = $this->slug($data['name'], $category->id);
        $category->fill($data)->save();

        return $category;
    }

    private function saveCollection(
        ProductCollection $collection,
        array $data,
    ): ProductCollection {
        $data['normalized_name'] = $this->normal($data['name']);
        $collection->fill($data)->save();

        return $collection;
    }

    private function ensureLine(
        int $lineId,
        ?int $collectionId,
        array $productIds = [],
    ): void {
        $line = ProductCollectionLine::findOrFail($lineId);

        if (! $collectionId) {
            $collections = Product::whereIn('id', $productIds)
                ->pluck('product_collection_id')
                ->filter()
                ->unique();
            if ($collections->count() !== 1) {
                throw ValidationException::withMessages([
                    'product_collection_line_id' => 'Para asignar una línea, selecciona una colección común o productos de una misma colección.',
                ]);
            }
            $collectionId = (int) $collections->first();
        }

        if ($line->product_collection_id !== $collectionId) {
            throw ValidationException::withMessages([
                'product_collection_line_id' => 'La línea debe pertenecer a la colección seleccionada.',
            ]);
        }
    }

    private function settingsRecord(): ProductSetting
    {
        $settings = ProductSetting::firstOrCreate([], [
            'manages_collections' => false,
            'manages_collection_lines' => false,
        ]);

        foreach ([['size', 'Talla', 1], ['color', 'Color', 2]] as [$code, $name, $order]) {
            ProductAttribute::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => $order],
            );
        }

        return $settings;
    }

    private function enabledAttributes()
    {
        return ProductAttribute::where('is_enabled', true)
            ->with([
                'values' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('value'),
            ])
            ->orderBy('sort_order')
            ->get();
    }

    private function formData(array $extra = []): array
    {
        return $extra + [
            'settings' => $this->settingsRecord(),
            'attributes' => $this->enabledAttributes(),
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
            'collections' => ProductCollection::where('is_active', true)
                ->with('lines')
                ->orderBy('name')
                ->get(),
            'productTypes' => ProductType::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function canManage(Request $request, TenantAccountAccess $access): bool
    {
        return $access->canManageTenant(
            $request->user(),
            $request->attributes->get('tenantAccount'),
        );
    }

    private function normal(string $value): string
    {
        return mb_strtolower(Str::squish($value));
    }

    private function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while (ProductCategory::where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore))
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
