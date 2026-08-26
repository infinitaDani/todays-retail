<?php
namespace App\Modules\Knowledge\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class KnowledgeTracking extends TenantModel { protected $fillable=['assignment_id','opened_at','completed_at','confirmed_at']; protected function casts(): array { return ['opened_at'=>'datetime','completed_at'=>'datetime','confirmed_at'=>'datetime']; } public function assignment(): BelongsTo { return $this->belongsTo(KnowledgeAssignment::class,'assignment_id'); } }
