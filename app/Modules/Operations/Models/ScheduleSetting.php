<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;

class ScheduleSetting extends TenantModel
{
    protected $fillable = ['expected_hours_per_week', 'standard_hours_per_day', 'required_days_off_per_week', 'warn_on_excess_hours'];

    protected function casts(): array
    {
        return ['expected_hours_per_week' => 'decimal:2', 'standard_hours_per_day' => 'decimal:2', 'required_days_off_per_week' => 'integer', 'warn_on_excess_hours' => 'boolean'];
    }
}
