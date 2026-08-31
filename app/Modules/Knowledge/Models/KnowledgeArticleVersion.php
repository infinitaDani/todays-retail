<?php

namespace App\Modules\Knowledge\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeArticleVersion extends TenantModel
{
    protected $fillable = ['article_id', 'version_number', 'title', 'content', 'author_core_user_id', 'status', 'requires_confirmation', 'audience', 'published_at'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'requires_confirmation' => 'boolean', 'published_at' => 'datetime'];
    }

    public function article(): BelongsTo { return $this->belongsTo(KnowledgeArticle::class, 'article_id'); }
    public function readings(): HasMany { return $this->hasMany(KnowledgeVersionReading::class, 'knowledge_article_version_id'); }
}
