<?php
namespace App\Modules\Products\Models; use App\Modules\TenantModel;
class ProductSetting extends TenantModel { protected $fillable=['manages_collections','manages_collection_lines']; protected function casts(): array { return ['manages_collections'=>'boolean','manages_collection_lines'=>'boolean']; } }
