<?php

namespace App\Modules\Merchandising\Services;

use App\Modules\Merchandising\Models\MerchandisingFixtureType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FloorPlanLayoutValidator
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @param Collection<int, MerchandisingFixtureType> $fixtureTypes
     */
    public function validate(array $items, Collection $fixtureTypes): void
    {
        $itemsByKey = collect($items)->keyBy('client_key');

        foreach ($items as $index => $item) {
            $parentKey = $item['parent_client_key'] ?? null;

            if (! $parentKey) {
                continue;
            }

            $fixtureType = $fixtureTypes->get((int) $item['fixture_type_id']);

            if (! $fixtureType) {
                $this->fail($index, 'El tipo de elemento ya no está disponible.');
            }

            if ($fixtureType->category !== MerchandisingFixtureType::CATEGORY_ACCESSORY) {
                // Structures are roots. Combined with a structure-only parent,
                // this also makes a parent cycle impossible.
                $this->fail(
                    $index,
                    'Solo un accesorio puede asociarse a una estructura contenedora.',
                );
            }

            if ($parentKey === $item['client_key']) {
                $this->fail(
                    $index,
                    'Un elemento no puede ser su propio padre.',
                );
            }

            $parent = $itemsByKey->get($parentKey);

            if (! $parent) {
                // Only client keys submitted for this plan are accepted.
                $this->fail(
                    $index,
                    'La estructura contenedora debe pertenecer al mismo Floor Plan.',
                );
            }

            $parentFixtureType = $fixtureTypes->get(
                (int) $parent['fixture_type_id'],
            );

            if (
                ! $parentFixtureType
                || $parentFixtureType->category !== MerchandisingFixtureType::CATEGORY_STRUCTURE
            ) {
                $this->fail(
                    $index,
                    'El elemento contenedor debe ser una estructura del mismo Floor Plan.',
                );
            }
        }
    }

    private function fail(int $index, string $message): never
    {
        throw ValidationException::withMessages([
            "layout.items.{$index}.parent_client_key" => $message,
        ]);
    }
}
