<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunInventorySkuSyncRequest;
use App\Http\Requests\RunInventorySyncRequest;
use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Inventory\Models\InventoryUserLimit;
use App\Modules\Inventory\Services\InventoryAccess;
use App\Modules\Inventory\Services\InventorySynchronizationService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventorySyncController extends Controller
{
    public function index(
        Request $request,
        InventoryAccess $access,
    ): View {
        $scope = $access->scope($request);
        $access->authorizeSynchronization($scope);
        $account = $request->attributes->get('tenantAccount');
        $canConfigure = $access->isAccountAdministrator($scope);
        $users = $canConfigure
            ? $account->users()->orderBy('name')->get()
            : collect();
        $latestExecution = InventorySyncExecution::query()
            ->with(['branch', 'warehouse'])
            ->when(
                ! $access->canViewAllWarehouses($scope),
                fn ($query) => $query->where('branch_id', $scope['branch_id']),
            )
            ->latest()
            ->first();

        return view('tenant.inventory.contifico', [
            'account' => $account,
            'inventorySettings' => InventorySetting::current(),
            'contificoSettings' => ContificoSetting::current(),
            'canConfigure' => $canConfigure,
            'warehouses' => $access->visibleWarehouses($scope)
                ->with('branch:id,name')
                ->where('is_active', true)
                ->orderBy('branch_id')
                ->orderBy('name')
                ->get(),
            'latestExecution' => $latestExecution,
            'users' => $users,
            'userLimits' => InventoryUserLimit::query()
                ->whereIn('core_user_id', $users->pluck('id'))
                ->pluck('manual_bulk_syncs_per_day', 'core_user_id'),
        ]);
    }

    public function bulk(
        RunInventorySyncRequest $request,
        InventoryAccess $access,
        InventorySynchronizationService $service,
    ): RedirectResponse {
        $execution = $service->synchronizeBulk(
            $request->attributes->get('tenantAccount'),
            $request->user(),
            $access->scope($request),
            $request->validated('warehouse_id'),
        );

        return $this->toResult($execution);
    }

    public function product(
        RunInventorySyncRequest $request,
        Product $product,
        InventoryAccess $access,
        InventorySynchronizationService $service,
    ): RedirectResponse {
        $execution = $service->synchronizeProduct(
            $request->attributes->get('tenantAccount'),
            $request->user(),
            $access->scope($request),
            $product,
            $request->validated('warehouse_id'),
        );

        return $this->toResult($execution);
    }

    public function variant(
        RunInventorySyncRequest $request,
        ProductVariant $variant,
        InventoryAccess $access,
        InventorySynchronizationService $service,
    ): RedirectResponse {
        $execution = $service->synchronizeVariant(
            $request->attributes->get('tenantAccount'),
            $request->user(),
            $access->scope($request),
            $variant,
            $request->validated('warehouse_id'),
        );

        return $this->toResult($execution);
    }

    public function sku(
        RunInventorySkuSyncRequest $request,
        InventoryAccess $access,
        InventorySynchronizationService $service,
    ): RedirectResponse {
        $variant = ProductVariant::query()
            ->where('sku', trim($request->validated('sku')))
            ->where('is_active', true)
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'sku' => 'No existe una variante activa con ese SKU.',
            ]);
        }
        $execution = $service->synchronizeVariant(
            $request->attributes->get('tenantAccount'),
            $request->user(),
            $access->scope($request),
            $variant,
            $request->validated('warehouse_id'),
        );

        return $this->toResult($execution);
    }

    private function toResult(
        InventorySyncExecution $execution,
    ): RedirectResponse {
        return redirect()
            ->route('inventory.sync-executions.show', $execution)
            ->with('success', 'Sincronización finalizada.');
    }
}
