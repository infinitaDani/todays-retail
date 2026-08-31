<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('product_images')) {
            $schema->create('product_images', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->string('path', 500);
                $table->string('original_filename', 255)->nullable();
                $table->string('alt_text', 255)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('product_id', 'pimg_product_fk')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('product_variant_id', 'pimg_variant_fk')->references('id')->on('product_variants')->cascadeOnDelete();
                $table->index(['product_id', 'product_variant_id'], 'pimg_owner_ix');
            });
        }
    }

    public function down(): void
    {
        // Product image records are retained to avoid destructive tenant rollbacks.
    }
};
