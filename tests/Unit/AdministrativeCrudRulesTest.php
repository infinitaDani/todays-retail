<?php

namespace Tests\Unit;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\StoreKnowledgeArticleRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\Shift;
use App\Modules\Knowledge\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\TestCase;

class AdministrativeCrudRulesTest extends TestCase
{
    public function test_administrative_models_use_tenant_connection(): void
    {
        $this->assertSame('tenant', (new Branch)->getConnectionName());
        $this->assertSame('tenant', (new Shift)->getConnectionName());
        $this->assertSame('tenant', (new KnowledgeArticle)->getConnectionName());
    }

    public function test_shift_relates_to_checklists_through_the_tenant_pivot(): void
    {
        $this->assertInstanceOf(BelongsToMany::class, (new Shift)->checklists());
    }

    public function test_requests_require_the_administrative_fields(): void
    {
        $this->assertContains('required', (new StoreBranchRequest)->rules()['name']);
        $this->assertContains('required', (new StoreShiftRequest)->rules()['start_time']);
        $this->assertContains('required', (new StoreKnowledgeArticleRequest)->rules()['content']);
    }
}
