<?php

namespace Tests\Unit;

use App\Modules\Inventory\Exceptions\ContificoApiException;
use App\Modules\Inventory\Services\ContificoClient;
use App\Modules\Inventory\Services\ContificoStockValue;
use App\Modules\Inventory\Services\InventoryAccess;
use App\Modules\Products\Models\Warehouse;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class InventoryManualSyncTest extends TestCase
{
    public function test_contifico_match_requires_exact_sku(): void
    {
        $client = app(ContificoClient::class);
        $match = $client->selectExactProduct([
            ['codigo' => 'ABC-S', 'cantidad_stock' => '4.0'],
            ['codigo' => 'ABC', 'cantidad_stock' => '99.0'],
        ], 'ABC-S');

        $this->assertSame('ABC-S', $match['codigo']);
        $this->assertNull($client->selectExactProduct([], 'ABC-S'));
    }

    public function test_multiple_exact_matches_are_rejected(): void
    {
        $this->expectException(ContificoApiException::class);

        app(ContificoClient::class)->selectExactProduct([
            ['codigo' => 'ABC-S'],
            ['codigo' => 'ABC-S'],
        ], 'ABC-S');
    }

    public function test_warehouse_without_contifico_code_is_rejected(): void
    {
        $this->expectException(ContificoApiException::class);

        app(ContificoClient::class)->findProductBySkuForWarehouse(
            'ABC-S',
            new Warehouse(['name' => 'Bodega principal']),
        );
    }

    public function test_sync_permissions_preserve_operational_roles(): void
    {
        $access = app(InventoryAccess::class);

        $this->assertTrue($access->canSynchronize([
            'role' => TenantOperationalScope::MANAGEMENT,
            'branch_id' => null,
            'is_account_administrator' => false,
        ]));
        $this->assertTrue($access->canSynchronize([
            'role' => TenantOperationalScope::STORE_ADMIN,
            'branch_id' => 7,
            'is_account_administrator' => false,
        ]));
        $this->assertFalse($access->canSynchronize([
            'role' => TenantOperationalScope::ADVISOR,
            'branch_id' => 7,
            'is_account_administrator' => false,
        ]));
        $this->assertTrue($access->canSynchronize([
            'role' => 'admin',
            'branch_id' => null,
            'is_account_administrator' => true,
        ]));
    }

    public function test_stock_value_distinguishes_updates_and_unchanged(): void
    {
        $value = app(ContificoStockValue::class);

        $this->assertSame('0.000', $value->normalize('0'));
        $this->assertSame('updated', $value->result('7.000', '3.000'));
        $this->assertSame('unchanged', $value->result('3.0', '3.000'));
        $this->assertSame('updated', $value->result(null, '0.000'));
    }

    public function test_advisor_is_denied_by_server_side_access_service(): void
    {
        $this->expectException(AuthorizationException::class);

        app(InventoryAccess::class)->authorizeSynchronization([
            'role' => TenantOperationalScope::ADVISOR,
            'branch_id' => 7,
            'is_account_administrator' => false,
        ]);
    }

    public function test_service_replaces_stock_and_processes_every_batch(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Inventory/Services/InventorySynchronizationService.php',
            ),
        );

        $this->assertStringContainsString('chunkById(', $source);
        $this->assertStringContainsString("'quantity' => \$remoteQuantity", $source);
        $this->assertStringContainsString("'sync_source' => 'contifico_sync'", $source);
        $this->assertStringNotContainsString("increment('quantity'", $source);
    }

    public function test_individual_sync_has_cooldown_and_skips_bulk_limits(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Inventory/Services/InventorySynchronizationService.php',
            ),
        );
        $individualStart = strpos($source, 'private function synchronizeIndividual');
        $prepareStart = strpos($source, 'private function prepare');
        $individual = substr($source, $individualStart, $prepareStart - $individualStart);

        $this->assertStringContainsString('PRODUCT_COOLDOWN_SECONDS', $individual);
        $this->assertStringNotContainsString(
            'assertManualBulkSyncAllowed',
            $individual,
        );
    }

    public function test_bulk_uses_one_parent_execution_for_all_warehouses(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Inventory/Services/InventorySynchronizationService.php',
            ),
        );
        $bulkStart = strpos($source, 'public function synchronizeBulk');
        $productStart = strpos($source, 'public function synchronizeProduct');
        $bulk = substr($source, $bulkStart, $productStart - $bulkStart);

        $this->assertSame(1, substr_count($bulk, '$this->createExecution('));
        $this->assertStringContainsString(
            'assertManualBulkSyncAllowed',
            $bulk,
        );
    }

    public function test_technical_errors_do_not_persist_secrets_or_payloads(): void
    {
        $client = file_get_contents(
            app_path('Modules/Inventory/Services/ContificoClient.php'),
        );
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_09_04_001317_create_inventory_sync_details.php',
            ),
        );

        $this->assertStringNotContainsString('->body()', $client);
        $this->assertStringNotContainsString('api_key', $migration);
        $this->assertStringNotContainsString('authorization', strtolower($migration));
        $this->assertStringContainsString('missing_warehouse_code', $client);
    }

    public function test_new_migration_is_incremental_and_sanitized(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_09_04_001317_create_inventory_sync_details.php',
            ),
        );

        $this->assertStringContainsString("Schema::connection('tenant')", $migration);
        $this->assertStringContainsString('inventory_sync_execution_items', $migration);
        $this->assertStringContainsString('inventory_sync_logs', $migration);
        $this->assertStringNotContainsString('api_key', $migration);
        $this->assertStringNotContainsString('dropIfExists', $migration);
    }
}
