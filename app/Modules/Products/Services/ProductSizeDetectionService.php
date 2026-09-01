<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\ProductAttribute;
use App\Modules\Products\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

class ProductSizeDetectionService
{
    public function detect(string $sku, string $catalogCode): array
    {
        $attribute = ProductAttribute::query()
            ->where('code', 'size')
            ->where('is_enabled', true)
            ->get()
            ->first(
                fn (ProductAttribute $candidate): bool => $candidate->code === 'size'
            );

        if (! $attribute) {
            return $this->result(
                warning: 'No se pudo detectar talla porque el atributo habilitado size no está configurado.'
            );
        }

        $values = $attribute->values()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get();

        return $this->detectFromValues($sku, $catalogCode, $values);
    }

    public function detectFromValues(
        string $sku,
        string $catalogCode,
        Collection $values
    ): array {
        $normalizedSku = $this->normalize($sku);
        $normalizedCatalogCode = $this->normalize($catalogCode);

        if ($normalizedCatalogCode === '') {
            return $this->result(
                warning: 'No se pudo detectar talla porque el Código Catálogo está vacío.'
            );
        }

        if (! str_starts_with($normalizedSku, $normalizedCatalogCode)) {
            return $this->result(
                warning: 'No se pudo detectar talla porque el Código no comienza por el Código Catálogo.'
            );
        }

        $suffix = trim(
            mb_substr(
                $normalizedSku,
                mb_strlen($normalizedCatalogCode)
            )
        );

        if ($suffix === '') {
            return $this->result(
                warning: 'No se pudo detectar talla porque el Código no tiene un sufijo después del Código Catálogo.'
            );
        }

        $attributeValue = $values->first(
            fn (ProductAttributeValue $value): bool => $value->is_active
                && $this->normalize($value->value) === $suffix
        );

        if (! $attributeValue) {
            return $this->result(
                suffix: $suffix,
                warning: "No se encontró una talla configurada para el sufijo {$suffix}."
            );
        }

        return $this->result(
            attributeValue: $attributeValue,
            suffix: $suffix
        );
    }

    private function result(
        ?ProductAttributeValue $attributeValue = null,
        ?string $suffix = null,
        ?string $warning = null
    ): array {
        return [
            'attribute_value' => $attributeValue,
            'attribute_value_id' => $attributeValue?->id,
            'detected_size' => $attributeValue?->value,
            'suffix' => $suffix,
            'warning' => $warning,
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
