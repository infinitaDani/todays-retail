<?php

namespace App\Modules\Products\Services;

use App\Modules\Operations\Models\ScheduleSetting;
use App\Modules\Products\Models\InventoryStock;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductCategory;
use App\Modules\Products\Models\ProductCollection;
use App\Modules\Products\Models\ProductImport;
use App\Modules\Products\Models\ProductSetting;
use App\Modules\Products\Models\ProductType;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ProductExcelImportService
{
    private const REQUIRED_HEADERS = ['codigo', 'nombre', 'codigo_catalogo'];

    private const HEADER_MAP = [
        'codigo' => 'sku',
        'codigo_auxiliar' => 'auxiliary_code',
        'categoria' => 'category',
        'nombre' => 'name',
        'descripcion' => 'description',
        'codigo_catalogo' => 'catalog_code',
        'unidad' => 'unit',
        'tipo' => 'type',
        'para_la_venta' => 'is_for_sale',
        'para_la_compra' => 'is_for_purchase',
        'inventariable' => 'is_inventory_item',
        'minimo_en_inventario' => 'minimum_stock',
        'pvp1' => 'pvp1',
        'pvp1_iva' => 'pvp1_with_tax',
        'pvp2' => 'pvp2',
        'pvp2_iva' => 'pvp2_with_tax',
        'pvp3' => 'pvp3',
        'pvp3_iva' => 'pvp3_with_tax',
        'pvpdist' => 'distribution_price',
        'pvpdist_iva' => 'distribution_price_with_tax',
        'stock' => 'stock',
        'porcentaje_iva' => 'vat_rate',
        'porcentaje_ice' => 'ice_rate',
        'colleccion' => 'collection',
        'coleccion' => 'collection',
    ];

    public function __construct(
        private readonly ProductSizeDetectionService $sizeDetection
    ) {
    }

    public function preview(
        string $path,
        bool $detectSizeFromCode = false
    ): array {
        $rows = $this->readRows($path);
        $existingSkus = ProductVariant::query()
            ->whereIn('sku', collect($rows)->pluck('sku')->filter()->unique())
            ->pluck('sku')
            ->map(fn (string $sku) => $this->key($sku))
            ->all();

        $occurrences = collect($rows)
            ->pluck('sku')
            ->filter()
            ->map(fn (string $sku) => $this->key($sku))
            ->countBy();

        $result = [];

        foreach ($rows as $row) {
            $errors = $row['_errors'];
            $warnings = $row['_warnings'];
            $skuKey = $this->key($row['sku'] ?? '');
            $sizeDetection = null;

            if ($detectSizeFromCode) {
                $sizeDetection = $this->sizeDetection->detect(
                    $row['sku'],
                    $row['catalog_code']
                );

                if ($sizeDetection['warning'] !== null) {
                    $warnings[] = $sizeDetection['warning'];
                }
            }

            if ($skuKey !== '' && ($occurrences[$skuKey] ?? 0) > 1) {
                $errors[] = 'El código está duplicado dentro del archivo.';
            }

            if ($skuKey !== '' && in_array($skuKey, $existingSkus, true)) {
                $status = 'existing';
                $messages = ['Ya existe / no será importado.'];
            } elseif ($errors !== []) {
                $status = 'error';
                $messages = $errors;
            } elseif ($warnings !== []) {
                $status = 'warning';
                $messages = $warnings;
            } else {
                $status = 'ready';
                $messages = [];
            }

            $result[] = array_merge($row, [
                'detected_size' => $sizeDetection['detected_size'] ?? null,
                'detected_size_value_id' => $sizeDetection['attribute_value_id'] ?? null,
                'size_suffix' => $sizeDetection['suffix'] ?? null,
                'status' => $status,
                'messages' => $messages,
            ]);
        }

        return [
            'rows' => $result,
            'summary' => [
                'total' => count($result),
                'ready' => collect($result)->where('status', 'ready')->count(),
                'warning' => collect($result)->where('status', 'warning')->count(),
                'existing' => collect($result)->where('status', 'existing')->count(),
                'error' => collect($result)->where('status', 'error')->count(),
            ],
        ];
    }

    public function import(ProductImport $import): array
    {
        $preview = $this->preview(
            $import->excel_path,
            (bool) $import->detect_size_from_code
        );
        $created = 0;
        $existing = 0;
        $errors = [];
        $settings = ScheduleSetting::current();
        $importsStock = $settings->manages_inventory
            && $settings->inventory_by_branch
            && $import->warehouse_id !== null;

        if ($importsStock) {
            Warehouse::query()
                ->whereKey($import->warehouse_id)
                ->where('is_active', true)
                ->firstOrFail();
        }

        foreach ($preview['rows'] as $row) {
            if ($row['status'] === 'existing') {
                $existing++;
                continue;
            }

            if (in_array($row['status'], ['error'], true)) {
                $errors[] = $this->rowError($row);
                continue;
            }

            try {
                DB::connection('tenant')->transaction(function () use ($row, $import, $importsStock, &$created, &$existing): void {
                    if (ProductVariant::query()->where('sku', $row['sku'])->exists()) {
                        $existing++;

                        return;
                    }

                    $product = Product::query()
                        ->where('catalog_code', $row['catalog_code'])
                        ->oldest('id')
                        ->first();

                    if (! $product) {
                        $product = Product::create([
                            'catalog_code' => $row['catalog_code'],
                            'name' => $row['name'],
                            'description' => $row['description'] ?: null,
                            'category_id' => $this->categoryId($row['category']),
                            'product_collection_id' => $this->collectionId($row['collection']),
                            'product_type_id' => $this->typeId($row['type']),
                            'unit' => $row['unit'] ?: null,
                            'is_active' => true,
                        ]);
                    }
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $row['sku'],
                        'auxiliary_code' => $row['auxiliary_code'] ?: null,
                        'minimum_stock' => $row['minimum_stock'],
                        'sale_price' => $row['pvp1'],
                        'pvp1' => $row['pvp1'],
                        'pvp1_with_tax' => $row['pvp1_with_tax'],
                        'pvp2' => $row['pvp2'],
                        'pvp2_with_tax' => $row['pvp2_with_tax'],
                        'pvp3' => $row['pvp3'],
                        'pvp3_with_tax' => $row['pvp3_with_tax'],
                        'distribution_price' => $row['distribution_price'],
                        'distribution_price_with_tax' => $row['distribution_price_with_tax'],
                        'is_for_sale' => $row['is_for_sale'],
                        'is_for_purchase' => $row['is_for_purchase'],
                        'is_inventory_item' => $row['is_inventory_item'],
                        'is_taxable' => $row['vat_rate'] > 0 || $row['ice_rate'] > 0,
                        'tax_rate' => $row['vat_rate'] ?: null,
                        'ice_rate' => $row['ice_rate'] ?: null,
                        'is_active' => true,
                    ]);

                    if ($import->detect_size_from_code) {
                        $sizeDetection = $this->sizeDetection->detect(
                            $row['sku'],
                            $row['catalog_code']
                        );

                        if ($sizeDetection['attribute_value_id'] !== null) {
                            $variant->attributeValues()->syncWithoutDetaching([
                                $sizeDetection['attribute_value_id'],
                            ]);
                        }
                    }

                    if ($importsStock && $row['stock'] !== null) {
                        InventoryStock::updateOrCreate(
                            [
                                'warehouse_id' => $import->warehouse_id,
                                'product_variant_id' => $variant->id,
                            ],
                            [
                                'quantity' => $row['stock'],
                                'sync_source' => 'excel_initial',
                            ],
                        );
                    }

                    $created++;
                });
            } catch (\Throwable $exception) {
                $errors[] = $this->rowError($row, $exception->getMessage());
            }
        }

        return [
            'total' => $preview['summary']['total'],
            'created' => $created,
            'existing' => $existing,
            'warnings' => $preview['summary']['warning'],
            'errors' => $errors,
        ];
    }

    private function readRows(string $path): array
    {
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('El archivo temporal ya no está disponible. Vuelve a cargarlo.');
        }

        $sheet = IOFactory::load(Storage::disk('local')->path($path))
            ->getActiveSheet();

        $rawRows = $sheet->toArray(null, true, true, false);
        $headerRow = null;
        $headers = [];

        foreach (array_slice($rawRows, 0, 20, true) as $index => $cells) {
            $candidate = array_map(fn ($value) => $this->header((string) $value), $cells);

            if (count(array_intersect(self::REQUIRED_HEADERS, $candidate)) === count(self::REQUIRED_HEADERS)) {
                $headerRow = $index;
                $headers = $candidate;
                break;
            }
        }

        if ($headerRow === null) {
            throw new RuntimeException('No se encontraron encabezados válidos. El archivo debe incluir Código, Nombre y Código Catálogo.');
        }

        $rows = [];

        foreach (array_slice($rawRows, $headerRow + 1, null, true) as $index => $cells) {
            if (collect($cells)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $source = [];
            foreach ($headers as $column => $header) {
                if (isset(self::HEADER_MAP[$header])) {
                    $source[self::HEADER_MAP[$header]] = $cells[$column] ?? null;
                }
            }

            $rows[] = $this->normalizeRow($source, $index + 1);
        }

        return $rows;
    }

    private function normalizeRow(array $source, int $rowNumber): array
    {
        $errors = [];
        $warnings = [];
        $text = fn (string $field) => Str::squish(trim((string) ($source[$field] ?? '')));
        $number = function (string $field, bool $required = false) use ($source, &$errors, $rowNumber): ?float {
            $value = trim((string) ($source[$field] ?? ''));
            if ($value === '') {
                return null;
            }
            $value = $this->numericValue($value);
            if (! is_numeric($value)) {
                $errors[] = "Fila {$rowNumber}: {$field} no es un número válido.";
                return null;
            }
            return (float) $value;
        };

        $sku = $text('sku');
        $catalogCode = $text('catalog_code');
        $name = $text('name');

        if ($sku === '') {
            $errors[] = 'Código es obligatorio.';
        }
        if ($catalogCode === '') {
            $errors[] = 'Código Catálogo es obligatorio.';
        }
        if ($name === '') {
            $errors[] = 'Nombre es obligatorio.';
        }

        $type = $text('type');
        if ($type === '') {
            $type = 'Producto';
            $warnings[] = 'Tipo vacío: se utilizará Producto.';
        } elseif (! ProductType::query()->where('normalized_name', $this->key($type))->exists()) {
            $warnings[] = 'El tipo no existe todavía y será creado.';
        }

        if ($text('category') !== '' && ! ProductCategory::query()
            ->where('parent_key', 0)
            ->where('normalized_name', $this->key($text('category')))
            ->exists()) {
            $warnings[] = 'La categoría no existe todavía y será creada.';
        }

        if ($text('collection') !== '' && ProductSetting::first()?->manages_collections
            && ! ProductCollection::query()
                ->where('normalized_name', $this->key($text('collection')))
                ->exists()) {
            $warnings[] = 'La colección no existe todavía y será creada.';
        } elseif ($text('collection') !== '' && ! (bool) ProductSetting::first()?->manages_collections) {
            $warnings[] = 'La colección fue ignorada porque está desactivada en la configuración del tenant.';
        }

        foreach (['pvp2', 'pvp2_with_tax', 'pvp3', 'pvp3_with_tax', 'distribution_price', 'distribution_price_with_tax'] as $field) {
            if ($text($field) !== '' && ! $this->optionalPriceEnabled($field)) {
                $warnings[] = 'Un precio opcional fue omitido porque está desactivado en la configuración del tenant.';
                break;
            }
        }

        $pvp2 = $number('pvp2');
        $pvp2WithTax = $number('pvp2_with_tax');
        $pvp3 = $number('pvp3');
        $pvp3WithTax = $number('pvp3_with_tax');
        $distributionPrice = $number('distribution_price');
        $distributionPriceWithTax = $number('distribution_price_with_tax');

        return [
            'row_number' => $rowNumber,
            'sku' => $sku,
            'auxiliary_code' => $text('auxiliary_code'),
            'catalog_code' => $catalogCode,
            'category' => $text('category'),
            'name' => $name,
            'description' => $text('description'),
            'unit' => $text('unit'),
            'type' => $type,
            'collection' => $text('collection'),
            'is_for_sale' => $this->boolean($source['is_for_sale'] ?? null),
            'is_for_purchase' => $this->boolean($source['is_for_purchase'] ?? null),
            'is_inventory_item' => $this->boolean($source['is_inventory_item'] ?? null),
            'minimum_stock' => $number('minimum_stock'),
            'pvp1' => $number('pvp1'),
            'pvp1_with_tax' => $number('pvp1_with_tax'),
            'pvp2' => $this->optionalPriceEnabled('pvp2') ? $pvp2 : null,
            'pvp2_with_tax' => $this->optionalPriceEnabled('pvp2_with_tax') ? $pvp2WithTax : null,
            'pvp3' => $this->optionalPriceEnabled('pvp3') ? $pvp3 : null,
            'pvp3_with_tax' => $this->optionalPriceEnabled('pvp3_with_tax') ? $pvp3WithTax : null,
            'distribution_price' => $this->optionalPriceEnabled('distribution_price') ? $distributionPrice : null,
            'distribution_price_with_tax' => $this->optionalPriceEnabled('distribution_price_with_tax') ? $distributionPriceWithTax : null,
            'stock' => $number('stock'),
            'vat_rate' => $this->percent($source['vat_rate'] ?? null, 'Porcentaje IVA', $errors),
            'ice_rate' => $this->percent($source['ice_rate'] ?? null, 'Porcentaje ICE', $errors),
            '_errors' => $errors,
            '_warnings' => $warnings,
        ];
    }

    private function categoryId(string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        $normalized = $this->key($name);
        $category = ProductCategory::query()
            ->where('parent_key', 0)
            ->where('normalized_name', $normalized)
            ->first();

        if ($category) {
            return $category->id;
        }

        $baseSlug = Str::slug($name) ?: 'categoria';
        $slug = $baseSlug;
        $suffix = 2;
        while (ProductCategory::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix++;
        }

        return ProductCategory::create([
            'parent_key' => 0,
            'name' => $name,
            'normalized_name' => $normalized,
            'slug' => $slug,
            'is_active' => true,
        ])->id;
    }

    private function collectionId(string $name): ?int
    {
        if ($name === '' || ! ProductSetting::first()?->manages_collections) {
            return null;
        }

        $normalized = $this->key($name);

        return ProductCollection::firstOrCreate(
            ['normalized_name' => $normalized],
            ['name' => $name, 'is_active' => true],
        )->id;
    }

    private function typeId(string $name): int
    {
        $normalized = $this->key($name);

        return ProductType::firstOrCreate(
            ['normalized_name' => $normalized],
            ['name' => $name, 'is_active' => true],
        )->id;
    }

    private function percent(mixed $value, string $label, array &$errors): float
    {
        $text = strtoupper(trim((string) $value));
        if ($text === '') {
            return 0;
        }
        $text = str_replace(['IVA', 'ICE', '%', ',', ' '], ['', '', '', '.', ''], $text);
        if (! is_numeric($text) || (float) $text < 0 || (float) $text > 100) {
            $errors[] = "{$label} no es un porcentaje válido.";
            return 0;
        }
        return (float) $text;
    }

    private function boolean(mixed $value): bool
    {
        return in_array(
            $this->key((string) $value),
            ['si', 'yes', 'true', '1', 'x'],
            true
        );
    }

    private function header(string $value): string
    {
        return trim(
            preg_replace(
                '/[^a-z0-9]+/',
                '_',
                mb_strtolower(Str::ascii(trim($value))),
            ),
            '_',
        );
    }

    private function key(string $value): string
    {
        return mb_strtolower(Str::squish(Str::ascii(trim($value))));
    }

    private function optionalPriceEnabled(string $field): bool
    {
        $settings = ProductSetting::first();
        return match ($field) {
            'distribution_price', 'distribution_price_with_tax' => (bool) $settings?->manages_distribution_price,
            default => (bool) $settings?->manages_multiple_prices,
        };
    }

    private function numericValue(string $value): string
    {
        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                return str_replace(',', '.', str_replace('.', '', $value));
            }

            return str_replace(',', '', $value);
        }

        return str_replace(',', '.', $value);
    }

    private function rowError(array $row, ?string $message = null): array
    {
        return [
            'row' => $row['row_number'],
            'sku' => $row['sku'],
            'message' => $message ?? implode(' ', $row['messages']),
        ];
    }
}
