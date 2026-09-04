<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('inventory_sync_executions')) {
            $this->addExecutionColumns($schema);
        }

        if (! $schema->hasTable('inventory_sync_execution_items')) {
            $schema->create(
                'inventory_sync_execution_items',
                function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('inventory_sync_execution_id');
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                    $table->unsignedBigInteger('product_id')->nullable();
                    $table->unsignedBigInteger('product_variant_id')->nullable();
                    $table->string('sku', 150);
                    $table->decimal('previous_quantity', 14, 3)->nullable();
                    $table->decimal('remote_quantity', 14, 3)->nullable();
                    $table->string('result', 30);
                    $table->string('message', 500)->nullable();
                    $table->timestamps();

                    $table->foreign(
                        'inventory_sync_execution_id',
                        'isei_execution_fk',
                    )
                        ->references('id')
                        ->on('inventory_sync_executions')
                        ->cascadeOnDelete();
                    $table->foreign('warehouse_id', 'isei_warehouse_fk')
                        ->references('id')
                        ->on('warehouses')
                        ->nullOnDelete();
                    $table->foreign('product_id', 'isei_product_fk')
                        ->references('id')
                        ->on('products')
                        ->nullOnDelete();
                    $table->foreign('product_variant_id', 'isei_variant_fk')
                        ->references('id')
                        ->on('product_variants')
                        ->nullOnDelete();
                    $table->index(
                        ['inventory_sync_execution_id', 'result'],
                        'isei_exec_result_ix',
                    );
                    $table->index(
                        ['warehouse_id', 'sku'],
                        'isei_wh_sku_ix',
                    );
                    $table->unique(
                        [
                            'inventory_sync_execution_id',
                            'warehouse_id',
                            'product_variant_id',
                        ],
                        'isei_exec_wh_variant_uq',
                    );
                },
            );
        }

        if (! $schema->hasTable('inventory_sync_logs')) {
            $schema->create(
                'inventory_sync_logs',
                function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('inventory_sync_execution_id');
                    $table->unsignedBigInteger('warehouse_id')->nullable();
                    $table->string('sku', 150)->nullable();
                    $table->string('endpoint', 120)->nullable();
                    $table->unsignedSmallInteger('http_status')->nullable();
                    $table->string('error_type', 60);
                    $table->string('message', 500);
                    $table->timestamp('created_at')->useCurrent();

                    $table->foreign(
                        'inventory_sync_execution_id',
                        'isl_execution_fk',
                    )
                        ->references('id')
                        ->on('inventory_sync_executions')
                        ->cascadeOnDelete();
                    $table->foreign('warehouse_id', 'isl_warehouse_fk')
                        ->references('id')
                        ->on('warehouses')
                        ->nullOnDelete();
                    $table->index(
                        ['inventory_sync_execution_id', 'created_at'],
                        'isl_exec_created_ix',
                    );
                },
            );
        }
    }

    private function addExecutionColumns(Builder $schema): void
    {
        $columns = [
            'updated_count' => fn (Blueprint $table) => $table
                ->unsignedInteger('updated_count')
                ->default(0),
            'unchanged_count' => fn (Blueprint $table) => $table
                ->unsignedInteger('unchanged_count')
                ->default(0),
            'not_found_count' => fn (Blueprint $table) => $table
                ->unsignedInteger('not_found_count')
                ->default(0),
            'target_product_id' => fn (Blueprint $table) => $table
                ->unsignedBigInteger('target_product_id')
                ->nullable(),
            'target_product_variant_id' => fn (Blueprint $table) => $table
                ->unsignedBigInteger('target_product_variant_id')
                ->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if ($schema->hasColumn('inventory_sync_executions', $column)) {
                continue;
            }

            $schema->table(
                'inventory_sync_executions',
                function (Blueprint $table) use ($definition): void {
                    $definition($table);
                },
            );
        }

        if (! $schema->hasIndex('inventory_sync_executions', 'ise_target_product_ix')) {
            $schema->table(
                'inventory_sync_executions',
                function (Blueprint $table): void {
                    $table->index(
                        ['target_product_id', 'type', 'created_at'],
                        'ise_target_product_ix',
                    );
                },
            );
        }
    }

    public function down(): void
    {
        // Synchronization history is intentionally retained.
    }
};
