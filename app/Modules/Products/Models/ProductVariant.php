<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends TenantModel
{
    protected $fillable = [
        'product_id', 'sku', 'auxiliary_code', 'stock', 'minimum_stock',
        'sale_price', 'purchase_price', 'is_for_sale', 'is_for_purchase',
        'is_taxable', 'tax_rate', 'is_inventory_item', 'pvp1', 'pvp1_with_tax',
        'pvp2', 'pvp2_with_tax', 'pvp3', 'pvp3_with_tax', 'distribution_price',
        'distribution_price_with_tax', 'is_active',
        'ice_rate',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'sale_price' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'ice_rate' => 'decimal:4',
            'pvp1' => 'decimal:4',
            'pvp1_with_tax' => 'decimal:4',
            'pvp2' => 'decimal:4',
            'pvp2_with_tax' => 'decimal:4',
            'pvp3' => 'decimal:4',
            'pvp3_with_tax' => 'decimal:4',
            'distribution_price' => 'decimal:4',
            'distribution_price_with_tax' => 'decimal:4',
            'is_for_sale' => 'boolean',
            'is_for_purchase' => 'boolean',
            'is_taxable' => 'boolean',
            'is_inventory_item' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'product_variant_attribute_value',
            'product_variant_id',
            'product_attribute_value_id',
        )->with('attribute');
    }
}
