<?php

namespace App\Modules\Merchandising\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandisingFixtureType extends TenantModel
{
    public const CATEGORY_STRUCTURE = 'structure';

    public const CATEGORY_ACCESSORY = 'accessory';

    protected $fillable = [
        'code',
        'name',
        'normalized_name',
        'category',
        'icon_path',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function floorPlanItems(): HasMany
    {
        return $this->hasMany(MerchandisingFloorPlanItem::class, 'fixture_type_id');
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path
            ? asset($this->icon_path)
            : null;
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_STRUCTURE => 'Estructura',
            self::CATEGORY_ACCESSORY => 'Accesorio',
            default => str($this->category)->headline()->toString(),
        };
    }
}
