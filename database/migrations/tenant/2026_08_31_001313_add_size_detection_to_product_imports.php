<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('product_imports')) {
            return;
        }

        if (! $schema->hasColumn('product_imports', 'detect_size_from_code')) {
            $schema->table('product_imports', function (Blueprint $table): void {
                $table->boolean('detect_size_from_code')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Import configuration is retained to preserve historical import context.
    }
};
