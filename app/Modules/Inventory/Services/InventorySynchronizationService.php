<?php

namespace App\Modules\Inventory\Services;

use App\Core\Accounts\Account;
use App\Core\Users\User;
use App\Modules\Inventory\Exceptions\ContificoApiException;
use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Inventory\Models\InventorySyncExecutionItem;
use App\Modules\Inventory\Models\InventorySyncLog;
use App\Modules\Products\Models\InventoryStock;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventorySynchronizationService
{
    private const PRODUCT_COOLDOWN_SECONDS = 60;

    private const ENDPOINT_LABEL = '/api/v1/producto/';

    public function __construct(
        private readonly InventoryAccess $access,
        private readonly InventoryCommercialLimits $limits,
        private readonly ContificoClient $client,
        private readonly ContificoStockValue $stockValue,
    ) {
    }

    public function synchronizeBulk(
        Account $account,
        User $user,
        array $scope,
        ?int $warehouseId,
    ): InventorySyncExecution {
        $warehouses = $this->prepare($account, $scope, $warehouseId);
        $lock = Cache::lock($this->lockKey($account, 'bulk'), 3600);

        if (! $lock->get()) {
            $this->fail('Ya existe una sincronización masiva en curso.');
        }

        try {
            $execution = DB::connection('tenant')->transaction(
                function () use ($account, $user, $scope, $warehouses): InventorySyncExecution {
                    InventorySetting::query()->lockForUpdate()->first();
                    $this->limits->assertManualBulkSyncAllowed($account, $user);

                    return $this->createExecution(
                        $user,
                        $scope,
                        $warehouses,
                        InventorySyncExecution::TYPE_MANUAL_BULK,
                    );
                },
            );

            return $this->run(
                $execution,
                $warehouses,
                ProductVariant::query()
                    ->where('is_active', true)
                    ->whereHas(
                        'product',
                        fn (Builder $query): Builder => $query->where(
                            'is_active',
                            true,
                        ),
                    )
                    ->whereNotNull('sku')
                    ->where('sku', '!=', ''),
            );
        } finally {
            $lock->release();
        }
    }

    public function synchronizeProduct(
        Account $account,
        User $user,
        array $scope,
        Product $product,
        ?int $warehouseId,
    ): InventorySyncExecution {
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if (! (clone $variants)->exists()) {
            $this->fail('El producto no tiene variantes activas con SKU.');
        }

        return $this->synchronizeIndividual(
            $account,
            $user,
            $scope,
            $variants,
            (int) $product->id,
            null,
            $warehouseId,
        );
    }

    public function synchronizeVariant(
        Account $account,
        User $user,
        array $scope,
        ProductVariant $variant,
        ?int $warehouseId,
    ): InventorySyncExecution {
        if (! $variant->is_active || ! filled($variant->sku)) {
            $this->fail('La variante debe estar activa y tener un SKU válido.');
        }

        return $this->synchronizeIndividual(
            $account,
            $user,
            $scope,
            ProductVariant::query()->whereKey($variant->id),
            (int) $variant->product_id,
            (int) $variant->id,
            $warehouseId,
        );
    }

    private function synchronizeIndividual(
        Account $account,
        User $user,
        array $scope,
        Builder $variants,
        int $productId,
        ?int $variantId,
        ?int $warehouseId,
    ): InventorySyncExecution {
        $warehouses = $this->prepare($account, $scope, $warehouseId);
        $lock = Cache::lock(
            $this->lockKey($account, "product:{$productId}"),
            3600,
        );

        if (! $lock->get()) {
            $this->fail('Este producto ya se está sincronizando.');
        }

        try {
            $recent = InventorySyncExecution::query()
                ->where('type', InventorySyncExecution::TYPE_MANUAL_PRODUCT)
                ->where('target_product_id', $productId)
                ->where(function (Builder $query): void {
                    $query
                        ->where(function (Builder $running): void {
                            $running
                                ->where(
                                    'status',
                                    InventorySyncExecution::STATUS_RUNNING,
                                )
                                ->where('started_at', '>', now()->subHours(2));
                        })
                        ->orWhere('created_at', '>', now()->subSeconds(
                            self::PRODUCT_COOLDOWN_SECONDS,
                        ));
                })
                ->exists();

            if ($recent) {
                $this->fail('Espera un minuto antes de volver a sincronizar este producto.');
            }

            $execution = $this->createExecution(
                $user,
                $scope,
                $warehouses,
                InventorySyncExecution::TYPE_MANUAL_PRODUCT,
                $productId,
                $variantId,
            );

            return $this->run($execution, $warehouses, $variants);
        } finally {
            $lock->release();
        }
    }

    private function prepare(
        Account $account,
        array $scope,
        ?int $warehouseId,
    ): Collection {
        $this->access->authorizeSynchronization($scope);

        if (! $account->contifico_enabled) {
            $this->fail('El plan de esta cuenta no habilita Contífico.');
        }

        if (! InventorySetting::current()->contifico_stock_sync_enabled) {
            $this->fail('La sincronización de stock con Contífico está desactivada.');
        }

        $settings = ContificoSetting::current();

        if (! $settings->is_active || ! $settings->api_key) {
            $this->fail('Activa la integración y guarda una API Key antes de sincronizar.');
        }

        $warehouses = $this->access->synchronizedWarehouses($scope, $warehouseId);
        $withoutCode = $warehouses->first(
            fn (Warehouse $warehouse): bool => ! filled($warehouse->contifico_code),
        );

        if ($withoutCode) {
            $this->fail(
                "La bodega {$withoutCode->name} no tiene Código Contífico configurado.",
                'warehouse_id',
            );
        }

        return $warehouses;
    }

    private function createExecution(
        User $user,
        array $scope,
        Collection $warehouses,
        string $type,
        ?int $productId = null,
        ?int $variantId = null,
    ): InventorySyncExecution {
        $singleWarehouse = $warehouses->count() === 1
            ? $warehouses->first()
            : null;

        return InventorySyncExecution::create([
            'requested_by_core_user_id' => $user->id,
            'type' => $type,
            'scope' => $singleWarehouse ? 'warehouse' : 'all_warehouses',
            'branch_id' => $singleWarehouse?->branch_id
                ?? (($scope['branch_id'] ?? null) ?: null),
            'warehouse_id' => $singleWarehouse?->id,
            'status' => InventorySyncExecution::STATUS_RUNNING,
            'target_product_id' => $productId,
            'target_product_variant_id' => $variantId,
            'metadata' => [
                'warehouse_count' => $warehouses->count(),
            ],
            'started_at' => now(),
        ]);
    }

    private function run(
        InventorySyncExecution $execution,
        Collection $warehouses,
        Builder $variants,
    ): InventorySyncExecution {
        $batchSize = max(1, min(500, ContificoSetting::current()->batch_size ?: 100));
        $abort = false;

        try {
            foreach ($warehouses as $warehouse) {
                $seenSkus = [];
                $variantsForWarehouse = clone $variants;

                $variantsForWarehouse
                    ->with('product:id,name')
                    ->orderBy('id')
                    ->chunkById(
                        $batchSize,
                        function (Collection $chunk) use (
                            $execution,
                            $warehouse,
                            &$seenSkus,
                            &$abort,
                        ): bool {
                            foreach ($chunk as $variant) {
                                $sku = trim((string) $variant->sku);

                                if (isset($seenSkus[$sku])) {
                                    continue;
                                }

                                $seenSkus[$sku] = true;
                                $abort = $this->processVariant(
                                    $execution,
                                    $warehouse,
                                    $variant,
                                );

                                if ($abort) {
                                    return false;
                                }
                            }

                            $execution->refresh();

                            return true;
                        },
                    );

                if ($abort) {
                    break;
                }
            }
        } catch (Throwable) {
            $this->recordUnexpectedFailure($execution);
            $abort = true;
        }

        $execution->refresh();
        $execution->update([
            'status' => $abort
                ? InventorySyncExecution::STATUS_FAILED
                : ($execution->failed_count > 0 || $execution->not_found_count > 0
                    ? InventorySyncExecution::STATUS_COMPLETED_WITH_ERRORS
                    : InventorySyncExecution::STATUS_COMPLETED),
            'completed_at' => now(),
        ]);

        return $execution->fresh(['warehouse', 'branch']);
    }

    private function processVariant(
        InventorySyncExecution $execution,
        Warehouse $warehouse,
        ProductVariant $variant,
    ): bool {
        $previous = InventoryStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_variant_id', $variant->id)
            ->first();

        try {
            $remote = $this->client->findProductBySkuForWarehouse(
                $variant->sku,
                $warehouse,
            );

            if ($remote === null) {
                $this->recordResult(
                    $execution,
                    $warehouse,
                    $variant,
                    $previous?->quantity,
                    null,
                    InventorySyncExecutionItem::RESULT_NOT_FOUND,
                    'SKU no encontrado en Contífico para esta bodega.',
                );

                return false;
            }

            $remoteQuantity = $this->stockValue->normalize(
                $remote['cantidad_stock'] ?? null,
            );
            $result = $this->stockValue->result(
                $previous?->quantity,
                $remoteQuantity,
            );

            DB::connection('tenant')->transaction(
                function () use (
                    $execution,
                    $warehouse,
                    $variant,
                    $previous,
                    $remoteQuantity,
                    $result,
                ): void {
                    InventoryStock::query()->updateOrCreate(
                        [
                            'warehouse_id' => $warehouse->id,
                            'product_variant_id' => $variant->id,
                        ],
                        [
                            'quantity' => $remoteQuantity,
                            'sync_source' => 'contifico_sync',
                            'last_synced_at' => now(),
                        ],
                    );

                    $this->recordResult(
                        $execution,
                        $warehouse,
                        $variant,
                        $previous?->quantity,
                        $remoteQuantity,
                        $result,
                    );
                },
            );

            return false;
        } catch (ContificoApiException $exception) {
            $this->recordResult(
                $execution,
                $warehouse,
                $variant,
                $previous?->quantity,
                null,
                InventorySyncExecutionItem::RESULT_ERROR,
                $exception->getMessage(),
            );
            $this->recordLog(
                $execution,
                $warehouse,
                $variant->sku,
                $exception->errorType,
                $exception->getMessage(),
                $exception->httpStatus,
            );

            return $exception->shouldAbortExecution;
        } catch (Throwable) {
            $message = 'No fue posible procesar este SKU.';
            $this->recordResult(
                $execution,
                $warehouse,
                $variant,
                $previous?->quantity,
                null,
                InventorySyncExecutionItem::RESULT_ERROR,
                $message,
            );
            $this->recordLog(
                $execution,
                $warehouse,
                $variant->sku,
                'unexpected_error',
                $message,
            );

            return false;
        }
    }

    private function recordResult(
        InventorySyncExecution $execution,
        Warehouse $warehouse,
        ProductVariant $variant,
        mixed $previousQuantity,
        ?string $remoteQuantity,
        string $result,
        ?string $message = null,
    ): void {
        DB::connection('tenant')->transaction(
            function () use (
                $execution,
                $warehouse,
                $variant,
                $previousQuantity,
                $remoteQuantity,
                $result,
                $message,
            ): void {
                InventorySyncExecutionItem::create([
                    'inventory_sync_execution_id' => $execution->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'previous_quantity' => $previousQuantity,
                    'remote_quantity' => $remoteQuantity,
                    'result' => $result,
                    'message' => $message,
                ]);

                $increments = ['processed_count' => 1];

                if ($result === InventorySyncExecutionItem::RESULT_UPDATED) {
                    $increments['updated_count'] = 1;
                    $increments['succeeded_count'] = 1;
                } elseif ($result === InventorySyncExecutionItem::RESULT_UNCHANGED) {
                    $increments['unchanged_count'] = 1;
                    $increments['succeeded_count'] = 1;
                } elseif ($result === InventorySyncExecutionItem::RESULT_NOT_FOUND) {
                    $increments['not_found_count'] = 1;
                } else {
                    $increments['failed_count'] = 1;
                }

                foreach ($increments as $column => $amount) {
                    InventorySyncExecution::query()
                        ->whereKey($execution->id)
                        ->increment($column, $amount);
                }
            },
        );
    }

    private function recordUnexpectedFailure(
        InventorySyncExecution $execution,
    ): void {
        $this->recordLog(
            $execution,
            null,
            null,
            'execution_error',
            'La ejecución terminó por un error interno controlado.',
        );
    }

    private function recordLog(
        InventorySyncExecution $execution,
        ?Warehouse $warehouse,
        ?string $sku,
        string $errorType,
        string $message,
        ?int $httpStatus = null,
    ): void {
        InventorySyncLog::create([
            'inventory_sync_execution_id' => $execution->id,
            'warehouse_id' => $warehouse?->id,
            'sku' => $sku,
            'endpoint' => self::ENDPOINT_LABEL,
            'http_status' => $httpStatus,
            'error_type' => $errorType,
            'message' => mb_substr($message, 0, 500),
        ]);
    }

    private function lockKey(Account $account, string $operation): string
    {
        return "inventory-sync:{$account->id}:{$operation}";
    }

    private function fail(string $message, string $field = 'sync'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
