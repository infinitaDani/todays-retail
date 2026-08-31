<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('task_executions', function (Blueprint $table) {
            $table->unsignedBigInteger('core_user_id')->nullable()->index()->after('checklist_item_id');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete()->after('core_user_id');
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete()->after('branch_id');
            $table->date('scheduled_date')->nullable()->index()->after('assignment_id');
            $table->time('scheduled_start')->nullable()->after('scheduled_date');
            $table->time('scheduled_end')->nullable()->after('scheduled_start');
            $table->string('task_name_snapshot', 150)->nullable()->after('scheduled_end');
            $table->string('checklist_name_snapshot', 150)->nullable()->after('task_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('task_executions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropColumn(['core_user_id', 'scheduled_date', 'scheduled_start', 'scheduled_end', 'task_name_snapshot', 'checklist_name_snapshot']);
        });
    }
};
