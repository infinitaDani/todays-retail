<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('product_imports')) {
            return;
        }

        if (! $schema->hasColumn('product_imports', 'warehouse_id')) {
            $schema->table('product_imports', function (Blueprint $table): void {
                $table->unsignedBigInteger('warehouse_id')->nullable();
            });
        }

        $foreignKeyExists = DB::connection('tenant')
            ->table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'product_imports')
            ->where('CONSTRAINT_NAME', 'pimport_wh_fk')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if (! $foreignKeyExists) {
            $schema->table('product_imports', function (Blueprint $table): void {
                $table->foreign('warehouse_id', 'pimport_wh_fk')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Historical imports are intentionally retained.
    }
};
