<?php

namespace App\Modules\Merchandising\Models;

use App\Modules\Operations\Models\Branch;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandisingFloorPlan extends TenantModel
{
    protected $fillable = [
        'branch_id',
        'name',
        'canvas_width',
        'canvas_height',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'canvas_width' => 'integer',
            'canvas_height' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchandisingFloorPlanItem::class, 'floor_plan_id');
    }
}
