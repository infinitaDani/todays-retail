<?php

namespace App\Modules\Knowledge\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeCategory extends TenantModel
{
    protected $fillable = ['name', 'slug', 'description', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeArticle::class, 'knowledge_article_category', 'category_id', 'article_id');
    }
}
