<?php

namespace App\Modules\Knowledge\Models;

use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeVersionReading extends TenantModel
{
    protected $fillable = ['knowledge_article_version_id', 'core_user_id', 'first_opened_at', 'last_opened_at', 'last_heartbeat_at', 'active_seconds', 'confirmed_at'];

    protected function casts(): array
    {
        return ['first_opened_at' => 'datetime', 'last_opened_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function version(): BelongsTo { return $this->belongsTo(KnowledgeArticleVersion::class, 'knowledge_article_version_id'); }
}
