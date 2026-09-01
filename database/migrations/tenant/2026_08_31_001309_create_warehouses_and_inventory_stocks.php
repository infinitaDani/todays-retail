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

        if (! $schema->hasTable('warehouses')) {
            $schema->create('warehouses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('branch_id');
                $table->string('name', 150);
                $table->string('code', 80)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('external_system', 60)->nullable();
                $table->string('external_warehouse_id', 120)->nullable();
                $table->string('external_pos', 120)->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'wh_branch_fk')
                    ->references('id')
                    ->on('branches')
                    ->cascadeOnDelete();
                $table->unique(['branch_id', 'name'], 'wh_branch_name_uq');
            });
        }

        if (! $schema->hasTable('inventory_stocks')) {
            $schema->create('inventory_stocks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('product_variant_id');
                $table->decimal('quantity', 14, 3)->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('sync_source', 60)->nullable();
                $table->timestamps();

                $table->foreign('warehouse_id', 'istock_wh_fk')
                    ->references('id')
                    ->on('warehouses')
                    ->cascadeOnDelete();
                $table->foreign('product_variant_id', 'istock_variant_fk')
                    ->references('id')
                    ->on('product_variants')
                    ->cascadeOnDelete();
                $table->unique(
                    ['warehouse_id', 'product_variant_id'],
                    'istock_wh_variant_uq',
                );
            });
        }

        if ($schema->hasTable('branches') && $schema->hasTable('warehouses')) {
            DB::connection('tenant')
                ->table('branches')
                ->orderBy('id')
                ->each(function ($branch): void {
                    DB::connection('tenant')
                        ->table('warehouses')
                        ->insertOrIgnore([
                            'branch_id' => $branch->id,
                            'name' => 'Bodega principal',
                            'code' => 'MAIN',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    DB::connection('tenant')
                        ->table('warehouses')
                        ->where('branch_id', $branch->id)
                        ->where('name', 'Bodega principal')
                        ->update([
                            'code' => 'MAIN',
                            'is_active' => true,
                        ]);
                });
        }
    }

    public function down(): void
    {
        // Inventory data is intentionally retained.
    }
};
