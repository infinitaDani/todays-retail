<?php
namespace App\Modules\Tasks\Models;
use App\Modules\Operations\Models\Assignment; use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class ChecklistExecution extends TenantModel { protected $fillable=['checklist_id','core_user_id','assignment_id','execution_date']; protected function casts(): array { return ['execution_date'=>'date']; } public function checklist(): BelongsTo { return $this->belongsTo(Checklist::class); } public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); } public function taskExecutions(): HasMany { return $this->hasMany(TaskExecution::class); } }
