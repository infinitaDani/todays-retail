<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends TenantModel
{
    protected $fillable = ['core_user_id', 'branch_id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
