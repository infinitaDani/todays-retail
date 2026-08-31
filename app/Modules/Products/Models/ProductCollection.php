<?php
namespace App\Modules\Products\Models; use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\HasMany;
class ProductCollection extends TenantModel { protected $table='product_collections'; protected $fillable=['name','normalized_name','reference','description','is_active']; protected function casts(): array { return ['is_active'=>'boolean']; } public function lines(): HasMany { return $this->hasMany(ProductCollectionLine::class); } public function products(): HasMany { return $this->hasMany(Product::class); } }
