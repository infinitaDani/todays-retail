<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Products\Models\Warehouse;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryAccess
{
    public function __construct(
        private readonly TenantOperationalScope $operationalScope,
    ) {
    }

    public function scope(Request $request): array
    {
        return $request->attributes->get('tenantOperationalScope')
            ?? $this->operationalScope->for(
                $request->user(),
                $request->attributes->get('tenantAccount'),
            );
    }

    public function isAccountAdministrator(array $scope): bool
    {
        return (bool) ($scope['is_account_administrator'] ?? false);
    }

    public function canImportStock(array $scope): bool
    {
        return $this->canViewAllWarehouses($scope);
    }

    public function canViewAllWarehouses(array $scope): bool
    {
        return $this->isAccountAdministrator($scope)
            || $scope['role'] === TenantOperationalScope::MANAGEMENT;
    }

    public function visibleWarehouses(array $scope): Builder
    {
        return Warehouse::query()
            ->when(
                ! $this->canViewAllWarehouses($scope),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $scope['branch_id'],
                ),
            );
    }

    public function authorizeWarehouseView(
        Warehouse $warehouse,
        array $scope,
    ): void {
        if (
            ! $this->canViewAllWarehouses($scope)
            && (int) $warehouse->branch_id !== (int) $scope['branch_id']
        ) {
            throw new AuthorizationException(
                'No tienes acceso a bodegas de otra sucursal.',
            );
        }
    }

    public function authorizeAccountAdministrator(array $scope): void
    {
        if (! $this->isAccountAdministrator($scope)) {
            throw new AuthorizationException(
                'Solo Account Administrator puede modificar esta configuración.',
            );
        }
    }
}
