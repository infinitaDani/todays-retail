<?php

namespace App\Modules\Products\Services;

use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\Warehouse;

class WarehouseProvisioningService
{
    public function ensurePrimaryWarehouse(Branch $branch): Warehouse
    {
        $warehouse = Warehouse::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Bodega principal',
            ],
            [
                'code' => 'MAIN',
                'is_active' => true,
            ],
        );

        if ($warehouse->code !== 'MAIN' || ! $warehouse->is_active) {
            $warehouse->update([
                'code' => 'MAIN',
                'is_active' => true,
            ]);
        }

        return $warehouse;
    }
}
