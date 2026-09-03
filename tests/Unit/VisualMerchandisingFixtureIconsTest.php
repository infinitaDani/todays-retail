<?php

namespace Tests\Unit;

use App\Modules\Merchandising\Models\MerchandisingFixtureType;
use App\Modules\Merchandising\Models\MerchandisingFloorPlan;
use App\Modules\Merchandising\Models\MerchandisingFloorPlanItem;
use App\Modules\Merchandising\Services\FloorPlanLayoutValidator;
use App\Modules\Merchandising\Services\MerchandisingFixtureTypeDefaults;
use App\Modules\Operations\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VisualMerchandisingFixtureIconsTest extends TestCase
{
    public function test_merchandising_models_use_tenant_connection(): void
    {
        $this->assertSame(
            'tenant',
            (new MerchandisingFixtureType())->getConnectionName(),
        );
        $this->assertSame(
            'tenant',
            (new MerchandisingFloorPlan())->getConnectionName(),
        );
        $this->assertSame(
            'tenant',
            (new MerchandisingFloorPlanItem())->getConnectionName(),
        );
    }

    public function test_all_twenty_four_default_icons_exist_as_png_files(): void
    {
        $defaults = MerchandisingFixtureTypeDefaults::DEFAULTS;

        $this->assertCount(24, $defaults);
        $this->assertCount(
            24,
            array_unique(array_column($defaults, 'icon_path')),
        );

        foreach ($defaults as $default) {
            $path = public_path($default['icon_path']);

            $this->assertFileExists($path);
            $this->assertSame(
                "\x89PNG\r\n\x1a\n",
                file_get_contents($path, false, null, 0, 8),
            );
        }
    }

    public function test_structure_and_accessory_shelves_use_distinct_correct_assets(): void
    {
        $defaults = collect(MerchandisingFixtureTypeDefaults::DEFAULTS)
            ->keyBy('code');

        $this->assertSame(
            'images/visual-merchandising/fixtures/structures/repisa-estructura.png',
            $defaults['structure-shelf']['icon_path'],
        );
        $this->assertSame(
            'images/visual-merchandising/fixtures/accessories/repisa-accesorio.png',
            $defaults['accessory-shelf']['icon_path'],
        );
        $this->assertSame('structure', $defaults['structure-shelf']['category']);
        $this->assertSame('accessory', $defaults['accessory-shelf']['category']);
    }

    public function test_every_default_has_the_expected_platform_asset(): void
    {
        $expected = [
            'Panel de pared|structure' => 'panel-de-pared.png',
            'Rack / Perchero|structure' => 'rack-perchero.png',
            'Góndola|structure' => 'gondola.png',
            'Estantería|structure' => 'estanteria.png',
            'Repisa|structure' => 'repisa-estructura.png',
            'Mesa de exhibición|structure' => 'mesa-de-exhibicion.png',
            'Torre|structure' => 'torre.png',
            'Vitrina|structure' => 'vitrina.png',
            'Pedestal|structure' => 'pedestal.png',
            'Maniquí|structure' => 'maniqui.png',
            'Cabecera de góndola / End cap|structure' => 'cabecera-de-gondola.png',
            'Isla / Exhibidor central|structure' => 'isla-exhibidor-central.png',
            'Slatwall|structure' => 'slatwall.png',
            'Gridwall|structure' => 'gridwall.png',
            'Barra horizontal / Hangrail|accessory' => 'barra-horizontal-hangrail.png',
            'Faceout|accessory' => 'faceout.png',
            'Flauta / Waterfall|accessory' => 'flauta-waterfall.png',
            'Brazo escalonado|accessory' => 'brazo-escalonado.png',
            'Brazo recto|accessory' => 'brazo-recto.png',
            'Barra T|accessory' => 'barra-t.png',
            'Gancho|accessory' => 'gancho.png',
            'Repisa|accessory' => 'repisa-accesorio.png',
            'Canasta|accessory' => 'canasta.png',
            'Columpio|accessory' => 'columpio.png',
        ];
        $actual = collect(MerchandisingFixtureTypeDefaults::DEFAULTS)
            ->mapWithKeys(function (array $default): array {
                return [
                    $default['name'] . '|' . $default['category'] => basename(
                        $default['icon_path'],
                    ),
                ];
            })
            ->all();

        $this->assertSame($expected, $actual);
    }

    public function test_custom_fixture_type_without_icon_uses_fallback_contract(): void
    {
        $fixtureType = new MerchandisingFixtureType([
            'name' => 'Elemento personalizado',
            'icon_path' => null,
        ]);

        $this->assertNull($fixtureType->iconUrl());
    }

    public function test_branch_supports_multiple_floor_plans(): void
    {
        $relation = (new Branch())->merchandisingFloorPlans();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame('branch_id', $relation->getForeignKeyName());

        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001315_create_visual_merchandising_foundation.php'
            )
        );

        $this->assertStringContainsString('mfp_branch_active_ix', $migration);
        $this->assertStringNotContainsString('mfp_branch_uq', $migration);
    }

    public function test_floor_plan_items_have_parent_and_children_relations(): void
    {
        $item = new MerchandisingFloorPlanItem();

        $this->assertInstanceOf(BelongsTo::class, $item->parent());
        $this->assertInstanceOf(HasMany::class, $item->children());
        $this->assertSame('parent_item_id', $item->parent()->getForeignKeyName());
        $this->assertSame('parent_item_id', $item->children()->getForeignKeyName());
    }

    public function test_accessory_can_use_a_structure_from_the_same_layout_as_parent(): void
    {
        $validator = new FloorPlanLayoutValidator();
        $fixtureTypes = collect([
            $this->fixtureType(1, MerchandisingFixtureType::CATEGORY_STRUCTURE),
            $this->fixtureType(2, MerchandisingFixtureType::CATEGORY_ACCESSORY),
        ])->keyBy('id');

        $validator->validate([
            [
                'client_key' => 'structure-1',
                'fixture_type_id' => 1,
                'parent_client_key' => null,
            ],
            [
                'client_key' => 'accessory-1',
                'fixture_type_id' => 2,
                'parent_client_key' => 'structure-1',
            ],
        ], $fixtureTypes);

        $this->assertTrue(true);
    }

    public function test_item_cannot_be_its_own_parent(): void
    {
        $this->expectException(ValidationException::class);

        (new FloorPlanLayoutValidator())->validate([
            [
                'client_key' => 'accessory-1',
                'fixture_type_id' => 2,
                'parent_client_key' => 'accessory-1',
            ],
        ], collect([
            $this->fixtureType(2, MerchandisingFixtureType::CATEGORY_ACCESSORY),
        ])->keyBy('id'));
    }

    public function test_parent_must_be_a_structure_in_the_same_floor_plan_layout(): void
    {
        $this->expectException(ValidationException::class);

        (new FloorPlanLayoutValidator())->validate([
            [
                'client_key' => 'accessory-1',
                'fixture_type_id' => 2,
                'parent_client_key' => 'structure-from-another-plan',
            ],
        ], collect([
            $this->fixtureType(2, MerchandisingFixtureType::CATEGORY_ACCESSORY),
        ])->keyBy('id'));
    }

    public function test_structure_cannot_have_a_parent(): void
    {
        $this->expectException(ValidationException::class);

        (new FloorPlanLayoutValidator())->validate([
            [
                'client_key' => 'structure-1',
                'fixture_type_id' => 1,
                'parent_client_key' => 'structure-2',
            ],
            [
                'client_key' => 'structure-2',
                'fixture_type_id' => 3,
                'parent_client_key' => null,
            ],
        ], collect([
            $this->fixtureType(1, MerchandisingFixtureType::CATEGORY_STRUCTURE),
            $this->fixtureType(3, MerchandisingFixtureType::CATEGORY_STRUCTURE),
        ])->keyBy('id'));
    }

    public function test_default_sync_is_idempotent_and_preserves_existing_icons(): void
    {
        $service = file_get_contents(
            app_path(
                'Modules/Merchandising/Services/MerchandisingFixtureTypeDefaults.php'
            )
        );

        $this->assertStringContainsString("->where('code', \$default['code'])", $service);
        $this->assertStringContainsString("->where('is_default', true)", $service);
        $this->assertStringContainsString('if (! $fixtureType->icon_path)', $service);
        $this->assertStringNotContainsString('truncate(', $service);
        $this->assertStringNotContainsString('delete()', $service);
    }

    public function test_all_merchandising_routes_require_tenant_management(): void
    {
        $routes = app('router')->getRoutes();
        $names = [
            'merchandising.fixture-types.index',
            'merchandising.fixture-types.create',
            'merchandising.fixture-types.store',
            'merchandising.fixture-types.edit',
            'merchandising.fixture-types.update',
            'merchandising.fixture-types.status',
            'merchandising.floor-plan',
            'merchandising.floor-plans.store',
            'merchandising.floor-plans.update',
        ];

        foreach ($names as $name) {
            $this->assertContains(
                'tenant.management',
                $routes->getByName($name)->gatherMiddleware(),
            );
        }
    }

    public function test_floor_plan_uses_icons_and_has_a_missing_icon_fallback(): void
    {
        $view = file_get_contents(
            resource_path('views/tenant/merchandising/floor-plan.blade.php')
        );
        $component = file_get_contents(
            resource_path('views/components/merchandising/fixture-icon.blade.php')
        );

        $this->assertStringContainsString('object-fit: contain', $view);
        $this->assertStringContainsString('floor-plan-placeholder', $view);
        $this->assertStringContainsString('onerror=', $component);
        $this->assertStringNotContainsString('base64', $view);
    }

    public function test_floor_plan_ui_supports_multiple_plans_and_parent_mapping(): void
    {
        $view = file_get_contents(
            resource_path('views/tenant/merchandising/floor-plan.blade.php')
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/MerchandisingFloorPlanController.php')
        );

        $this->assertStringContainsString('name="floor_plan_id"', $view);
        $this->assertStringContainsString('Nuevo Floor Plan', $view);
        $this->assertStringContainsString('Estructura contenedora', $view);
        $this->assertStringContainsString('parent_client_key', $view);
        $this->assertStringContainsString("\$createdItems[\$item['client_key']]", $controller);
        $this->assertStringNotContainsString(
            'MerchandisingFloorPlan::firstOrCreate',
            $controller,
        );
    }

    public function test_merchandising_schema_has_no_product_or_line_assignment(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001315_create_visual_merchandising_foundation.php'
            )
        );

        $this->assertStringContainsString("Schema::connection('tenant')", $migration);
        $this->assertStringContainsString('icon_path', $migration);
        $this->assertStringContainsString('parent_item_id', $migration);
        $this->assertStringContainsString('mfpi_parent_fk', $migration);
        $this->assertStringNotContainsString('product_id', $migration);
        $this->assertStringNotContainsString('product_line_id', $migration);
        $this->assertStringNotContainsString('base64', $migration);
    }

    private function fixtureType(int $id, string $category): MerchandisingFixtureType
    {
        return (new MerchandisingFixtureType())->forceFill([
            'id' => $id,
            'name' => "Fixture {$id}",
            'category' => $category,
        ]);
    }
}
