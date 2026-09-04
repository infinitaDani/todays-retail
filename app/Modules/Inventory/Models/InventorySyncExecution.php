<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\Warehouse;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySyncExecution extends TenantModel
{
    public const TYPE_MANUAL_BULK = 'manual_bulk';

    public const TYPE_AUTOMATIC = 'automatic';

    public const TYPE_PRODUCT = 'product';

    protected $fillable = [
        'requested_by_core_user_id',
        'type',
        'scope',
        'branch_id',
        'warehouse_id',
        'status',
        'processed_count',
        'succeeded_count',
        'failed_count',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
