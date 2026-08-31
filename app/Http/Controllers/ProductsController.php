<?php
namespace App\Http\Controllers;
use App\Http\Requests\{StoreProductCategoryRequest,StoreProductCollectionLineRequest,StoreProductCollectionRequest,StoreProductRequest};
use App\Modules\Products\Models\{Product,ProductAttribute,ProductAttributeValue,ProductCategory,ProductCollection,ProductCollectionLine,ProductSetting,ProductType,ProductVariant};
use Illuminate\Database\Eloquent\Builder; use Illuminate\Http\{RedirectResponse,Request}; use Illuminate\Support\Facades\DB; use Illuminate\Support\Str; use Illuminate\Validation\ValidationException; use Illuminate\View\View;

class ProductsController extends Controller {
    public function index(Request $r): View
    {
        $settings = $this->settingsRecord();
        $attributes = $this->enabledAttributes();
        $q = Product::query()
            ->with(['type', 'category.parent', 'collection', 'line'])
            ->withCount('variants')
            ->withSum('variants', 'stock');

        if ($r->filled('search')) {
            $search = $r->string('search')->toString();
            $q->where(fn (Builder $x) => $x
                ->where('name', 'like', "%{$search}%")
                ->orWhere('catalog_code', 'like', "%{$search}%")
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%")));
        }

        foreach (['category_id', 'product_collection_id', 'product_collection_line_id', 'product_type_id'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->integer($field));
            }
        }

        if ($r->filled('parent_category_id')) {
            $q->whereHas('category', fn (Builder $c) => $c->where('parent_id', $r->integer('parent_category_id')));
        }

        if ($r->filled('status')) {
            $q->where('is_active', (string) $r->input('status') === 'active');
        }

        foreach ($attributes as $attribute) {
            if ($r->filled('attribute_' . $attribute->id)) {
                $q->whereHas('variants.attributeValues', fn (Builder $v) => $v->whereKey($r->integer('attribute_' . $attribute->id)));
            }
        }

        return view('tenant.products.index', [
            'products' => $q->latest()->paginate(15)->withQueryString(),
            'settings' => $settings,
            'attributes' => $attributes,
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
            'collections' => ProductCollection::where('is_active', true)->orderBy('name')->get(),
            'lines' => ProductCollectionLine::where('is_active', true)->orderBy('name')->get(),
            'productTypes' => ProductType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'summary' => [
                'total' => Product::count(),
                'active' => Product::where('is_active', true)->count(),
                'inactive' => Product::where('is_active', false)->count(),
                'variants' => ProductVariant::count(),
            ],
        ]);
    }
    public function create(): View { return view('tenant.products.form',$this->formData()); }
    public function store(StoreProductRequest $r): RedirectResponse { $product=DB::connection('tenant')->transaction(fn()=> $this->saveProduct(new Product,$r->validated())); return redirect()->route('products.show',$product)->with('success','Producto creado.'); }
    public function show(Product $product): View { $product->load(['type','category.parent','collection','line','variants.attributeValues.attribute']); return view('tenant.products.show',compact('product')); }
    public function edit(Product $product): View { return view('tenant.products.form',$this->formData(['product'=>$product->load('variants.attributeValues')])); }
    public function update(StoreProductRequest $r,Product $product): RedirectResponse { DB::connection('tenant')->transaction(fn()=> $this->saveProduct($product,$r->validated())); return redirect()->route('products.show',$product)->with('success','Producto actualizado.'); }
    public function toggle(Product $product): RedirectResponse { $product->update(['is_active'=>!$product->is_active]); return back()->with('success','Estado actualizado.'); }
    public function destroy(Product $product): RedirectResponse { DB::connection('tenant')->transaction(function () use ($product): void { $product->variants()->each(function ($variant): void { $variant->attributeValues()->detach(); $variant->delete(); }); $product->delete(); }); return redirect()->route('products.index')->with('success','Producto eliminado.'); }
    public function bulk(Request $r): RedirectResponse { $data=$r->validate(['product_ids'=>['required','array','min:1'],'product_ids.*'=>['integer','exists:tenant.products,id'],'category_id'=>['nullable','integer','exists:tenant.product_categories,id'],'product_collection_id'=>['nullable','integer','exists:tenant.product_collections,id'],'product_collection_line_id'=>['nullable','integer','exists:tenant.product_collection_lines,id'],'is_active'=>['nullable','boolean']]); if($data['product_collection_line_id']??null) $this->ensureLine((int)$data['product_collection_line_id'],isset($data['product_collection_id'])?(int)$data['product_collection_id']:null,$data['product_ids']); $updates=array_filter(['category_id'=>$data['category_id']??null,'product_collection_id'=>$data['product_collection_id']??null,'product_collection_line_id'=>$data['product_collection_line_id']??null],fn($v)=>$v!==null); if($r->has('is_active'))$updates['is_active']=$r->boolean('is_active'); if(!$updates) throw ValidationException::withMessages(['bulk'=>'Selecciona al menos un cambio.']); Product::whereIn('id',$data['product_ids'])->update($updates); return back()->with('success','Productos actualizados masivamente.'); }
    public function categories(): View { return view('tenant.products.categories',['categories'=>ProductCategory::with('parent')->withCount('products')->orderBy('parent_key')->orderBy('name')->paginate(20)]); }
    public function createCategory(): View { return view('tenant.products.category-form',['parents'=>ProductCategory::whereNull('parent_id')->orderBy('name')->get()]); }
    public function storeCategory(StoreProductCategoryRequest $r): RedirectResponse { $category=$this->saveCategory(new ProductCategory,$r->validated()); return redirect()->route('products.categories')->with('success','Categoría creada.'); }
    public function editCategory(ProductCategory $category): View { return view('tenant.products.category-form',['category'=>$category,'parents'=>ProductCategory::whereNull('parent_id')->whereKeyNot($category)->orderBy('name')->get()]); }
    public function updateCategory(StoreProductCategoryRequest $r,ProductCategory $category): RedirectResponse { $this->saveCategory($category,$r->validated()); return redirect()->route('products.categories')->with('success','Categoría actualizada.'); }
    public function toggleCategory(ProductCategory $category): RedirectResponse { $category->update(['is_active'=>!$category->is_active]); return back()->with('success','Estado actualizado.'); }
    public function collections(): View { return view('tenant.products.collections',['collections'=>ProductCollection::withCount(['lines','products'])->orderBy('name')->paginate(20)]); }
    public function createCollection(): View { return view('tenant.products.collection-form'); }
    public function storeCollection(StoreProductCollectionRequest $r): RedirectResponse { $collection=$this->saveCollection(new ProductCollection,$r->validated()); return redirect()->route('products.collections.show',$collection)->with('success','Colección creada.'); }
    public function showCollection(ProductCollection $collection): View { return view('tenant.products.collection-show',['collection'=>$collection->load('lines'),'products'=>$collection->products()->count()]); }
    public function editCollection(ProductCollection $collection): View { return view('tenant.products.collection-form',compact('collection')); }
    public function updateCollection(StoreProductCollectionRequest $r,ProductCollection $collection): RedirectResponse { $this->saveCollection($collection,$r->validated()); return redirect()->route('products.collections.show',$collection)->with('success','Colección actualizada.'); }
    public function toggleCollection(ProductCollection $collection): RedirectResponse { $collection->update(['is_active'=>!$collection->is_active]); return back()->with('success','Estado actualizado.'); }
    public function storeLine(StoreProductCollectionLineRequest $r,ProductCollection $collection): RedirectResponse { $data=$r->validated();$data['product_collection_id']=$collection->id;$data['normalized_name']=$this->normal($data['name']); ProductCollectionLine::create($data);return back()->with('success','Línea creada.'); }
    public function updateLine(StoreProductCollectionLineRequest $r,ProductCollection $collection,ProductCollectionLine $line): RedirectResponse { abort_unless($line->product_collection_id===$collection->id,404);$data=$r->validated();$data['normalized_name']=$this->normal($data['name']);$line->update($data);return back()->with('success','Línea actualizada.'); }
    public function settings(): View { return view('tenant.products.settings',['settings'=>$this->settingsRecord(),'attributes'=>ProductAttribute::with('values')->orderBy('sort_order')->get()]); }
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
            'manages_collection_lines' => $collections && $request->boolean('manages_collection_lines'),
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
                'is_enabled' => $request->boolean('attributes.'.$attribute->id.'.is_enabled'),
            ]);

            foreach (array_filter(array_map('trim', explode(',', $input['values'] ?? ''))) as $value) {
                ProductAttributeValue::firstOrCreate(
                    ['product_attribute_id' => $attribute->id, 'normalized_value' => $this->normal($value)],
                    ['value' => Str::squish($value)],
                );
            }
        }

        return back()->with('success', 'Configuración de Productos guardada.');
    }
    private function saveProduct(Product $product,array $data): Product { $settings=$this->settingsRecord();if(!$settings->manages_collections){$data['product_collection_id']=null;$data['product_collection_line_id']=null;}elseif(!$settings->manages_collection_lines)$data['product_collection_line_id']=null;if($data['product_collection_line_id']??null)$this->ensureLine((int)$data['product_collection_line_id'],(int)($data['product_collection_id']??0));$variants=$data['variants']??[];unset($data['variants']);$product->fill($data)->save();$existing=[];foreach($variants as $row){$values=ProductAttributeValue::whereIn('id',$row['attribute_value_ids']??[])->whereHas('attribute',fn(Builder $a)=>$a->where('is_enabled',true))->pluck('id')->all();if(count($values)!==count($row['attribute_value_ids']??[]))throw ValidationException::withMessages(['variants'=>'Una variante contiene atributos no habilitados.']);$variant=$product->variants()->updateOrCreate(['sku'=>$row['sku']],collect($row)->except('attribute_value_ids')->all());$variant->attributeValues()->sync($values);$existing[]=$variant->id;}$product->variants()->whereNotIn('id',$existing)->delete();return $product; }
    private function saveCategory(ProductCategory $category,array $data): ProductCategory { $parent=$data['parent_id']?ProductCategory::findOrFail($data['parent_id']):null;if($parent?->parent_id)throw ValidationException::withMessages(['parent_id'=>'Solo se permiten dos niveles de categorías.']);$data['parent_key']=$parent?->id??0;$data['normalized_name']=$this->normal($data['name']);$data['slug']=$this->slug($data['name'],$category->id);$category->fill($data)->save();return $category; }
    private function saveCollection(ProductCollection $collection,array $data): ProductCollection { $data['normalized_name']=$this->normal($data['name']);$collection->fill($data)->save();return $collection; }
    private function ensureLine(int $lineId,?int $collectionId,array $productIds=[]):void { $line=ProductCollectionLine::findOrFail($lineId);if(! $collectionId){$collections=Product::whereIn('id',$productIds)->pluck('product_collection_id')->filter()->unique();if($collections->count()!==1)throw ValidationException::withMessages(['product_collection_line_id'=>'Para asignar una línea, selecciona una colección común o productos de una misma colección.']);$collectionId=(int)$collections->first();}if($line->product_collection_id!==$collectionId)throw ValidationException::withMessages(['product_collection_line_id'=>'La línea debe pertenecer a la colección seleccionada.']); }
    private function settingsRecord():ProductSetting { $settings=ProductSetting::firstOrCreate([],['manages_collections'=>false,'manages_collection_lines'=>false]);foreach([['size','Talla',1],['color','Color',2]] as [$code,$name,$order])ProductAttribute::firstOrCreate(['code'=>$code],['name'=>$name,'sort_order'=>$order]);return $settings; }
    private function enabledAttributes(){return ProductAttribute::where('is_enabled',true)->with(['values'=>fn($q)=>$q->where('is_active',true)->orderBy('sort_order')->orderBy('value')])->orderBy('sort_order')->get();}
    private function formData(array $extra=[]):array{return $extra+['settings'=>$this->settingsRecord(),'attributes'=>$this->enabledAttributes(),'categories'=>ProductCategory::where('is_active',true)->orderBy('name')->get(),'collections'=>ProductCollection::where('is_active',true)->with('lines')->orderBy('name')->get(),'productTypes'=>ProductType::where('is_active',true)->orderBy('sort_order')->orderBy('name')->get()];}
    private function normal(string $value):string{return mb_strtolower(Str::squish($value));} private function slug(string $name,?int $ignore=null):string{$base=Str::slug($name)?:'categoria';$slug=$base;$i=2;while(ProductCategory::where('slug',$slug)->when($ignore,fn($q)=>$q->where('id','!=',$ignore))->exists())$slug=$base.'-'.$i++;return $slug;}
}
