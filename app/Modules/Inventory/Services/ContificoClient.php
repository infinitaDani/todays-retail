<?php

namespace App\Modules\Inventory\Services;

use App\Core\Accounts\Account;
use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ContificoClient
{
    public const PRODUCTS_ENDPOINT = '/api/v1/producto/';

    public function testConnection(
        Account $account,
        ContificoSetting $settings,
    ): array {
        if (! $account->contifico_enabled) {
            throw new RuntimeException(
                'El plan de esta cuenta no habilita Contífico.',
            );
        }

        if (! $settings->api_key) {
            throw new RuntimeException('Guarda una API Key antes de probar la conexión.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => $settings->api_key,
                ])
                ->timeout(10)
                ->get($this->endpoint(), [
                    'page_size' => 1,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException(
                'No fue posible conectar con Contífico. Verifica la red e inténtalo nuevamente.',
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Contífico respondió con estado {$response->status()}. Verifica la API Key.",
            );
        }

        return [
            'successful' => true,
            'status' => $response->status(),
        ];
    }

    public function productQuery(string $sku, Warehouse $warehouse): array
    {
        return [
            'codigo' => trim($sku),
            'pos' => $warehouse->contifico_code,
        ];
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.contifico.base_url'), '/')
            . self::PRODUCTS_ENDPOINT;
    }
}
