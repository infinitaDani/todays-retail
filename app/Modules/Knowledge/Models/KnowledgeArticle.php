<?php
namespace App\Modules\Knowledge\Models;
use App\Modules\TenantModel; use Illuminate\Database\Eloquent\Relations\HasMany;
class KnowledgeArticle extends TenantModel { protected $fillable=['title','content','category','version','status']; public function assignments(): HasMany { return $this->hasMany(KnowledgeAssignment::class,'article_id'); } }
