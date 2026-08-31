<?php

namespace App\Modules\Requests\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantRequest extends TenantModel
{
    public const TYPES = ['vacation', 'permission', 'supply'];

    protected $fillable = [
        'core_user_id', 'type', 'status', 'starts_at', 'ends_at', 'comment',
        'reason', 'modality', 'recovery_hours', 'reviewed_by_core_user_id',
        'reviewed_at', 'review_comment', 'month_key',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'recovery_hours' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenantRequestItem::class);
    }
}
