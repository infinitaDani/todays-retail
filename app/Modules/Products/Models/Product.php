<?php

namespace App\Modules\Products\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends TenantModel
{
    protected $fillable = [
        'catalog_code', 'name', 'description', 'category_id',
        'product_collection_id', 'product_collection_line_id', 'product_type_id',
        'usage_period', 'usage_period_unit', 'unit', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'product_collection_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ProductCollectionLine::class, 'product_collection_line_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
	{
		return $this->hasMany(ProductImage::class)
			->orderBy('sort_order');
	}

	public function generalImages(): HasMany
	{
		return $this->images()
			->whereNull('product_variant_id');
	}

	public function primaryImage(): ?ProductImage
	{
		return $this->generalImages()
			->where('is_primary', true)
			->first();
	}

	protected static function booted(): void
	{
		static::deleting(function (Product $product): void {
			$product->images()
				->get()
				->each(function (ProductImage $image): void {
					Storage::disk('local')->delete($image->path);
				});
		});
	}
}
