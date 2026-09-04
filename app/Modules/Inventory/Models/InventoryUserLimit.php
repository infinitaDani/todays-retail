<?php

namespace App\Modules\Inventory\Models;

use App\Modules\TenantModel;

class InventoryUserLimit extends TenantModel
{
    protected $fillable = [
        'core_user_id',
        'manual_bulk_syncs_per_day',
    ];

    protected function casts(): array
    {
        return [
            'core_user_id' => 'integer',
            'manual_bulk_syncs_per_day' => 'integer',
        ];
    }
}
