<?php

namespace App\Modules\Inventory\Services;

use App\Core\Accounts\Account;
use App\Core\Users\User;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Inventory\Models\InventoryUserLimit;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InventoryCommercialLimits
{
    public function effectiveLimits(Account $account, User $user): array
    {
        $settings = InventorySetting::current();
        $userLimit = InventoryUserLimit::query()
            ->where('core_user_id', $user->id)
            ->value('manual_bulk_syncs_per_day');

        return [
            'tenant_daily' => $this->minimum([
                $account->manual_bulk_syncs_per_day,
                $settings->manual_bulk_syncs_per_day,
            ]),
            'user_daily' => $this->minimum([
                $account->manual_bulk_syncs_per_day,
                $settings->manual_bulk_syncs_per_day,
                $userLimit,
            ]),
            'minimum_interval_minutes' => $this->maximum([
                $account->manual_bulk_min_interval_minutes,
                $settings->manual_bulk_min_interval_minutes,
            ]),
        ];
    }

    public function assertManualBulkSyncAllowed(
        Account $account,
        User $user,
    ): void {
        if (! $account->contifico_enabled) {
            $this->fail('El plan de esta cuenta no habilita Contífico.');
        }

        if (! InventorySetting::current()->contifico_stock_sync_enabled) {
            $this->fail('La sincronización de stock con Contífico está desactivada.');
        }

        if (
            InventorySyncExecution::query()
                ->where('type', InventorySyncExecution::TYPE_MANUAL_BULK)
                ->where('status', InventorySyncExecution::STATUS_RUNNING)
                ->where('started_at', '>', now()->subHours(2))
                ->exists()
        ) {
            $this->fail('Ya existe una sincronización masiva en curso.');
        }

        $limits = $this->effectiveLimits($account, $user);
        $today = now()->startOfDay();
        $tenantExecutions = InventorySyncExecution::query()
            ->where('type', InventorySyncExecution::TYPE_MANUAL_BULK)
            ->where('created_at', '>=', $today)
            ->count();
        $userExecutions = InventorySyncExecution::query()
            ->where('type', InventorySyncExecution::TYPE_MANUAL_BULK)
            ->where('requested_by_core_user_id', $user->id)
            ->where('created_at', '>=', $today)
            ->count();

        if (
            $limits['tenant_daily'] !== null
            && $tenantExecutions >= $limits['tenant_daily']
        ) {
            $this->fail('La cuenta alcanzó su límite diario de sincronizaciones masivas.');
        }

        if (
            $limits['user_daily'] !== null
            && $userExecutions >= $limits['user_daily']
        ) {
            $this->fail('Alcanzaste tu límite diario de sincronizaciones masivas.');
        }

        $minimumInterval = $limits['minimum_interval_minutes'];
        $lastExecutionAt = InventorySyncExecution::query()
            ->where('type', InventorySyncExecution::TYPE_MANUAL_BULK)
            ->latest('created_at')
            ->value('created_at');

        if (
            $minimumInterval !== null
            && $lastExecutionAt
            && now()->diffInMinutes(Carbon::parse($lastExecutionAt)) < $minimumInterval
        ) {
            $this->fail(
                "Deben transcurrir {$minimumInterval} minutos entre sincronizaciones masivas.",
            );
        }
    }

    private function minimum(array $limits): ?int
    {
        $configured = collect($limits)
            ->filter(fn ($limit): bool => $limit !== null)
            ->map(fn ($limit): int => (int) $limit);

        return $configured->isEmpty() ? null : $configured->min();
    }

    private function maximum(array $limits): ?int
    {
        $configured = collect($limits)
            ->filter(fn ($limit): bool => $limit !== null)
            ->map(fn ($limit): int => (int) $limit);

        return $configured->isEmpty() ? null : $configured->max();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['sync' => $message]);
    }
}
