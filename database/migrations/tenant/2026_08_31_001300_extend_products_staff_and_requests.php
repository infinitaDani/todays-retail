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

        if ($schema->hasTable('product_settings')) {
            $this->addColumnIfMissing($schema, 'product_settings', 'manages_taxes', fn (Blueprint $table) => $table->boolean('manages_taxes')->default(false));
            $this->addColumnIfMissing($schema, 'product_settings', 'tax_percent', fn (Blueprint $table) => $table->decimal('tax_percent', 8, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_settings', 'vat_percent', fn (Blueprint $table) => $table->decimal('vat_percent', 8, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_settings', 'ice_percent', fn (Blueprint $table) => $table->decimal('ice_percent', 8, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_settings', 'manages_multiple_prices', fn (Blueprint $table) => $table->boolean('manages_multiple_prices')->default(false));
            $this->addColumnIfMissing($schema, 'product_settings', 'manages_distribution_price', fn (Blueprint $table) => $table->boolean('manages_distribution_price')->default(false));
        }

        if (! $schema->hasTable('product_types')) {
            $schema->create('product_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('normalized_name', 120);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique('normalized_name', 'pt_name_uq');
            });
        }

        if ($schema->hasTable('product_types')) {
            foreach (['Producto', 'Servicio', 'Consumible', 'Suministro'] as $position => $name) {
                DB::connection('tenant')->table('product_types')->updateOrInsert(
                    ['normalized_name' => mb_strtolower($name)],
                    [
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => $position + 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }

        if ($schema->hasTable('products')) {
            $this->addColumnIfMissing($schema, 'products', 'product_type_id', fn (Blueprint $table) => $table->unsignedBigInteger('product_type_id')->nullable());
            $this->addColumnIfMissing($schema, 'products', 'usage_period', fn (Blueprint $table) => $table->unsignedInteger('usage_period')->nullable());
            $this->addColumnIfMissing($schema, 'products', 'usage_period_unit', fn (Blueprint $table) => $table->string('usage_period_unit', 20)->nullable());
        }

        if ($schema->hasTable('product_variants')) {
            $this->addColumnIfMissing($schema, 'product_variants', 'is_inventory_item', fn (Blueprint $table) => $table->boolean('is_inventory_item')->default(true));
            $this->addColumnIfMissing($schema, 'product_variants', 'pvp1', fn (Blueprint $table) => $table->decimal('pvp1', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'pvp1_with_tax', fn (Blueprint $table) => $table->decimal('pvp1_with_tax', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'pvp2', fn (Blueprint $table) => $table->decimal('pvp2', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'pvp2_with_tax', fn (Blueprint $table) => $table->decimal('pvp2_with_tax', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'pvp3', fn (Blueprint $table) => $table->decimal('pvp3', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'pvp3_with_tax', fn (Blueprint $table) => $table->decimal('pvp3_with_tax', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'distribution_price', fn (Blueprint $table) => $table->decimal('distribution_price', 14, 4)->nullable());
            $this->addColumnIfMissing($schema, 'product_variants', 'distribution_price_with_tax', fn (Blueprint $table) => $table->decimal('distribution_price_with_tax', 14, 4)->nullable());
        }

        if ($schema->hasTable('staff_profiles')) {
            $this->addColumnIfMissing($schema, 'staff_profiles', 'hire_date', fn (Blueprint $table) => $table->date('hire_date')->nullable());
            $this->addColumnIfMissing($schema, 'staff_profiles', 'termination_date', fn (Blueprint $table) => $table->date('termination_date')->nullable());
        }

        if (! $schema->hasTable('tenant_requests')) {
            $schema->create('tenant_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('core_user_id');
                $table->string('type', 30);
                $table->string('status', 30)->default('pending');
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->text('comment')->nullable();
                $table->string('reason', 200)->nullable();
                $table->string('modality', 80)->nullable();
                $table->decimal('recovery_hours', 8, 2)->nullable();
                $table->unsignedBigInteger('reviewed_by_core_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_comment')->nullable();
                $table->string('month_key', 7)->nullable();
                $table->timestamps();
                $table->index(['core_user_id', 'type'], 'treq_user_type_ix');
                $table->index(['status', 'type'], 'treq_status_type_ix');
            });
        }

        if (! $schema->hasTable('tenant_request_items')) {
            $schema->create('tenant_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_request_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('quantity', 14, 3);
                $table->timestamps();
                $table->foreign('tenant_request_id', 'tri_request_fk')->references('id')->on('tenant_requests')->cascadeOnDelete();
                $table->foreign('product_id', 'tri_product_fk')->references('id')->on('products')->restrictOnDelete();
                $table->unique(['tenant_request_id', 'product_id'], 'tri_req_prod_uq');
            });
        }

        if (! $schema->hasTable('product_imports')) {
            $schema->create('product_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('core_user_id');
                $table->string('status', 30)->default('previewed');
                $table->string('excel_path');
                $table->string('images_zip_path')->nullable();
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->json('errors')->nullable();
                $table->json('unresolved_images')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally no destructive rollback for production tenant data.
    }

    private function addColumnIfMissing($schema, string $table, string $column, callable $definition): void
    {
        if (! $schema->hasColumn($table, $column)) {
            $schema->table($table, $definition);
        }
    }
};
