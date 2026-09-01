<?php

namespace Tests\Unit;

use App\Modules\Products\Models\InventoryStock;
use App\Modules\Products\Models\InventoryStockImport;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Services\InventoryStockImportService;
use Tests\TestCase;

class InventoryStockImportTest extends TestCase
{
    public function test_stock_import_history_uses_tenant_connection(): void
    {
        $this->assertSame(
            'tenant',
            (new InventoryStockImport())->getConnectionName(),
        );
    }

    public function test_existing_sku_is_classified_as_quantity_replacement(): void
    {
        $preview = $this->classify('SKU-1', '7.000', '3.000');

        $this->assertSame('update', $preview['rows'][0]['status']);
        $this->assertSame('7.000', $preview['rows'][0]['current_quantity']);
        $this->assertSame('3.000', $preview['rows'][0]['file_quantity']);
    }

    public function test_zero_is_a_valid_replacement_quantity(): void
    {
        $preview = $this->classify('SKU-1', '7.000', '0.000');

        $this->assertSame('update', $preview['rows'][0]['status']);
        $this->assertSame('0.000', $preview['rows'][0]['file_quantity']);
    }

    public function test_missing_sku_is_not_found_and_no_variant_is_created(): void
    {
        $service = app(InventoryStockImportService::class);
        $preview = $service->classifyRows(
            [$this->row('UNKNOWN', '2.000')],
            collect(),
            collect(),
        );

        $this->assertSame('not_found', $preview['rows'][0]['status']);
        $this->assertSame(1, $preview['summary']['not_found']);

        $source = file_get_contents(
            app_path('Modules/Products/Services/InventoryStockImportService.php')
        );

        $this->assertStringNotContainsString('ProductVariant::create', $source);
        $this->assertStringNotContainsString('Product::create', $source);
    }

    public function test_duplicate_sku_rows_are_both_errors(): void
    {
        $variant = $this->variant(10, 'SKU-1');
        $service = app(InventoryStockImportService::class);
        $preview = $service->classifyRows(
            [
                $this->row('SKU-1', '2.000', 2),
                $this->row('SKU-1', '4.000', 3),
            ],
            collect([$variant]),
            collect(),
        );

        $this->assertSame(2, $preview['summary']['error']);
        $this->assertSame('error', $preview['rows'][0]['status']);
        $this->assertSame('error', $preview['rows'][1]['status']);
    }

    public function test_all_stock_import_routes_require_tenant_management(): void
    {
        $routes = app('router')->getRoutes();
        $routeNames = [
            'products.stock-imports.create',
            'products.stock-imports.preview',
            'products.stock-imports.store',
            'products.stock-imports.show',
            'products.stock-imports.cancel',
        ];

        foreach ($routeNames as $routeName) {
            $this->assertContains(
                'tenant.management',
                $routes->getByName($routeName)->gatherMiddleware(),
                "The {$routeName} route must require tenant.management.",
            );
        }
    }

    public function test_confirmation_is_claimed_once_and_destination_is_revalidated(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/InventoryStockImportController.php')
        );
        $service = file_get_contents(
            app_path('Modules/Products/Services/InventoryStockImportService.php')
        );

        $this->assertStringContainsString('lockForUpdate()', $controller);
        $this->assertStringContainsString("status !== 'previewed'", $controller);
        $this->assertStringContainsString("->where('branch_id', \$branch->id)", $service);
        $this->assertStringContainsString("->where('is_active', true)", $service);
    }

    public function test_import_replaces_authoritative_quantity_and_records_source(): void
    {
        $service = file_get_contents(
            app_path('Modules/Products/Services/InventoryStockImportService.php')
        );

        $this->assertStringContainsString(
            "'quantity' => \$row['file_quantity']",
            $service,
        );
        $this->assertStringContainsString(
            "'sync_source' => 'excel_import'",
            $service,
        );
        $this->assertStringNotContainsString("increment('quantity'", $service);
        $this->assertStringNotContainsString("decrement('quantity'", $service);
    }

    public function test_migration_is_incremental_and_tenant_scoped(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001314_create_inventory_stock_imports.php'
            )
        );

        $this->assertStringContainsString("Schema::connection('tenant')", $migration);
        $this->assertStringContainsString('inventory_stock_imports', $migration);
        $this->assertStringContainsString('isi_branch_fk', $migration);
        $this->assertStringContainsString('isi_warehouse_fk', $migration);
        $this->assertStringContainsString('nullOnDelete', $migration);
        $this->assertStringNotContainsString('dropIfExists', $migration);
    }

    private function classify(
        string $sku,
        string $currentQuantity,
        string $fileQuantity,
    ): array {
        $variant = $this->variant(10, $sku);
        $stock = new InventoryStock();
        $stock->setRawAttributes([
            'id' => 20,
            'product_variant_id' => 10,
            'quantity' => $currentQuantity,
        ]);

        return app(InventoryStockImportService::class)->classifyRows(
            [$this->row($sku, $fileQuantity)],
            collect([$variant]),
            collect([$stock]),
        );
    }

    private function variant(int $id, string $sku): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setRawAttributes([
            'id' => $id,
            'sku' => $sku,
        ]);

        return $variant;
    }

    private function row(
        string $sku,
        string $quantity,
        int $rowNumber = 2,
    ): array {
        return [
            'row_number' => $rowNumber,
            'sku' => $sku,
            'file_quantity' => $quantity,
            'errors' => [],
        ];
    }
}
