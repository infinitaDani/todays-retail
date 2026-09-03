<?php

namespace App\Modules\Merchandising\Services;

use App\Modules\Merchandising\Models\MerchandisingFixtureType;
use Illuminate\Support\Str;

class MerchandisingFixtureTypeDefaults
{
    public const DEFAULTS = [
        [
            'code' => 'wall-panel',
            'name' => 'Panel de pared',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/panel-de-pared.png',
        ],
        [
            'code' => 'rack',
            'name' => 'Rack / Perchero',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/rack-perchero.png',
        ],
        [
            'code' => 'gondola',
            'name' => 'Góndola',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/gondola.png',
        ],
        [
            'code' => 'shelving-unit',
            'name' => 'Estantería',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/estanteria.png',
        ],
        [
            'code' => 'structure-shelf',
            'name' => 'Repisa',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/repisa-estructura.png',
        ],
        [
            'code' => 'display-table',
            'name' => 'Mesa de exhibición',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/mesa-de-exhibicion.png',
        ],
        [
            'code' => 'tower',
            'name' => 'Torre',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/torre.png',
        ],
        [
            'code' => 'display-case',
            'name' => 'Vitrina',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/vitrina.png',
        ],
        [
            'code' => 'pedestal',
            'name' => 'Pedestal',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/pedestal.png',
        ],
        [
            'code' => 'mannequin',
            'name' => 'Maniquí',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/maniqui.png',
        ],
        [
            'code' => 'end-cap',
            'name' => 'Cabecera de góndola / End cap',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/cabecera-de-gondola.png',
        ],
        [
            'code' => 'central-display',
            'name' => 'Isla / Exhibidor central',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/isla-exhibidor-central.png',
        ],
        [
            'code' => 'slatwall',
            'name' => 'Slatwall',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/slatwall.png',
        ],
        [
            'code' => 'gridwall',
            'name' => 'Gridwall',
            'category' => 'structure',
            'icon_path' => 'images/visual-merchandising/fixtures/structures/gridwall.png',
        ],
        [
            'code' => 'hangrail',
            'name' => 'Barra horizontal / Hangrail',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/barra-horizontal-hangrail.png',
        ],
        [
            'code' => 'faceout',
            'name' => 'Faceout',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/faceout.png',
        ],
        [
            'code' => 'waterfall',
            'name' => 'Flauta / Waterfall',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/flauta-waterfall.png',
        ],
        [
            'code' => 'stepped-arm',
            'name' => 'Brazo escalonado',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/brazo-escalonado.png',
        ],
        [
            'code' => 'straight-arm',
            'name' => 'Brazo recto',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/brazo-recto.png',
        ],
        [
            'code' => 't-bar',
            'name' => 'Barra T',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/barra-t.png',
        ],
        [
            'code' => 'hook',
            'name' => 'Gancho',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/gancho.png',
        ],
        [
            'code' => 'accessory-shelf',
            'name' => 'Repisa',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/repisa-accesorio.png',
        ],
        [
            'code' => 'basket',
            'name' => 'Canasta',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/canasta.png',
        ],
        [
            'code' => 'swing-arm',
            'name' => 'Columpio',
            'category' => 'accessory',
            'icon_path' => 'images/visual-merchandising/fixtures/accessories/columpio.png',
        ],
    ];

    public function sync(): void
    {
        foreach (self::DEFAULTS as $sortOrder => $default) {
            $normalizedName = $this->normalize($default['name']);
            $fixtureType = MerchandisingFixtureType::query()
                ->where('code', $default['code'])
                ->first();

            if (! $fixtureType) {
                $fixtureType = MerchandisingFixtureType::query()
                    ->where('normalized_name', $normalizedName)
                    ->where('category', $default['category'])
                    ->where('is_default', true)
                    ->first();
            }

            if (! $fixtureType) {
                MerchandisingFixtureType::create([
                    'code' => $default['code'],
                    'name' => $default['name'],
                    'normalized_name' => $normalizedName,
                    'category' => $default['category'],
                    'icon_path' => $default['icon_path'],
                    'is_default' => true,
                    'is_active' => true,
                    'sort_order' => $sortOrder + 1,
                ]);

                continue;
            }

            $updates = [];

            if (! $fixtureType->code) {
                $updates['code'] = $default['code'];
            }

            if (! $fixtureType->icon_path) {
                $updates['icon_path'] = $default['icon_path'];
            }

            if (! $fixtureType->is_default) {
                $updates['is_default'] = true;
            }

            if ($updates !== []) {
                $fixtureType->update($updates);
            }
        }
    }

    public function normalize(string $name): string
    {
        return mb_strtolower(Str::squish(Str::ascii($name)));
    }
}
