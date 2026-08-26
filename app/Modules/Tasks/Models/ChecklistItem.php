<?php
namespace App\Modules\Tasks\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ChecklistItem extends TenantModel { protected $fillable=['checklist_id','task_id','sort_order','start_time','due_time']; public function checklist(): BelongsTo { return $this->belongsTo(Checklist::class); } public function task(): BelongsTo { return $this->belongsTo(Task::class); } }
