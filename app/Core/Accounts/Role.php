<?php

namespace App\Core\Accounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }
}
