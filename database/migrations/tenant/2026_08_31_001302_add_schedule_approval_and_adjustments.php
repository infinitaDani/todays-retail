<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('schedule_periods')) {
            $schema->create('schedule_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id');
                $table->string('month_key', 7);
                $table->string('status', 30)->default('draft');
                $table->unsignedBigInteger('created_by_core_user_id')->nullable();
                $table->unsignedBigInteger('submitted_by_core_user_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by_core_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_comment')->nullable();
                $table->timestamps();
                $table->unique(['branch_id', 'month_key'], 'sp_branch_month_uq');
                $table->index(['status', 'month_key'], 'sp_status_month_ix');
            });
        }

        if (! $schema->hasTable('schedule_adjustments')) {
            $schema->create('schedule_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('schedule_period_id');
                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('core_user_id');
                $table->date('date');
                $table->unsignedBigInteger('previous_shift_id')->nullable();
                $table->unsignedBigInteger('new_shift_id')->nullable();
                $table->string('reason', 80);
                $table->text('comment')->nullable();
                $table->unsignedBigInteger('tenant_request_id')->nullable();
                $table->unsignedBigInteger('changed_by_core_user_id');
                $table->timestamps();
                $table->index(['schedule_period_id', 'core_user_id', 'date'], 'sa_period_user_date_ix');
                $table->index(['branch_id', 'date'], 'sa_branch_date_ix');
            });
        }
    }

    public function down(): void
    {
        // Historical tenant records are intentionally not removed.
    }
};
