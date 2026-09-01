<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('schedule_settings')) {
            return;
        }

        if (! $schema->hasColumn('schedule_settings', 'manages_inventory')) {
            $schema->table('schedule_settings', function (Blueprint $table): void {
                $table->boolean('manages_inventory')->default(true);
            });
        }

        if (! $schema->hasColumn('schedule_settings', 'inventory_by_branch')) {
            $schema->table('schedule_settings', function (Blueprint $table): void {
                $table->boolean('inventory_by_branch')->default(true);
            });
        }
    }

    public function down(): void
    {
        // Tenant settings are intentionally retained.
    }
};
