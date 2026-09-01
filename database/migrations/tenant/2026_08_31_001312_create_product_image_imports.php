<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('product_image_imports')) {
            $schema->create('product_image_imports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('core_user_id');
                $table->string('status', 30)->default('previewed');
                $table->string('zip_path', 500);
                $table->string('temporary_directory', 500);
                $table->string('original_filename', 255);
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('unmatched_count')->default(0);
                $table->unsignedInteger('ambiguous_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('invalid_count')->default(0);
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->json('preview_rows')->nullable();
                $table->json('errors')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index('core_user_id', 'pii_user_ix');
                $table->index(['status', 'created_at'], 'pii_status_created_ix');
            });
        }

        if ($schema->hasTable('product_images')) {
            if (! $schema->hasColumn('product_images', 'content_hash')) {
                $schema->table('product_images', function (Blueprint $table): void {
                    $table->string('content_hash', 64)->nullable();
                });
            }

            if (! $schema->hasColumn('product_images', 'product_image_import_id')) {
                $schema->table('product_images', function (Blueprint $table): void {
                    $table->unsignedBigInteger('product_image_import_id')->nullable();
                });
            }

            if (! $this->indexExists('product_images', 'pimg_product_hash_uq')) {
                $schema->table('product_images', function (Blueprint $table): void {
                    $table->unique(
                        ['product_id', 'content_hash'],
                        'pimg_product_hash_uq'
                    );
                });
            }

            if (! $this->foreignKeyExists('product_images', 'pimg_import_fk')) {
                $schema->table('product_images', function (Blueprint $table): void {
                    $table->foreign(
                        'product_image_import_id',
                        'pimg_import_fk'
                    )
                        ->references('id')
                        ->on('product_image_imports')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Import history and image hashes are retained to protect tenant data.
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::connection('tenant')->getIndexes($table);

        return collect($indexes)->contains(
            fn (array $definition): bool => ($definition['name'] ?? null) === $index
        );
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $foreignKeys = Schema::connection('tenant')->getForeignKeys($table);

        return collect($foreignKeys)->contains(
            fn (array $definition): bool => ($definition['name'] ?? null) === $foreignKey
        );
    }
};
