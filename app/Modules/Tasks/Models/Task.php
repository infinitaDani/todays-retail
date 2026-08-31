<?php
namespace App\Modules\Tasks\Models;
use App\Modules\Knowledge\Models\KnowledgeArticle; use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\{BelongsToMany,HasMany};
class Task extends TenantModel { protected $fillable=['name','description','status']; public function checklistItems(): HasMany { return $this->hasMany(ChecklistItem::class); } public function knowledgeArticles(): BelongsToMany { return $this->belongsToMany(KnowledgeArticle::class,'knowledge_article_task','task_id','knowledge_article_id'); } }
