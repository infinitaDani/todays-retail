<?php

namespace App\Modules\Operations\Models;

use App\Modules\Products\Models\Warehouse;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends TenantModel
{
    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
