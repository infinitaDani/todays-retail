<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImport extends TenantModel
{
    protected $fillable = [
        'core_user_id',
        'status',
        'excel_path',
        'original_filename',
        'processed_count',
        'created_count',
        'updated_count',
        'existing_count',
        'warning_count',
        'error_count',
        'total_count',
        'errors',
        'warehouse_id',
        'detect_size_from_code',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'detect_size_from_code' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
