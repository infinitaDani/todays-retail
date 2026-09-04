<?php

namespace App\Core\Accounts;

use App\Core\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $connection = 'core';

    protected $fillable = [
        'name',
        'ruc',
        'database_name',
        'status',
        'contifico_enabled',
        'manual_bulk_syncs_per_day',
        'manual_bulk_min_interval_minutes',
    ];

    protected function casts(): array
    {
        return [
            'contifico_enabled' => 'boolean',
            'manual_bulk_syncs_per_day' => 'integer',
            'manual_bulk_min_interval_minutes' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(AccountUser::class)
            ->withPivot(['id', 'role_id'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }
}
