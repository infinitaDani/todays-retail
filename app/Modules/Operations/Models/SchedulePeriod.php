<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchedulePeriod extends TenantModel
{
    protected $fillable = ['branch_id', 'month_key', 'status', 'created_by_core_user_id', 'submitted_by_core_user_id', 'submitted_at', 'reviewed_by_core_user_id', 'reviewed_at', 'review_comment'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ScheduleAdjustment::class);
    }
}
