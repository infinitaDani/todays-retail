<?php

namespace Tests\Unit;

use App\Modules\Tasks\Models\TaskExecution;
use App\Modules\Tasks\Support\TaskExecutionStatus;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TaskExecutionSnapshotTest extends TestCase
{
    public function test_status_uses_the_saved_deadline_snapshot(): void
    {
        $execution = new TaskExecution([
            'scheduled_date' => '2026-08-30',
            'scheduled_end' => '10:00:00',
        ]);

        $this->assertSame(TaskExecutionStatus::Overdue, $execution->status(Carbon::parse('2026-08-30 10:01:00')));
    }

    public function test_task_execution_accepts_operational_snapshots(): void
    {
        $execution = new TaskExecution([
            'core_user_id' => 10,
            'branch_id' => 2,
            'scheduled_date' => '2026-08-30',
            'scheduled_start' => '09:00:00',
            'scheduled_end' => '10:00:00',
            'task_name_snapshot' => 'Apertura',
            'checklist_name_snapshot' => 'Checklist de apertura',
        ]);

        $this->assertSame('Apertura', $execution->task_name_snapshot);
        $this->assertSame('Checklist de apertura', $execution->checklist_name_snapshot);
    }
}
