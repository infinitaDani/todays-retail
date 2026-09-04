<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\Warehouse;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySyncExecution extends TenantModel
{
    public const TYPE_MANUAL_BULK = 'manual_bulk';

    public const TYPE_AUTOMATIC = 'automatic';

    public const TYPE_MANUAL_PRODUCT = 'manual_product';

    public const TYPE_PRODUCT = 'product';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'requested_by_core_user_id',
        'type',
        'scope',
        'branch_id',
        'warehouse_id',
        'status',
        'processed_count',
        'succeeded_count',
        'updated_count',
        'unchanged_count',
        'not_found_count',
        'failed_count',
        'target_product_id',
        'target_product_variant_id',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processed_count' => 'integer',
            'succeeded_count' => 'integer',
            'updated_count' => 'integer',
            'unchanged_count' => 'integer',
            'not_found_count' => 'integer',
            'failed_count' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(
            InventorySyncExecutionItem::class,
            'inventory_sync_execution_id',
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            InventorySyncLog::class,
            'inventory_sync_execution_id',
        );
    }

    public function durationInSeconds(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_MANUAL_BULK => 'Masiva manual',
            self::TYPE_MANUAL_PRODUCT => 'Producto manual',
            self::TYPE_PRODUCT => 'Producto (legado)',
            self::TYPE_AUTOMATIC => 'Automática',
            default => $this->type,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_RUNNING => 'En ejecución',
            self::STATUS_COMPLETED => 'Completada',
            self::STATUS_COMPLETED_WITH_ERRORS => 'Completada con novedades',
            self::STATUS_FAILED => 'Fallida',
            default => $this->status,
        };
    }
}
