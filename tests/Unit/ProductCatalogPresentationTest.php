<?php

namespace Tests\Unit;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class ProductCatalogPresentationTest extends TestCase
{
    public function test_product_has_eager_loadable_catalog_image_relation(): void
    {
        $this->assertInstanceOf(
            HasOne::class,
            (new Product())->catalogImage(),
        );
    }

    public function test_catalog_uses_authoritative_inventory_and_pvp_with_tax(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/ProductsController.php')
        );

        $this->assertStringContainsString(
            'variants.inventoryStocks.warehouse.branch',
            $controller,
        );
        $this->assertStringContainsString("->pluck('pvp1_with_tax')", $controller);
        $this->assertStringNotContainsString('operational_stock_total', $controller);
        $this->assertStringNotContainsString('variants.stock', $controller);
    }

    public function test_catalog_eager_loads_image_variants_size_and_inventory_structure(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/ProductsController.php')
        );

        $this->assertStringContainsString('catalogImage:id,product_id', $controller);
        $this->assertStringContainsString('variants.attributeValues.attribute', $controller);
        $this->assertStringContainsString("->where('code', 'size')", $controller);
        $this->assertStringContainsString('variants.inventoryStocks', $controller);
        $this->assertStringContainsString("'warehouses' =>", $controller);
    }

    public function test_catalog_columns_follow_the_required_order(): void
    {
        $view = file_get_contents(
            resource_path('views/tenant/products/index.blade.php')
        );
        $headers = [
            '<th></th>',
            '<th>Imagen</th>',
            '<th>Código</th>',
            '<th>Producto</th>',
            '<th>PVP + IVA</th>',
            '<th>Stock</th>',
            '<th>Categoría</th>',
            '<th>Cód. Gral</th>',
            '<th>Línea</th>',
            '<th>Estado</th>',
        ];
        $lastPosition = -1;

        foreach ($headers as $header) {
            $position = strpos($view, $header, $lastPosition + 1);

            $this->assertNotFalse($position, "Missing catalog header {$header}.");
            $this->assertGreaterThan($lastPosition, $position);
            $lastPosition = $position;
        }
    }

    public function test_removed_data_does_not_occupy_catalog_columns(): void
    {
        $view = file_get_contents(
            resource_path('views/tenant/products/index.blade.php')
        );
        $tableHeader = strstr($view, '<thead>');
        $tableHeader = strstr($tableHeader, '</thead>', true);

        $this->assertStringNotContainsString('<th>Tipo</th>', $tableHeader);
        $this->assertStringNotContainsString('<th>Colección</th>', $tableHeader);
        $this->assertStringNotContainsString('<th>Variantes</th>', $tableHeader);
    }
}
