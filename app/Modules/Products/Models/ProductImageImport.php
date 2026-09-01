<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImageImport extends TenantModel
{
    protected $fillable = [
        'core_user_id',
        'status',
        'zip_path',
        'temporary_directory',
        'original_filename',
        'total_count',
        'matched_count',
        'unmatched_count',
        'ambiguous_count',
        'duplicate_count',
        'invalid_count',
        'imported_count',
        'failed_count',
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

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
