<?php

namespace Tests\Unit;

use App\Http\Requests\StoreChecklistRequest;
use App\Modules\Tasks\Models\Checklist;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\TestCase;

class ChecklistEditorRulesTest extends TestCase
{
    public function test_task_and_checklist_models_use_the_tenant_connection(): void
    {
        $this->assertSame('tenant', (new Task)->getConnectionName());
        $this->assertSame('tenant', (new Checklist)->getConnectionName());
    }

    public function test_checklist_request_requires_turns_and_timed_task_items(): void
    {
        $rules = (new StoreChecklistRequest)->rules();

        $this->assertContains('required', $rules['shift_ids']);
        $this->assertContains('required', $rules['items.*.start_time']);
        $this->assertContains('required', $rules['items.*.due_time']);
        $this->assertContains('distinct', $rules['items.*.task_id']);
    }

    public function test_checklist_can_be_related_to_multiple_shifts(): void
    {
        $this->assertInstanceOf(BelongsToMany::class, (new Checklist)->shifts());
    }
}
