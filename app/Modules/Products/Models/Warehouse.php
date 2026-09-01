<?php

namespace App\Modules\Products\Models;

use App\Modules\Operations\Models\Branch;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends TenantModel
{
    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'is_active',
        'external_system',
        'external_warehouse_id',
        'external_pos',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
