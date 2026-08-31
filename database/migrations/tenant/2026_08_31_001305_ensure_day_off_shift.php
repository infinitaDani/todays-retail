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

        $shifts = DB::connection('tenant')->table('shifts');
        if (! $shifts->where('is_day_off', true)->exists()) {
            $shifts->insert([
                'name' => 'Día libre',
                'start_time' => null,
                'end_time' => null,
                'is_day_off' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Never remove a tenant's configured operational shift.
    }
};
