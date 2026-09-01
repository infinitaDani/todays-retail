<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\InventoryStock;
use App\Modules\Products\Models\ProductVariant;

class InventoryStockService
{
    public function inWarehouse(ProductVariant $variant, int $warehouseId): float
    {
        return (float) InventoryStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    public function inBranch(ProductVariant $variant, int $branchId): float
    {
        return (float) InventoryStock::query()
            ->where('product_variant_id', $variant->id)
            ->whereHas('warehouse', function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId);
            })
            ->sum('quantity');
    }

    public function total(ProductVariant $variant): float
    {
        return (float) $variant->inventoryStocks()->sum('quantity');
    }
}
