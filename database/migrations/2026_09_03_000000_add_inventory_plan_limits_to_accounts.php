<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('core');

        if (! $schema->hasTable('accounts')) {
            return;
        }

        if (! $schema->hasColumn('accounts', 'contifico_enabled')) {
            $schema->table('accounts', function (Blueprint $table): void {
                $table->boolean('contifico_enabled')->default(false);
            });
        }

        if (! $schema->hasColumn('accounts', 'manual_bulk_syncs_per_day')) {
            $schema->table('accounts', function (Blueprint $table): void {
                $table->unsignedInteger('manual_bulk_syncs_per_day')->nullable();
            });
        }

        if (! $schema->hasColumn('accounts', 'manual_bulk_min_interval_minutes')) {
            $schema->table('accounts', function (Blueprint $table): void {
                $table->unsignedInteger('manual_bulk_min_interval_minutes')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Commercial entitlements are retained to avoid destructive rollback.
    }
};
