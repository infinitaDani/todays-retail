<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffProfile extends TenantModel
{
    protected $fillable = ['core_user_id', 'branch_id', 'first_name', 'last_name', 'birth_date', 'hire_date', 'termination_date', 'phone', 'email', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'status'];

    protected function casts(): array { return ['birth_date' => 'date', 'hire_date' => 'date', 'termination_date' => 'date']; }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function documents(): HasMany { return $this->hasMany(StaffDocument::class); }
}
