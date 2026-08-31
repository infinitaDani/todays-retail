<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('shifts')) {
            return;
        }

        DB::connection('tenant')->statement(
            'ALTER TABLE shifts MODIFY start_time TIME NULL'
        );

        DB::connection('tenant')->statement(
            'ALTER TABLE shifts MODIFY end_time TIME NULL'
        );

        $dayOffExists = DB::connection('tenant')
            ->table('shifts')
            ->where('is_day_off', true)
            ->exists();

        if ($dayOffExists) {
            return;
        }

        DB::connection('tenant')
            ->table('shifts')
            ->insert([
                'name' => 'Día libre',
                'start_time' => null,
                'end_time' => null,
                'is_day_off' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No revertimos start_time/end_time a NOT NULL porque los turnos
        // is_day_off utilizan legítimamente valores NULL.
    }
};
