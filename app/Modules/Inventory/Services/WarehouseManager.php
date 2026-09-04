<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Products\Models\InventoryStockImport;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseManager
{
    public function create(array $data): Warehouse
    {
        $this->assertManagementEnabled();

        return DB::connection('tenant')->transaction(
            fn (): Warehouse => Warehouse::create($this->values($data)),
        );
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $this->assertManagementEnabled();

        return DB::connection('tenant')->transaction(
            function () use ($warehouse, $data): Warehouse {
                $warehouse->update($this->values($data));

                return $warehouse->refresh();
            },
        );
    }

    public function toggle(Warehouse $warehouse): Warehouse
    {
        $this->assertManagementEnabled();

        $warehouse->update([
            'is_active' => ! $warehouse->is_active,
        ]);

        return $warehouse->refresh();
    }

    public function delete(Warehouse $warehouse): void
    {
        $this->assertManagementEnabled();

        if (! $this->canDelete($warehouse)) {
            throw ValidationException::withMessages([
                'warehouse' => 'No se puede eliminar la bodega porque tiene inventario o historial asociado.',
            ]);
        }

        $warehouse->delete();
    }

    public function canDelete(Warehouse $warehouse): bool
    {
        return ! $warehouse->stocks()->exists()
            && ! InventoryStockImport::query()
                ->where('warehouse_id', $warehouse->id)
                ->exists()
            && ! InventorySyncExecution::query()
                ->where('warehouse_id', $warehouse->id)
                ->exists();
    }

    private function values(array $data): array
    {
        return [
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'purposes' => ! empty($data['purposes'])
				? array_values($data['purposes'])
				: null,
            'contifico_code' => ($data['contifico_code'] ?? null) ?: null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    public function assertManagementEnabled(): void
    {
        if (! InventorySetting::current()->manages_warehouses) {
            throw ValidationException::withMessages([
                'warehouse' => 'La gestión de bodegas está desactivada en Configuración.',
            ]);
        }
    }
}
