<?php

namespace App\Modules\Inventory\Models;

use App\Modules\TenantModel;

class InventorySetting extends TenantModel
{
		protected $fillable = [
		'manages_warehouses',
		'contifico_stock_sync_enabled',
		'manual_bulk_syncs_per_day',
		'manual_bulk_min_interval_minutes',
	];

    protected function casts(): array
	{
		return [
			'manages_warehouses' => 'boolean',
			'contifico_stock_sync_enabled' => 'boolean',
			'manual_bulk_syncs_per_day' => 'integer',
			'manual_bulk_min_interval_minutes' => 'integer',
		];
	}

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'manages_warehouses' => true,
            'contifico_stock_sync_enabled' => false,
        ]);
    }
}
