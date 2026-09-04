<?php

namespace Tests\Unit;

use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Inventory\Models\InventorySyncExecutionItem;
use App\Modules\Inventory\Models\InventorySyncLog;
use App\Modules\Inventory\Models\InventoryUserLimit;
use App\Modules\Inventory\Services\InventoryAccess;
use App\Modules\Products\Models\Warehouse;
use App\Tenancy\TenantOperationalScope;
use Tests\TestCase;

class InventoryAdministrationTest extends TestCase
{
    public function test_inventory_administration_models_are_tenant_scoped(): void
    {
        $models = [
            new InventorySetting(),
            new ContificoSetting(),
            new InventoryUserLimit(),
            new InventorySyncExecution(),
            new InventorySyncExecutionItem(),
            new InventorySyncLog(),
        ];

        foreach ($models as $model) {
            $this->assertSame('tenant', $model->getConnectionName());
        }
    }

    public function test_warehouse_exposes_contifico_configuration_fields(): void
    {
        $warehouse = new Warehouse();

        $this->assertContains('purposes', $warehouse->getFillable());
        $this->assertContains('contifico_code', $warehouse->getFillable());
    }

    public function test_store_admin_and_advisor_only_see_their_branch(): void
    {
        $access = app(InventoryAccess::class);

        foreach (
            [
                TenantOperationalScope::STORE_ADMIN,
                TenantOperationalScope::ADVISOR,
            ] as $role
        ) {
            $scope = [
                'role' => $role,
                'branch_id' => 42,
                'is_account_administrator' => false,
            ];

            $this->assertFalse($access->canViewAllWarehouses($scope));
            $warehouseQuery = $access->visibleWarehouses($scope);
            $this->assertSame(
                'branch_id',
                $warehouseQuery->getQuery()->wheres[0]['column'],
            );
            $this->assertSame(
                42,
                $warehouseQuery->getQuery()->wheres[0]['value'],
            );
        }
    }

    public function test_management_reads_all_warehouses_but_cannot_mutate_them(): void
    {
        $access = app(InventoryAccess::class);
        $scope = [
            'role' => TenantOperationalScope::MANAGEMENT,
            'branch_id' => null,
            'is_account_administrator' => false,
        ];

        $this->assertTrue($access->canViewAllWarehouses($scope));
        $this->assertFalse($access->isAccountAdministrator($scope));

        $routes = app('router')->getRoutes();
        $this->assertContains(
            'tenant.operational',
            $routes->getByName('inventory.warehouses.index')->gatherMiddleware(),
        );
        $this->assertContains(
            'tenant.account-admin',
            $routes->getByName('inventory.warehouses.store')->gatherMiddleware(),
        );
    }

    public function test_inventory_permissions_are_enforced_by_route_middleware(): void
    {
        $routes = app('router')->getRoutes();

        foreach (
            [
                'inventory.dashboard',
                'inventory.warehouses.index',
                'inventory.warehouses.show',
            ] as $routeName
        ) {
            $this->assertContains(
                'tenant.operational',
                $routes->getByName($routeName)->gatherMiddleware(),
            );
        }

        foreach (
            [
                'inventory.warehouses.create',
                'inventory.warehouses.store',
                'inventory.warehouses.edit',
                'inventory.warehouses.update',
                'inventory.warehouses.status',
                'inventory.warehouses.destroy',
                'inventory.settings.edit',
                'inventory.settings.update',
                'inventory.settings.test',
            ] as $routeName
        ) {
            $this->assertContains(
                'tenant.account-admin',
                $routes->getByName($routeName)->gatherMiddleware(),
            );
        }

        $this->assertContains(
            'tenant.operational',
            $routes->getByName('inventory.history')->gatherMiddleware(),
        );
    }

    public function test_contifico_key_is_encrypted_hidden_and_never_logged(): void
    {
        $model = new ContificoSetting();

        $this->assertSame('encrypted', $model->getCasts()['api_key']);
        $this->assertContains('api_key', $model->getHidden());

        $client = file_get_contents(
            app_path('Modules/Inventory/Services/ContificoClient.php'),
        );
        $request = file_get_contents(
            app_path('Http/Requests/UpdateInventoryConfigurationRequest.php'),
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/InventoryConfigurationController.php'),
        );

        $this->assertStringContainsString("'Authorization'", $client);
        $this->assertStringContainsString('/api/v1/producto/', $client);
        $this->assertStringNotContainsString('Log::', $client);
        $this->assertStringNotContainsString('->body()', $client);
        $this->assertStringContainsString("remove('api_key')", $request);
        $this->assertStringContainsString("remove('api_key')", $controller);
    }

    public function test_migrations_are_incremental_and_use_short_index_names(): void
    {
        $tenantMigration = file_get_contents(
            database_path(
                'migrations/tenant/2026_09_03_001316_create_inventory_administration.php',
            ),
        );
        $coreMigration = file_get_contents(
            database_path(
                'migrations/2026_09_03_000000_add_inventory_plan_limits_to_accounts.php',
            ),
        );

        $this->assertStringContainsString("Schema::connection('tenant')", $tenantMigration);
        $this->assertStringContainsString('wh_contifico_code_uq', $tenantMigration);
        $this->assertStringContainsString('ise_user_created_ix', $tenantMigration);
        $this->assertStringContainsString("Schema::connection('core')", $coreMigration);
        $this->assertStringNotContainsString('migrate:fresh', $tenantMigration);
        $this->assertStringNotContainsString('dropIfExists', $tenantMigration);
    }

    public function test_real_contifico_sync_routes_are_operationally_protected(): void
    {
        $routes = app('router')->getRoutes();

        foreach (
            [
                'inventory.contifico',
                'inventory.sync.bulk',
                'inventory.sync.product',
                'inventory.sync.variant',
                'inventory.sync.sku',
                'inventory.sync-executions.show',
            ] as $routeName
        ) {
            $this->assertContains(
                'tenant.operational',
                $routes->getByName($routeName)->gatherMiddleware(),
            );
        }
    }
}
