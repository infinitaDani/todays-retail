<?php

namespace App\Modules\Operations\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDocument extends TenantModel
{
    protected $fillable = ['staff_profile_id', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'issued_at', 'expires_at', 'notes'];
    protected function casts(): array { return ['issued_at' => 'date', 'expires_at' => 'date']; }
    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }
}
