<?php

namespace App\Modules\Inventory\Services;

use App\Core\Accounts\Account;
use App\Modules\Inventory\Exceptions\ContificoApiException;
use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
            throw new RuntimeException('El plan de esta cuenta no habilita Contífico.');
        }

        if (! $settings->api_key) {
            throw new RuntimeException('Guarda una API Key antes de probar la conexión.');
        }

        try {
            $response = $this->request(
                $settings,
                ['page_size' => 1],
                false,
            );
        } catch (ContificoApiException $exception) {
            throw new RuntimeException($exception->getMessage());
        }

        return ['successful' => true, 'status' => $response->status()];
    }

    public function findProductBySkuForWarehouse(
        string $sku,
        Warehouse $warehouse,
    ): ?array {
        $sku = trim($sku);

        if ($sku === '') {
            throw new ContificoApiException('El SKU está vacío.', 'invalid_sku');
        }

        if (! filled($warehouse->contifico_code)) {
            throw new ContificoApiException(
                'La bodega no tiene Código Contífico configurado.',
                'missing_warehouse_code',
            );
        }

        $response = $this->request(
            ContificoSetting::current(),
            $this->productQuery($sku, $warehouse),
        );
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ContificoApiException(
                'Contífico devolvió una respuesta inválida.',
                'invalid_response',
                $response->status(),
                true,
            );
        }

        return $this->selectExactProduct($payload, $sku);
    }

    public function selectExactProduct(array $payload, string $sku): ?array
    {
        $matches = collect($payload)
            ->filter(fn ($product): bool => is_array($product))
            ->filter(
                fn (array $product): bool => trim((string) ($product['codigo'] ?? '')) === trim($sku),
            )
            ->values();

        if ($matches->count() > 1) {
            throw new ContificoApiException(
                'Contífico devolvió más de una coincidencia exacta para el SKU.',
                'ambiguous_exact_match',
            );
        }

        return $matches->first();
    }

    public function productQuery(string $sku, Warehouse $warehouse): array
    {
        return [
            'codigo' => trim($sku),
            'pos' => trim((string) $warehouse->contifico_code),
        ];
    }

    private function request(
        ContificoSetting $settings,
        array $query,
        bool $requireActive = true,
    ): Response {
        if (($requireActive && ! $settings->is_active) || ! $settings->api_key) {
            throw new ContificoApiException(
                'La integración Contífico no está activa o no tiene API Key.',
                'integration_inactive',
                null,
                true,
            );
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['Authorization' => $settings->api_key])
                ->timeout(20)
                ->get($this->endpoint(), $query);
        } catch (ConnectionException) {
            throw new ContificoApiException(
                'No fue posible conectar con Contífico.',
                'connection_error',
                null,
                true,
            );
        }

        if (! $response->successful()) {
            $status = $response->status();
            $abort = in_array($status, [401, 403, 429], true) || $status >= 500;

            throw new ContificoApiException(
                "Contífico respondió con estado {$status}.",
                'http_error',
                $status,
                $abort,
            );
        }

        return $response;
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.contifico.base_url'), '/')
            . self::PRODUCTS_ENDPOINT;
    }
}
