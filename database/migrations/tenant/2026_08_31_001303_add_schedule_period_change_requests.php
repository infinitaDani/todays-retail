<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('schedule_period_change_requests')) {
            $schema->create('schedule_period_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('schedule_period_id');
                $table->unsignedBigInteger('requested_by_core_user_id');
                $table->text('reason');
                $table->string('status', 30)->default('pending');
                $table->unsignedBigInteger('reviewed_by_core_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_comment')->nullable();
                $table->timestamps();
                $table->index(['schedule_period_id', 'status'], 'spcr_period_status_ix');
            });
        }
    }

    public function down(): void
    {
        // Historical authorization records are intentionally retained.
    }
};
