<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Products\Models\InventoryStock;

class InventoryDashboard
{
    public function summary(array $scope, InventoryAccess $access): array
    {
        $warehouseQuery = $access->visibleWarehouses($scope);
        $warehouseIds = (clone $warehouseQuery)->pluck('id');

        $recentSyncs = InventorySyncExecution::query()
            ->with(['branch', 'warehouse'])
            ->when(
                ! $access->canViewAllWarehouses($scope),
                fn ($query) => $query->where('branch_id', $scope['branch_id']),
            )
            ->latest()
            ->limit(8)
            ->get();

        return [
            'warehouses' => (clone $warehouseQuery)->count(),
            'active_warehouses' => (clone $warehouseQuery)
                ->where('is_active', true)
                ->count(),
            'stock_records' => InventoryStock::query()
                ->whereIn('warehouse_id', $warehouseIds)
                ->count(),
            'total_quantity' => InventoryStock::query()
                ->whereIn('warehouse_id', $warehouseIds)
                ->sum('quantity'),
            'recent_syncs' => $recentSyncs,
        ];
    }
}
