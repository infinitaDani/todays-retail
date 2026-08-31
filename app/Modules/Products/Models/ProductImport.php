<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;

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
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
        ];
    }
}
