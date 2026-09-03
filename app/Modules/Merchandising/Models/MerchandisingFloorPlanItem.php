<?php

namespace App\Modules\Merchandising\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandisingFloorPlanItem extends TenantModel
{
    protected $fillable = [
        'floor_plan_id',
        'fixture_type_id',
        'parent_item_id',
        'label',
        'position_x',
        'position_y',
        'width',
        'height',
        'rotation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parent_item_id' => 'integer',
            'position_x' => 'decimal:3',
            'position_y' => 'decimal:3',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
            'rotation' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(MerchandisingFloorPlan::class, 'floor_plan_id');
    }

    public function fixtureType(): BelongsTo
    {
        return $this->belongsTo(MerchandisingFixtureType::class, 'fixture_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }
}
