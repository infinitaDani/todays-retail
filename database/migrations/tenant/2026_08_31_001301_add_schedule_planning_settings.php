<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shifts') && ! $schema->hasColumn('shifts', 'is_day_off')) {
            $schema->table('shifts', function (Blueprint $table) {
                $table->boolean('is_day_off')->default(false);
            });
        }

        if (! $schema->hasTable('schedule_settings')) {
            $schema->create('schedule_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('expected_hours_per_week', 6, 2)->default(40);
                $table->decimal('standard_hours_per_day', 6, 2)->default(8);
                $table->unsignedTinyInteger('required_days_off_per_week')->default(2);
                $table->boolean('warn_on_excess_hours')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive for tenant production data.
    }
};
