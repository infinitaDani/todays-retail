<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('inventory_stock_imports')) {
            return;
        }

        $schema->create('inventory_stock_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('core_user_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('original_filename', 255);
            $table->string('excel_path', 500)->nullable();
            $table->string('status', 30)->default('previewed');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('not_found_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('preview_rows')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('branch_id', 'isi_branch_fk')
                ->references('id')
                ->on('branches')
                ->nullOnDelete();
            $table->foreign('warehouse_id', 'isi_warehouse_fk')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();
            $table->index('core_user_id', 'isi_user_ix');
            $table->index(['status', 'created_at'], 'isi_status_created_ix');
        });
    }

    public function down(): void
    {
        // Stock import history is intentionally retained.
    }
};
