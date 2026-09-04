<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\ContificoApiException;
use App\Modules\Inventory\Models\InventorySyncExecutionItem;

class ContificoStockValue
{
    public function normalize(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '' || ! is_numeric($quantity)) {
            throw new ContificoApiException(
                'Contífico devolvió una cantidad de stock inválida.',
                'invalid_quantity',
            );
        }

        $value = (float) $quantity;

        if (! is_finite($value) || abs($value) >= 100000000000) {
            throw new ContificoApiException(
                'La cantidad de stock excede el rango permitido.',
                'invalid_quantity',
            );
        }

        return number_format($value, 3, '.', '');
    }

    public function result(mixed $localQuantity, string $remoteQuantity): string
    {
        if ($localQuantity === null) {
            return InventorySyncExecutionItem::RESULT_UPDATED;
        }

        $unchanged = number_format((float) $localQuantity, 3, '.', '')
            === $remoteQuantity;

        return $unchanged
            ? InventorySyncExecutionItem::RESULT_UNCHANGED
            : InventorySyncExecutionItem::RESULT_UPDATED;
    }
}
