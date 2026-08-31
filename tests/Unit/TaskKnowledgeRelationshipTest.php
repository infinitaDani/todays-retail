<?php
namespace Tests\Unit;
use App\Modules\Tasks\Models\Task; use Illuminate\Database\Eloquent\Relations\BelongsToMany; use PHPUnit\Framework\TestCase;
class TaskKnowledgeRelationshipTest extends TestCase { public function test_tasks_keep_knowledge_as_an_optional_tenant_many_to_many_relation(): void { $task=new Task; $this->assertSame('tenant',$task->getConnectionName()); $this->assertInstanceOf(BelongsToMany::class,$task->knowledgeArticles()); } }
