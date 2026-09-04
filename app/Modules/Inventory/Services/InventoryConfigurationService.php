<?php

namespace App\Modules\Inventory\Services;

use App\Core\Accounts\Account;
use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventoryUserLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryConfigurationService
{
    public function update(
        Account $account,
        array $data,
        array $allowedUserIds,
    ): void {
        $this->validateCommercialLimits($account, $data, $allowedUserIds);

        DB::connection('tenant')->transaction(
            function () use ($data, $allowedUserIds): void {
                InventorySetting::current()->update([
                    'manages_warehouses' => $data['manages_warehouses'],
                    'contifico_stock_sync_enabled' => $data['contifico_stock_sync_enabled'],
                    'manual_bulk_syncs_per_day' => $data['manual_bulk_syncs_per_day'],
					'manual_bulk_min_interval_minutes' => $data[
						'manual_bulk_min_interval_minutes'
					],
                ]);

                $contifico = ContificoSetting::current();
                $contificoValues = [
                    'is_active' => $data['contifico_is_active'],
                    'automatic_sync_enabled' => $data['automatic_sync_enabled'],
                    'sync_interval_minutes' => $data['sync_interval_minutes'],
                    'batch_size' => $data['batch_size'],
                ];

                if (! empty($data['api_key'])) {
                    $contificoValues['api_key'] = $data['api_key'];
                }

                $contifico->update($contificoValues);
                $this->replaceUserLimits(
                    $data['user_limits'] ?? [],
                    $allowedUserIds,
                );
                $this->removeLimitsForFormerMembers($allowedUserIds);
            },
        );
    }

    private function validateCommercialLimits(
        Account $account,
        array $data,
        array $allowedUserIds,
    ): void {
        if (
            ! $account->contifico_enabled
            && (
                $data['contifico_is_active']
                || $data['contifico_stock_sync_enabled']
                || $data['automatic_sync_enabled']
                || ! empty($data['api_key'])
            )
        ) {
            $this->fail(
                'contifico_is_active',
                'El plan de esta cuenta no tiene habilitada la integración con Contífico.',
            );
        }

        if ($data['automatic_sync_enabled'] && ! $data['contifico_is_active']) {
            $this->fail(
                'automatic_sync_enabled',
                'Activa la integración antes de habilitar la sincronización automática.',
            );
        }

        if (
            $data['contifico_is_active']
            && empty($data['api_key'])
            && ! ContificoSetting::current()->api_key
        ) {
            $this->fail(
                'api_key',
                'Debes guardar una API Key para activar la integración.',
            );
        }

        if (
            $data['contifico_stock_sync_enabled']
            && ! $data['contifico_is_active']
        ) {
            $this->fail(
                'contifico_stock_sync_enabled',
                'Activa la integración antes de habilitar la sincronización de stock.',
            );
        }

        $tenantLimit = $data['manual_bulk_syncs_per_day'];
        $planLimit = $account->manual_bulk_syncs_per_day;

        if ($planLimit !== null && $tenantLimit !== null && $tenantLimit > $planLimit) {
            $this->fail(
                'manual_bulk_syncs_per_day',
                "El límite tenant no puede superar el límite del plan ({$planLimit}).",
            );
        }
		
		$tenantMinimumInterval = $data['manual_bulk_min_interval_minutes'];
		$planMinimumInterval = $account->manual_bulk_min_interval_minutes;

		if (
			$planMinimumInterval !== null
			&& $tenantMinimumInterval !== null
			&& $tenantMinimumInterval < $planMinimumInterval
		) {
			$this->fail(
				'manual_bulk_min_interval_minutes',
				"El intervalo mínimo del tenant no puede ser menor al definido por el plan ({$planMinimumInterval} minutos).",
			);
		}

        $maximumUserLimit = $tenantLimit ?? $planLimit;

        foreach ($data['user_limits'] ?? [] as $userId => $limit) {
            if (! in_array((int) $userId, $allowedUserIds, true)) {
                $this->fail(
                    "user_limits.{$userId}",
                    'El usuario no pertenece a la cuenta activa.',
                );
            }

            if (
                $limit !== null
                && $maximumUserLimit !== null
                && $limit > $maximumUserLimit
            ) {
                $this->fail(
                    "user_limits.{$userId}",
                    "El límite individual no puede superar {$maximumUserLimit}.",
                );
            }
        }
    }

    private function replaceUserLimits(array $limits, array $allowedUserIds): void
    {
        foreach ($allowedUserIds as $userId) {
            $limit = $limits[$userId] ?? null;

            if ($limit === null || $limit === '') {
                InventoryUserLimit::query()
                    ->where('core_user_id', $userId)
                    ->delete();

                continue;
            }

            InventoryUserLimit::query()->updateOrCreate(
                ['core_user_id' => $userId],
                ['manual_bulk_syncs_per_day' => $limit],
            );
        }
    }

    private function removeLimitsForFormerMembers(array $allowedUserIds): void
    {
        $query = InventoryUserLimit::query();

        if ($allowedUserIds !== []) {
            $query->whereNotIn('core_user_id', $allowedUserIds);
        }

        $query->delete();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}
