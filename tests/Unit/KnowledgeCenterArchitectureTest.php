<?php

namespace Tests\Unit;

use App\Events\KnowledgeArticlePublished;
use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Modules\Knowledge\Models\KnowledgeArticle;
use App\Modules\Knowledge\Models\KnowledgeArticleVersion;
use App\Modules\Knowledge\Models\KnowledgeCategory;
use App\Modules\Knowledge\Models\KnowledgeVersionReading;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\TestCase;

class KnowledgeCenterArchitectureTest extends TestCase
{
    public function test_knowledge_models_use_the_tenant_connection_and_version_relationships(): void
    {
        $this->assertSame('tenant', (new KnowledgeCategory)->getConnectionName());
        $this->assertSame('tenant', (new KnowledgeArticleVersion)->getConnectionName());
        $this->assertSame('tenant', (new KnowledgeVersionReading)->getConnectionName());
        $this->assertInstanceOf(BelongsToMany::class, (new KnowledgeArticle)->categories());
        $this->assertInstanceOf(HasMany::class, (new KnowledgeArticleVersion)->readings());
    }

    public function test_article_request_only_accepts_operational_audiences_and_category_ids(): void
    {
        $rules = (new StoreKnowledgeArticleRequest)->rules();
        $this->assertArrayHasKey('category_ids', $rules);
        $this->assertArrayHasKey('audience.*', $rules);
        $this->assertArrayHasKey('requires_confirmation', $rules);
    }

    public function test_published_event_carries_a_version_not_an_operational_role(): void
    {
        $event = new KnowledgeArticlePublished(new KnowledgeArticleVersion);
        $this->assertInstanceOf(KnowledgeArticleVersion::class, $event->version);
    }

    public function test_version_readings_support_server_controlled_heartbeat_metadata(): void
    {
        $this->assertContains('last_heartbeat_at', (new KnowledgeVersionReading)->getFillable());
        $this->assertArrayHasKey('last_heartbeat_at', (new KnowledgeVersionReading)->getCasts());
    }
}
