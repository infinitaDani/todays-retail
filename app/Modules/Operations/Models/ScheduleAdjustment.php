<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;

class ScheduleAdjustment extends TenantModel
{
    protected $fillable = ['schedule_period_id', 'schedule_period_change_request_id', 'branch_id', 'core_user_id', 'date', 'previous_shift_id', 'new_shift_id', 'reason', 'comment', 'tenant_request_id', 'changed_by_core_user_id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
