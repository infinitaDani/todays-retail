<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Services\InventoryAccess;
use App\Modules\Inventory\Services\WarehouseManager;
use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(
        Request $request,
        InventoryAccess $access,
    ): View {
        $scope = $access->scope($request);
        $baseQuery = $access->visibleWarehouses($scope);
        $settings = InventorySetting::current();
        $query = (clone $baseQuery)
            ->with('branch')
            ->withCount('stocks')
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request): void {
                    $search = $request->string('search')->toString();

                    $query->where(function (Builder $nested) use ($search): void {
                        $nested
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('purpose', 'like', "%{$search}%")
                            ->orWhere('contifico_code', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                $request->filled('branch_id'),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $request->integer('branch_id'),
                ),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query): Builder => $query->where(
                    'is_active',
                    $request->string('status')->toString() === 'active',
                ),
            );

        return view('tenant.inventory.warehouses.index', [
            'warehouses' => $query
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'branches' => $this->visibleBranches($scope, $access),
            'canManage' => $access->isAccountAdministrator($scope)
                && $settings->manages_warehouses,
            'settings' => $settings,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('is_active', true)->count(),
                'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(WarehouseManager $manager): View
    {
        $manager->assertManagementEnabled();

        return view('tenant.inventory.warehouses.form', [
            'branches' => Branch::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(
        StoreWarehouseRequest $request,
        WarehouseManager $manager,
    ): RedirectResponse {
        $warehouse = $manager->create($request->validated());

        return redirect()
            ->route('inventory.warehouses.show', $warehouse)
            ->with('success', 'Bodega creada correctamente.');
    }

    public function show(
        Request $request,
        Warehouse $warehouse,
        InventoryAccess $access,
        WarehouseManager $manager,
    ): View {
        $scope = $access->scope($request);
        $access->authorizeWarehouseView($warehouse, $scope);
        $warehouse->load('branch')->loadCount('stocks');
        $managementEnabled = InventorySetting::current()
            ->manages_warehouses;

        return view('tenant.inventory.warehouses.show', [
            'warehouse' => $warehouse,
            'totalQuantity' => $warehouse->stocks()->sum('quantity'),
            'canManage' => $access->isAccountAdministrator($scope)
                && $managementEnabled,
            'canDelete' => $managementEnabled
                && $manager->canDelete($warehouse),
        ]);
    }

    public function edit(
        Warehouse $warehouse,
        WarehouseManager $manager,
    ): View {
        $manager->assertManagementEnabled();

        return view('tenant.inventory.warehouses.form', [
            'warehouse' => $warehouse,
            'branches' => Branch::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(
        StoreWarehouseRequest $request,
        Warehouse $warehouse,
        WarehouseManager $manager,
    ): RedirectResponse {
        $manager->update($warehouse, $request->validated());

        return redirect()
            ->route('inventory.warehouses.show', $warehouse)
            ->with('success', 'Bodega actualizada correctamente.');
    }

    public function toggle(
        Warehouse $warehouse,
        WarehouseManager $manager,
    ): RedirectResponse {
        $manager->toggle($warehouse);

        return back()->with('success', 'Estado de la bodega actualizado.');
    }

    public function destroy(
        Warehouse $warehouse,
        WarehouseManager $manager,
    ): RedirectResponse {
        $manager->delete($warehouse);

        return redirect()
            ->route('inventory.warehouses.index')
            ->with('success', 'Bodega eliminada correctamente.');
    }

    private function visibleBranches(
        array $scope,
        InventoryAccess $access,
    ): Collection {
        return Branch::query()
            ->where('status', 'active')
            ->when(
                ! $access->canViewAllWarehouses($scope),
                fn (Builder $query): Builder => $query->whereKey(
                    $scope['branch_id'],
                ),
            )
            ->orderBy('name')
            ->get();
    }
}
