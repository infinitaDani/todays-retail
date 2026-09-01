<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;

class ScheduleSetting extends TenantModel
{
    protected $fillable = [
        'expected_hours_per_week',
        'standard_hours_per_day',
        'required_days_off_per_week',
        'warn_on_excess_hours',
        'manages_inventory',
        'inventory_by_branch',
    ];

    protected function casts(): array
    {
        return [
            'expected_hours_per_week' => 'decimal:2',
            'standard_hours_per_day' => 'decimal:2',
            'required_days_off_per_week' => 'integer',
            'warn_on_excess_hours' => 'boolean',
            'manages_inventory' => 'boolean',
            'inventory_by_branch' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'expected_hours_per_week' => 40,
            'standard_hours_per_day' => 8,
            'required_days_off_per_week' => 2,
            'warn_on_excess_hours' => true,
            'manages_inventory' => true,
            'inventory_by_branch' => true,
        ];
    }
}
