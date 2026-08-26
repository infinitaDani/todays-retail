<?php
namespace App\Modules\Tasks\Models;
use App\Modules\TenantModel; use App\Modules\Tasks\Support\TaskExecutionStatus; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaskExecution extends TenantModel { protected $fillable=['checklist_execution_id','checklist_item_id','completed_at']; protected function casts(): array { return ['completed_at'=>'datetime']; } public function checklistExecution(): BelongsTo { return $this->belongsTo(ChecklistExecution::class); } public function checklistItem(): BelongsTo { return $this->belongsTo(ChecklistItem::class); } public function status(\DateTimeInterface $now): TaskExecutionStatus { return TaskExecutionStatus::for($this, $now); } }
