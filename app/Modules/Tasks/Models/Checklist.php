<?php
namespace App\Modules\Tasks\Models;
use App\Modules\Operations\Models\Shift; use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class Checklist extends TenantModel { protected $fillable=['name','shift_id','status']; public function shift(): BelongsTo { return $this->belongsTo(Shift::class); } public function items(): HasMany { return $this->hasMany(ChecklistItem::class)->orderBy('sort_order'); } public function executions(): HasMany { return $this->hasMany(ChecklistExecution::class); } }
