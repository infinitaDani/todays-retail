<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $schema = Schema::connection('tenant');
        $hasIndex = static function (string $table, string $name, array $columns = []): bool {
            $indexes = collect(DB::connection('tenant')->select("SHOW INDEX FROM `{$table}`"));
            if ($indexes->contains(fn (object $index) => $index->Key_name === $name)) return true;
            return $columns !== [] && $indexes->groupBy('Key_name')->contains(function ($parts) use ($columns) {
                return $parts->sortBy('Seq_in_index')->pluck('Column_name')->values()->all() === $columns;
            });
        };
        $ensureIndex = static function (string $table, array $columns, string $name, bool $unique = false) use ($schema, $hasIndex): void {
            if ($hasIndex($table, $name, $columns)) return;
            $schema->table($table, fn (Blueprint $t) => $unique ? $t->unique($columns, $name) : $t->index($columns, $name));
        };

        if (! $schema->hasTable('product_settings')) $schema->create('product_settings', function (Blueprint $t) { $t->id(); $t->boolean('manages_collections')->default(false); $t->boolean('manages_collection_lines')->default(false); $t->timestamps(); });
        if (! $schema->hasTable('product_attributes')) $schema->create('product_attributes', function (Blueprint $t) { $t->id(); $t->string('code',50); $t->string('name',100); $t->boolean('is_enabled')->default(false); $t->unsignedInteger('sort_order')->default(0); $t->timestamps(); $t->unique('code','pa_code_uq'); });
        if (! $schema->hasTable('product_attribute_values')) $schema->create('product_attribute_values', function (Blueprint $t) { $t->id(); $t->foreignId('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete(); $t->string('value',100); $t->string('normalized_value',120); $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0); $t->timestamps(); $t->unique(['product_attribute_id','normalized_value'],'pav_attr_val_uq'); });
        if (! $hasIndex('product_attribute_values','pav_attr_val_uq',['product_attribute_id','normalized_value'])) $schema->table('product_attribute_values', fn (Blueprint $t) => $t->unique(['product_attribute_id','normalized_value'],'pav_attr_val_uq'));

        if (! $schema->hasTable('product_categories')) $schema->create('product_categories', function (Blueprint $t) { $t->id(); $t->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete(); $t->unsignedBigInteger('parent_key')->default(0); $t->string('name',150); $t->string('normalized_name',170); $t->string('slug',180); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0); $t->timestamps(); $t->unique(['parent_key','normalized_name'],'pcat_parent_name_uq'); $t->unique('slug','pcat_slug_uq'); });
        if (! $schema->hasTable('product_collections')) $schema->create('product_collections', function (Blueprint $t) { $t->id(); $t->string('name',150); $t->string('normalized_name',170); $t->string('reference',100)->nullable(); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique('normalized_name','pcol_name_uq'); $t->index('reference','pcol_reference_ix'); });
        if (! $schema->hasTable('product_collection_lines')) $schema->create('product_collection_lines', function (Blueprint $t) { $t->id(); $t->foreignId('product_collection_id')->constrained('product_collections')->cascadeOnDelete(); $t->string('name',150); $t->string('normalized_name',170); $t->text('description')->nullable(); $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0); $t->timestamps(); $t->unique(['product_collection_id','normalized_name'],'pcl_col_name_uq'); });
        if (! $schema->hasTable('products')) $schema->create('products', function (Blueprint $t) { $t->id(); $t->string('catalog_code',120)->nullable(); $t->string('name',200); $t->text('description')->nullable(); $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete(); $t->foreignId('product_collection_id')->nullable()->constrained('product_collections')->nullOnDelete(); $t->foreignId('product_collection_line_id')->nullable()->constrained('product_collection_lines')->nullOnDelete(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->index('catalog_code','prod_catalog_code_ix'); });
        if (! $schema->hasTable('product_variants')) $schema->create('product_variants', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->constrained('products')->cascadeOnDelete(); $t->string('sku',150); $t->string('auxiliary_code',150)->nullable(); $t->decimal('stock',14,3)->nullable(); $t->decimal('minimum_stock',14,3)->nullable(); $t->decimal('sale_price',14,4)->nullable(); $t->decimal('purchase_price',14,4)->nullable(); $t->boolean('is_for_sale')->default(true); $t->boolean('is_for_purchase')->default(false); $t->boolean('is_taxable')->default(true); $t->decimal('tax_rate',8,4)->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique('sku','pvar_sku_uq'); $t->index('auxiliary_code','pvar_aux_code_ix'); });
        if (! $schema->hasTable('product_variant_attribute_value')) $schema->create('product_variant_attribute_value', function (Blueprint $t) { $t->unsignedBigInteger('product_variant_id'); $t->unsignedBigInteger('product_attribute_value_id'); $t->foreign('product_variant_id','pvat_variant_fk')->references('id')->on('product_variants')->cascadeOnDelete(); $t->foreign('product_attribute_value_id','pvat_attr_value_fk')->references('id')->on('product_attribute_values')->cascadeOnDelete(); $t->primary(['product_variant_id','product_attribute_value_id'],'pvar_attr_val_pk'); });

        // Covers a retry after an engine created a table but failed while adding an index.
        $ensureIndex('product_attributes',['code'],'pa_code_uq',true); $ensureIndex('product_categories',['parent_key','normalized_name'],'pcat_parent_name_uq',true); $ensureIndex('product_categories',['slug'],'pcat_slug_uq',true); $ensureIndex('product_collections',['normalized_name'],'pcol_name_uq',true); $ensureIndex('product_collections',['reference'],'pcol_reference_ix'); $ensureIndex('product_collection_lines',['product_collection_id','normalized_name'],'pcl_col_name_uq',true); $ensureIndex('products',['catalog_code'],'prod_catalog_code_ix'); $ensureIndex('product_variants',['sku'],'pvar_sku_uq',true); $ensureIndex('product_variants',['auxiliary_code'],'pvar_aux_code_ix');
    }
    public function down(): void { foreach (['product_variant_attribute_value','product_variants','products','product_collection_lines','product_collections','product_categories','product_attribute_values','product_attributes','product_settings'] as $table) Schema::connection('tenant')->dropIfExists($table); }
};
