<?php
namespace App\Modules\Knowledge\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeArticle extends TenantModel
{
    // Legacy title/content/category/version remain populated to preserve prior integrations.
    protected $fillable = ['title', 'content', 'category', 'version', 'status'];

    public function assignments(): HasMany { return $this->hasMany(KnowledgeAssignment::class, 'article_id'); }
    public function versions(): HasMany { return $this->hasMany(KnowledgeArticleVersion::class, 'article_id'); }
    public function categories(): BelongsToMany { return $this->belongsToMany(KnowledgeCategory::class, 'knowledge_article_category', 'article_id', 'category_id'); }
}
