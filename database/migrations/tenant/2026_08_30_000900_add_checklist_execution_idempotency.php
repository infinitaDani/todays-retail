<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::connection('tenant')->table('checklist_executions')
            ->select('checklist_id', 'core_user_id', 'assignment_id', 'execution_date', DB::raw('COUNT(*) as total'))
            ->whereNotNull('assignment_id')
            ->groupBy('checklist_id', 'core_user_id', 'assignment_id', 'execution_date')
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $conflicts = $duplicates->map(fn (object $row) => "checklist_id={$row->checklist_id}, core_user_id={$row->core_user_id}, assignment_id={$row->assignment_id}, date={$row->execution_date}, executions={$row->total}")->implode('; ');
            throw new RuntimeException("No se puede crear la protección de idempotencia de checklist executions. Corrige manualmente estos duplicados: {$conflicts}");
        }

        Schema::connection('tenant')->table('checklist_executions', function (Blueprint $table) {
            $table->unique(['checklist_id', 'core_user_id', 'assignment_id', 'execution_date'], 'checklist_execution_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('checklist_executions', function (Blueprint $table) {
            $table->dropUnique('checklist_execution_assignment_unique');
        });
    }
};
