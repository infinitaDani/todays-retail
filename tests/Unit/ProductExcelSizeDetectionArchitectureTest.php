<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProductExcelSizeDetectionArchitectureTest extends TestCase
{
    public function test_import_setting_defaults_to_disabled(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001313_add_size_detection_to_product_imports.php'
            )
        );

        $this->assertStringContainsString('detect_size_from_code', $migration);
        $this->assertStringContainsString('->default(false)', $migration);
    }

    public function test_preview_only_detects_size_when_option_is_enabled(): void
    {
        $service = file_get_contents(
            app_path('Modules/Products/Services/ProductExcelImportService.php')
        );

        $this->assertStringContainsString('if ($detectSizeFromCode)', $service);
        $this->assertStringContainsString("'detected_size'", $service);
    }

    public function test_detector_only_uses_enabled_size_attribute_and_active_values(): void
    {
        $detector = file_get_contents(
            app_path('Modules/Products/Services/ProductSizeDetectionService.php')
        );

        $this->assertStringContainsString("->where('code', 'size')", $detector);
        $this->assertStringContainsString("->where('is_enabled', true)", $detector);
        $this->assertStringContainsString("->where('is_active', true)", $detector);
        $this->assertStringNotContainsString('ProductAttributeValue::create', $detector);
        $this->assertStringNotContainsString('firstOrCreate', $detector);
    }

    public function test_confirmation_attaches_without_removing_other_attributes(): void
    {
        $service = file_get_contents(
            app_path('Modules/Products/Services/ProductExcelImportService.php')
        );

        $this->assertStringContainsString('syncWithoutDetaching', $service);
        $this->assertStringNotContainsString(
            '->attributeValues()->sync([',
            $service
        );
    }

    public function test_existing_sku_is_still_omitted_before_variant_creation(): void
    {
        $service = file_get_contents(
            app_path('Modules/Products/Services/ProductExcelImportService.php')
        );
        $existingGuard = strpos(
            $service,
            "if (\$row['status'] === 'existing')"
        );
        $variantCreation = strpos($service, 'ProductVariant::create([');

        $this->assertNotFalse($existingGuard);
        $this->assertNotFalse($variantCreation);
        $this->assertLessThan($variantCreation, $existingGuard);
    }
}
