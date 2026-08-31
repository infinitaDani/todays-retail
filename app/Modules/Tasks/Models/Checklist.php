<?php
namespace App\Modules\Tasks\Models;
use App\Modules\Operations\Models\Shift; use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\BelongsToMany; use Illuminate\Database\Eloquent\Relations\HasMany;
class Checklist extends TenantModel { protected $fillable=['name','description','shift_id','status']; public function shift(): BelongsTo { return $this->belongsTo(Shift::class); } public function shifts(): BelongsToMany { return $this->belongsToMany(Shift::class, 'checklist_shift')->withTimestamps(); } public function items(): HasMany { return $this->hasMany(ChecklistItem::class)->orderBy('sort_order'); } public function executions(): HasMany { return $this->hasMany(ChecklistExecution::class); } }
