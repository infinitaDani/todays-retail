<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('knowledge_categories')) {
            $schema->create('knowledge_categories', function (Blueprint $table) {
                $table->id(); $table->string('name', 120); $table->string('slug', 140)->unique();
                $table->text('description')->nullable(); $table->string('icon', 80)->nullable();
                $table->boolean('is_active')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
            });
        }
        if (! $schema->hasTable('knowledge_article_versions')) {
            $schema->create('knowledge_article_versions', function (Blueprint $table) {
                $table->id(); $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
                $table->unsignedInteger('version_number'); $table->string('title', 200); $table->longText('content');
                $table->unsignedBigInteger('author_core_user_id')->nullable()->index();
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->boolean('requires_confirmation')->default(false); $table->json('audience')->nullable();
                $table->timestamp('published_at')->nullable(); $table->timestamps(); $table->unique(['article_id', 'version_number']);
            });
        }
        if (! $schema->hasTable('knowledge_article_category')) {
            $schema->create('knowledge_article_category', function (Blueprint $table) {
                $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('knowledge_categories')->cascadeOnDelete(); $table->primary(['article_id', 'category_id']);
            });
        }
        if (! $schema->hasTable('knowledge_version_readings')) {
            $schema->create('knowledge_version_readings', function (Blueprint $table) {
                $table->id(); $table->foreignId('knowledge_article_version_id')->constrained('knowledge_article_versions')->cascadeOnDelete();
                $table->unsignedBigInteger('core_user_id')->index(); $table->timestamp('first_opened_at')->nullable();
                $table->timestamp('last_opened_at')->nullable(); $table->timestamp('last_heartbeat_at')->nullable();
                $table->unsignedInteger('active_seconds')->default(0); $table->timestamp('confirmed_at')->nullable(); $table->timestamps();
                $table->unique(['knowledge_article_version_id', 'core_user_id'], 'knowledge_version_readings_unique_user_version');
            });
        } elseif (! $schema->hasColumn('knowledge_version_readings', 'last_heartbeat_at')) {
            $schema->table('knowledge_version_readings', fn (Blueprint $table) => $table->timestamp('last_heartbeat_at')->nullable()->after('last_opened_at'));
        }

        // Re-runnable data migration: legacy category text is never altered or removed.
        DB::connection('tenant')->table('knowledge_articles')->orderBy('id')->each(function (object $article): void {
            $connection = DB::connection('tenant');
            $versionNumber = max(1, (int) ($article->version ?: 1));
            if (! $connection->table('knowledge_article_versions')->where(['article_id' => $article->id, 'version_number' => $versionNumber])->exists()) {
                $connection->table('knowledge_article_versions')->insert([
                    'article_id' => $article->id, 'version_number' => $versionNumber, 'title' => $article->title, 'content' => $article->content,
                    'status' => $article->status === 'published' ? 'published' : ($article->status === 'inactive' ? 'archived' : 'draft'),
                    'requires_confirmation' => false, 'audience' => json_encode(['all']),
                    'published_at' => $article->status === 'published' ? ($article->updated_at ?: now()) : null,
                    'created_at' => $article->created_at ?: now(), 'updated_at' => $article->updated_at ?: now(),
                ]);
            }

            $name = trim((string) ($article->category ?? ''));
            if ($name === '') return;
            $normalized = Str::squish($name);
            $category = $connection->table('knowledge_categories')->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])->first();
            if (! $category) {
                $baseSlug = Str::slug($normalized) ?: 'categoria'; $slug = $baseSlug; $suffix = 2;
                while ($connection->table('knowledge_categories')->where('slug', $slug)->exists()) $slug = $baseSlug.'-'.$suffix++;
                $id = $connection->table('knowledge_categories')->insertGetId(['name' => $normalized, 'slug' => $slug, 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $category = (object) ['id' => $id];
            }
            $connection->table('knowledge_article_category')->insertOrIgnore(['article_id' => $article->id, 'category_id' => $category->id]);
        });

        // Legacy assignments/trackings intentionally remain untouched. Official reading history starts with Knowledge 2.0 versions.
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('knowledge_version_readings'); $schema->dropIfExists('knowledge_article_category');
        $schema->dropIfExists('knowledge_article_versions'); $schema->dropIfExists('knowledge_categories');
    }
};
