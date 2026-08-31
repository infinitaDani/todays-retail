<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('staff_profiles') && ! $schema->hasColumn('staff_profiles', 'can_work_other_branches')) {
            $schema->table('staff_profiles', function (Blueprint $table) {
                $table->boolean('can_work_other_branches')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Tenant personnel authorization is intentionally retained.
    }
};
