<?php
namespace App\Modules\Products\Models; use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\HasMany;
class ProductAttribute extends TenantModel { protected $fillable=['code','name','is_enabled','sort_order']; protected function casts(): array { return ['is_enabled'=>'boolean']; } public function values(): HasMany { return $this->hasMany(ProductAttributeValue::class); } }
