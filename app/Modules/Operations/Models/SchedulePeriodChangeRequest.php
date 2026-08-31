<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;

class SchedulePeriodChangeRequest extends TenantModel
{
    protected $fillable = ['schedule_period_id', 'requested_by_core_user_id', 'reason', 'status', 'reviewed_by_core_user_id', 'reviewed_at', 'review_comment'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
