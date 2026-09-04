<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Warehouse;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySyncExecutionItem extends TenantModel
{
    public const RESULT_UPDATED = 'updated';

    public const RESULT_UNCHANGED = 'unchanged';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_ERROR = 'error';

    protected $fillable = [
        'inventory_sync_execution_id',
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'sku',
        'previous_quantity',
        'remote_quantity',
        'result',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'previous_quantity' => 'decimal:3',
            'remote_quantity' => 'decimal:3',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function resultLabel(): string
    {
        return match ($this->result) {
            self::RESULT_UPDATED => 'Actualizado',
            self::RESULT_UNCHANGED => 'Sin cambio',
            self::RESULT_NOT_FOUND => 'No encontrado',
            self::RESULT_ERROR => 'Error',
            default => $this->result,
        };
    }
}
