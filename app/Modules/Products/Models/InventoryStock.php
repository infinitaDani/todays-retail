<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends TenantModel
{
    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'quantity',
        'last_synced_at',
        'sync_source',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'last_synced_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
