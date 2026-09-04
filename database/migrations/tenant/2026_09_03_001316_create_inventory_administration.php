<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('warehouses')) {
            if (! $schema->hasColumn('warehouses', 'purposes')) {
				$schema->table('warehouses', function (Blueprint $table) {
					$table->json('purposes')->nullable();
				});
			}

            if (! $schema->hasColumn('warehouses', 'contifico_code')) {
                $schema->table('warehouses', function (Blueprint $table): void {
                    $table->string('contifico_code', 120)->nullable();
                });
            }

            if (! $schema->hasIndex('warehouses', 'wh_contifico_code_uq')) {
                $schema->table('warehouses', function (Blueprint $table): void {
                    $table->unique('contifico_code', 'wh_contifico_code_uq');
                });
            }
        }

        if (! $schema->hasTable('inventory_settings')) {
            $schema->create('inventory_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('manages_warehouses')->default(true);
                $table->boolean('contifico_stock_sync_enabled')->default(false);
                $table->unsignedInteger('manual_bulk_syncs_per_day')->nullable();
				$table->unsignedInteger('manual_bulk_min_interval_minutes')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('contifico_settings')) {
            $schema->create('contifico_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->text('api_key')->nullable();
                $table->boolean('automatic_sync_enabled')->default(false);
                $table->unsignedInteger('sync_interval_minutes')->default(60);
                $table->unsignedInteger('batch_size')->default(100);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('inventory_user_limits')) {
            $schema->create('inventory_user_limits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('core_user_id');
                $table->unsignedInteger('manual_bulk_syncs_per_day')->nullable();
                $table->timestamps();

                $table->unique('core_user_id', 'iul_core_user_uq');
            });
        }

        if (! $schema->hasTable('inventory_sync_executions')) {
            $schema->create('inventory_sync_executions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('requested_by_core_user_id');
                $table->string('type', 40);
                $table->string('scope', 40);
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('status', 30)->default('pending');
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('succeeded_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'ise_branch_fk')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
                $table->foreign('warehouse_id', 'ise_warehouse_fk')
                    ->references('id')
                    ->on('warehouses')
                    ->nullOnDelete();
                $table->index(
                    ['requested_by_core_user_id', 'created_at'],
                    'ise_user_created_ix',
                );
                $table->index(['type', 'status'], 'ise_type_status_ix');
            });
        }
    }

    public function down(): void
    {
        // Inventory administration and audit data are intentionally retained.
    }
};
