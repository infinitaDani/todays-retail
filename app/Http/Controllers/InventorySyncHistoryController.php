<?php

namespace App\Http\Controllers;

use App\Core\Users\User;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Inventory\Services\InventoryAccess;
use App\Modules\Products\Models\InventoryStockImport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySyncHistoryController extends Controller
{
    public function index(
        Request $request,
        InventoryAccess $access,
    ): View {
        $scope = $access->scope($request);
        $access->authorizeSynchronization($scope);
        $executions = $this->scopedExecutions($scope, $access)
            ->with(['branch', 'warehouse'])
            ->latest()
            ->paginate(20);
        $stockImports = InventoryStockImport::query()
            ->with(['branch', 'warehouse'])
            ->when(
                ! $access->canViewAllWarehouses($scope),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $scope['branch_id'],
                ),
            )
            ->latest()
            ->limit(20)
            ->get();
        $userIds = $executions->getCollection()
            ->pluck('requested_by_core_user_id')
            ->merge($stockImports->pluck('core_user_id'))
            ->unique();

        return view('tenant.inventory.history', [
            'executions' => $executions,
            'stockImports' => $stockImports,
            'userNames' => User::query()
                ->whereIn('id', $userIds)
                ->pluck('name', 'id'),
            'currentUserId' => (int) $request->user()->id,
            'canImportStock' => $access->canImportStock($scope),
        ]);
    }

    public function show(
        Request $request,
        InventorySyncExecution $execution,
        InventoryAccess $access,
    ): View {
        $scope = $access->scope($request);
        $access->authorizeSynchronization($scope);

        if (
            ! $access->canViewAllWarehouses($scope)
            && (int) $execution->branch_id !== (int) $scope['branch_id']
        ) {
            throw new AuthorizationException(
                'No tienes acceso a esta ejecución de inventario.',
            );
        }

        $execution->load([
            'branch',
            'warehouse',
        ]);
        $items = $execution->items()
            ->with([
                'warehouse.branch',
                'product:id,name',
                'variant:id,product_id,sku',
            ])
            ->orderBy('id')
            ->paginate(100);

        return view('tenant.inventory.sync-executions.show', [
            'execution' => $execution,
            'items' => $items,
            'requestedBy' => User::query()->find(
                $execution->requested_by_core_user_id,
            ),
        ]);
    }

    private function scopedExecutions(
        array $scope,
        InventoryAccess $access,
    ): Builder {
        return InventorySyncExecution::query()
            ->when(
                ! $access->canViewAllWarehouses($scope),
                fn (Builder $query): Builder => $query->where(
                    'branch_id',
                    $scope['branch_id'],
                ),
            );
    }
}
