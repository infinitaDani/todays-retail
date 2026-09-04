<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Products\Models\Warehouse;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySyncLog extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'inventory_sync_execution_id',
        'warehouse_id',
        'sku',
        'endpoint',
        'http_status',
        'error_type',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(
            InventorySyncExecution::class,
            'inventory_sync_execution_id',
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
