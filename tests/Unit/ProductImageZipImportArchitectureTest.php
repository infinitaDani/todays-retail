<?php

namespace Tests\Unit;

use App\Modules\Products\Models\ProductImageImport;
use App\Modules\Products\Services\ProductImageZipImportService;
use Tests\TestCase;

class ProductImageZipImportArchitectureTest extends TestCase
{
    public function test_image_import_history_uses_tenant_connection(): void
    {
        $this->assertSame(
            'tenant',
            (new ProductImageImport())->getConnectionName()
        );
    }

    public function test_all_image_import_routes_require_tenant_management(): void
    {
        $routes = app('router')->getRoutes();
        $routeNames = [
            'products.image-imports.create',
            'products.image-imports.preview',
            'products.image-imports.store',
            'products.image-imports.show',
            'products.image-imports.cancel',
        ];

        foreach ($routeNames as $routeName) {
            $this->assertContains(
                'tenant.management',
                $routes->getByName($routeName)->gatherMiddleware(),
                "The {$routeName} route must require tenant.management."
            );
        }
    }

    public function test_migration_adds_hash_uniqueness_and_import_history(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/tenant/2026_08_31_001312_create_product_image_imports.php'
            )
        );

        $this->assertStringContainsString('product_image_imports', $migration);
        $this->assertStringContainsString('content_hash', $migration);
        $this->assertStringContainsString('pimg_product_hash_uq', $migration);
        $this->assertStringContainsString('pimg_import_fk', $migration);
        $this->assertStringContainsString('nullOnDelete', $migration);
    }

    public function test_zip_import_matches_catalog_code_and_never_variant_sku(): void
    {
        $service = file_get_contents(
            app_path('Modules/Products/Services/ProductImageZipImportService.php')
        );

        $this->assertStringContainsString("'catalog_code'", $service);
        $this->assertStringContainsString("hash('sha256', \$contents)", $service);
        $this->assertStringContainsString("'product_variant_id' => null", $service);
        $this->assertStringNotContainsString('ProductVariant::', $service);
    }

    public function test_filename_matching_uses_every_contained_catalog_code(): void
    {
        $service = new ProductImageZipImportService();
        $matches = $service->catalogCodesInFilename(
            'Conjunto-Brasier-0419M62-Panty-brasilero-0119M62-Catalogo.jpg',
            ['0119M62', '0419M62', 'SKU-QUE-NO-CORRESPONDE']
        );

        $this->assertSame(['0119M62', '0419M62'], $matches);
    }

    public function test_zip_import_enforces_archive_safety_limits(): void
    {
        $service = file_get_contents(
            app_path('Modules/Products/Services/ProductImageZipImportService.php')
        );

        $this->assertStringContainsString('MAX_ENTRIES', $service);
        $this->assertStringContainsString('MAX_IMAGE_BYTES', $service);
        $this->assertStringContainsString('MAX_UNCOMPRESSED_BYTES', $service);
        $this->assertStringContainsString('MAX_COMPRESSION_RATIO', $service);
        $this->assertStringContainsString('safeOriginalFilename', $service);
        $this->assertStringContainsString('belongsToTemporaryDirectory', $service);
    }
}
