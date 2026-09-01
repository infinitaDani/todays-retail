<?php

namespace Tests\Unit;

use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\InventoryStock;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class InventoryArchitectureTest extends TestCase
{
    public function test_inventory_models_use_tenant_connection(): void
    {
        $this->assertSame('tenant', (new Warehouse())->getConnectionName());
        $this->assertSame('tenant', (new InventoryStock())->getConnectionName());
    }

    public function test_inventory_relationships_are_defined(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Branch())->warehouses());
        $this->assertInstanceOf(BelongsTo::class, (new Warehouse())->branch());
        $this->assertInstanceOf(HasMany::class, (new Warehouse())->stocks());
        $this->assertInstanceOf(BelongsTo::class, (new InventoryStock())->warehouse());
        $this->assertInstanceOf(BelongsTo::class, (new InventoryStock())->variant());
        $this->assertInstanceOf(HasMany::class, (new ProductVariant())->inventoryStocks());
    }

    public function test_inventory_migration_has_short_unique_index(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001309_create_warehouses_and_inventory_stocks.php',
            ),
        );

        $this->assertStringContainsString('istock_wh_variant_uq', $migration);
        $this->assertStringContainsString("'warehouse_id', 'product_variant_id'", $migration);
    }

    public function test_import_warehouse_foreign_key_preserves_history(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001310_add_warehouse_to_product_imports.php',
            ),
        );

        $this->assertStringContainsString('pimport_wh_fk', $migration);
        $this->assertStringContainsString('nullOnDelete', $migration);
    }

    public function test_category_and_collection_reads_are_operational_but_writes_are_management_only(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains(
            'tenant.operational',
            $routes->getByName('products.categories')->gatherMiddleware(),
        );
        $this->assertContains(
            'tenant.operational',
            $routes->getByName('products.collections')->gatherMiddleware(),
        );
        $this->assertContains(
            'tenant.management',
            $routes->getByName('products.categories.store')->gatherMiddleware(),
        );
        $this->assertContains(
            'tenant.management',
            $routes->getByName('products.collections.update')->gatherMiddleware(),
        );
        $this->assertContains(
            'tenant.management',
            $routes->getByName('products.collections.lines.store')->gatherMiddleware(),
        );
    }
}
