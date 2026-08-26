<?php
namespace App\Modules\Knowledge\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasOne;
class KnowledgeAssignment extends TenantModel { protected $fillable=['article_id','core_user_id','assigned_at','due_at','required']; protected function casts(): array { return ['assigned_at'=>'datetime','due_at'=>'datetime','required'=>'boolean']; } public function article(): BelongsTo { return $this->belongsTo(KnowledgeArticle::class,'article_id'); } public function tracking(): HasOne { return $this->hasOne(KnowledgeTracking::class,'assignment_id'); } }
