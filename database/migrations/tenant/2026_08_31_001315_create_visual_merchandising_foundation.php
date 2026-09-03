<?php

use App\Modules\Merchandising\Services\MerchandisingFixtureTypeDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('merchandising_fixture_types')) {
            $schema->create('merchandising_fixture_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 100)->nullable();
                $table->string('name', 150);
                $table->string('normalized_name', 170);
                $table->string('category', 40);
                $table->string('icon_path', 500)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique('code', 'mft_code_uq');
                $table->index(
                    ['category', 'normalized_name'],
                    'mft_category_name_ix',
                );
                $table->index(
                    ['category', 'is_active', 'sort_order'],
                    'mft_category_active_ix',
                );
            });
        }

        if (! $schema->hasTable('merchandising_floor_plans')) {
            $schema->create('merchandising_floor_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('branch_id');
                $table->string('name', 150);
                $table->unsignedInteger('canvas_width')->default(1200);
                $table->unsignedInteger('canvas_height')->default(700);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('branch_id', 'mfp_branch_fk')
                    ->references('id')
                    ->on('branches')
                    ->restrictOnDelete();
                $table->index(
                    ['branch_id', 'is_active'],
                    'mfp_branch_active_ix',
                );
            });
        }

        if (! $schema->hasTable('merchandising_floor_plan_items')) {
            $schema->create(
                'merchandising_floor_plan_items',
                function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('floor_plan_id');
                    $table->unsignedBigInteger('fixture_type_id');
                    $table->unsignedBigInteger('parent_item_id')->nullable();
                    $table->string('label', 150)->nullable();
                    $table->decimal('position_x', 7, 3)->default(0);
                    $table->decimal('position_y', 7, 3)->default(0);
                    $table->decimal('width', 7, 3)->default(12);
                    $table->decimal('height', 7, 3)->default(18);
                    $table->decimal('rotation', 6, 2)->default(0);
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->foreign('floor_plan_id', 'mfpi_plan_fk')
                        ->references('id')
                        ->on('merchandising_floor_plans')
                        ->cascadeOnDelete();
                    $table->foreign('fixture_type_id', 'mfpi_type_fk')
                        ->references('id')
                        ->on('merchandising_fixture_types')
                        ->restrictOnDelete();
                    $table->index('parent_item_id', 'mfpi_parent_ix');
                    $table->foreign('parent_item_id', 'mfpi_parent_fk')
                        ->references('id')
                        ->on('merchandising_floor_plan_items')
                        ->nullOnDelete();
                    $table->index(
                        ['floor_plan_id', 'sort_order'],
                        'mfpi_plan_order_ix',
                    );
                },
            );
        }

        app(MerchandisingFixtureTypeDefaults::class)->sync();
    }

    public function down(): void
    {
        // Tenant floor plans and fixture customizations are intentionally retained.
    }
};
