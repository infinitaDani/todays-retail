<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;

class ProductSetting extends TenantModel
{
    protected $fillable = [
        'manages_collections',
        'manages_collection_lines',
        'manages_taxes',
        'tax_percent',
        'vat_percent',
        'ice_percent',
        'manages_multiple_prices',
        'manages_distribution_price',
    ];

    protected function casts(): array
    {
        return [
            'manages_collections' => 'boolean',
            'manages_collection_lines' => 'boolean',
            'manages_taxes' => 'boolean',
            'tax_percent' => 'decimal:4',
            'vat_percent' => 'decimal:4',
            'ice_percent' => 'decimal:4',
            'manages_multiple_prices' => 'boolean',
            'manages_distribution_price' => 'boolean',
        ];
    }
}
