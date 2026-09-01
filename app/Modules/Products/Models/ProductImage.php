<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends TenantModel
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'product_image_import_id',
        'path',
        'original_filename',
        'content_hash',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(
            ProductImageImport::class,
            'product_image_import_id'
        );
    }
}
