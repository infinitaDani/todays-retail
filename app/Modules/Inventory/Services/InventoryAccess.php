<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Products\Models\Warehouse;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    public function canSynchronize(array $scope): bool
    {
        return $this->isAccountAdministrator($scope)
            || in_array(
                $scope['role'] ?? null,
                [
                    TenantOperationalScope::MANAGEMENT,
                    TenantOperationalScope::STORE_ADMIN,
                ],
                true,
            );
    }

    public function authorizeSynchronization(array $scope): void
    {
        if (! $this->canSynchronize($scope)) {
            throw new AuthorizationException(
                'No tienes permiso para sincronizar inventario.',
            );
        }
    }

    public function synchronizedWarehouses(
        array $scope,
        ?int $warehouseId,
    ): Collection {
        $this->authorizeSynchronization($scope);

        $warehouses = $this->visibleWarehouses($scope)
            ->with('branch:id,name')
            ->where('is_active', true)
            ->when(
                $warehouseId !== null,
                fn (Builder $query): Builder => $query->whereKey($warehouseId),
            )
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();

        if ($warehouseId !== null && $warehouses->isEmpty()) {
            throw new AuthorizationException(
                'La bodega seleccionada no pertenece a tu alcance autorizado.',
            );
        }

        if ($warehouses->isEmpty()) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'No existen bodegas activas dentro de tu alcance.',
            ]);
        }

        return $warehouses;
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
