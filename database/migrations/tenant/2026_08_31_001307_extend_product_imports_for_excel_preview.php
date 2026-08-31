<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('products') && ! $schema->hasColumn('products', 'unit')) {
            $schema->table('products', function (Blueprint $table) {
                $table->string('unit', 60)->nullable();
            });
        }

        if ($schema->hasTable('product_variants') && ! $schema->hasColumn('product_variants', 'ice_rate')) {
            $schema->table('product_variants', function (Blueprint $table) {
                $table->decimal('ice_rate', 8, 4)->nullable();
            });
        }

        if (! $schema->hasTable('product_imports')) {
            return;
        }

        $columns = [
            'original_filename' => fn (Blueprint $table) => $table->string('original_filename')->nullable(),
            'total_count' => fn (Blueprint $table) => $table->unsignedInteger('total_count')->default(0),
            'existing_count' => fn (Blueprint $table) => $table->unsignedInteger('existing_count')->default(0),
            'warning_count' => fn (Blueprint $table) => $table->unsignedInteger('warning_count')->default(0),
        ];

        foreach ($columns as $column => $definition) {
            if (! $schema->hasColumn('product_imports', $column)) {
                $schema->table('product_imports', $definition);
            }
        }
    }

    public function down(): void
    {
        // Historical import records and product data are intentionally retained.
    }
};
