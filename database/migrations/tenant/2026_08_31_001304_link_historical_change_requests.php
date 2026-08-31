<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('schedule_period_change_requests') && ! $schema->hasColumn('schedule_period_change_requests', 'requested_at')) {
            $schema->table('schedule_period_change_requests', function (Blueprint $table) {
                $table->timestamp('requested_at')->nullable();
            });
        }

        if ($schema->hasTable('schedule_adjustments') && ! $schema->hasColumn('schedule_adjustments', 'schedule_period_change_request_id')) {
            $schema->table('schedule_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('schedule_period_change_request_id')->nullable();
                $table->index('schedule_period_change_request_id', 'sa_change_request_ix');
            });
        }
    }

    public function down(): void
    {
        // Audit data is intentionally retained.
    }
};
