<?php

namespace App\Modules\Products\Models;

use App\Modules\Operations\Models\Branch;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockImport extends TenantModel
{
    protected $fillable = [
        'core_user_id',
        'branch_id',
        'warehouse_id',
        'original_filename',
        'excel_path',
        'status',
        'processed_count',
        'updated_count',
        'unchanged_count',
        'not_found_count',
        'error_count',
        'preview_rows',
        'errors',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'preview_rows' => 'array',
            'errors' => 'array',
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
