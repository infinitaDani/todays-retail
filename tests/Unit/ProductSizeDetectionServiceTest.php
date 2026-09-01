<?php

namespace Tests\Unit;

use App\Modules\Products\Models\ProductAttributeValue;
use App\Modules\Products\Services\ProductSizeDetectionService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ProductSizeDetectionServiceTest extends TestCase
{
    private ProductSizeDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductSizeDetectionService();
    }

    public function test_it_detects_a_configured_single_letter_size(): void
    {
        $result = $this->service->detectFromValues(
            '2392C52L',
            '2392C52',
            $this->values('L', 'M', 'XL')
        );

        $this->assertSame('L', $result['detected_size']);
        $this->assertSame('L', $result['suffix']);
        $this->assertNull($result['warning']);
    }

    public function test_it_uses_the_complete_suffix_for_xl(): void
    {
        $result = $this->service->detectFromValues(
            '  2392c52xl  ',
            ' 2392C52 ',
            $this->values('L', 'XL')
        );

        $this->assertSame('XL', $result['detected_size']);
        $this->assertSame('XL', $result['suffix']);
    }

    public function test_it_does_not_create_or_return_an_unknown_size(): void
    {
        $result = $this->service->detectFromValues(
            '2392C52XYZ',
            '2392C52',
            $this->values('L', 'M', 'XL')
        );

        $this->assertNull($result['attribute_value']);
        $this->assertNull($result['detected_size']);
        $this->assertSame('XYZ', $result['suffix']);
        $this->assertSame(
            'No se encontró una talla configurada para el sufijo XYZ.',
            $result['warning']
        );
    }

    public function test_it_rejects_a_sku_that_does_not_start_with_catalog_code(): void
    {
        $result = $this->service->detectFromValues(
            'OTRO2392C52L',
            '2392C52',
            $this->values('L')
        );

        $this->assertNull($result['attribute_value']);
        $this->assertNull($result['detected_size']);
        $this->assertStringContainsString(
            'no comienza por el Código Catálogo',
            $result['warning']
        );
    }

    public function test_it_ignores_inactive_configured_values(): void
    {
        $inactiveValue = $this->value('L', false);
        $result = $this->service->detectFromValues(
            '2392C52L',
            '2392C52',
            collect([$inactiveValue])
        );

        $this->assertNull($result['attribute_value']);
        $this->assertStringContainsString(
            'No se encontró una talla configurada',
            $result['warning']
        );
    }

    private function values(string ...$sizes): Collection
    {
        return collect($sizes)
            ->map(fn (string $size): ProductAttributeValue => $this->value($size));
    }

    private function value(
        string $size,
        bool $active = true
    ): ProductAttributeValue {
        $value = new ProductAttributeValue([
            'value' => $size,
            'normalized_value' => mb_strtolower($size),
            'is_active' => $active,
        ]);
        $value->id = crc32($size . ($active ? 'active' : 'inactive'));

        return $value;
    }
}
