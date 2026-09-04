<?php

namespace App\Http\Controllers;

use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Services\InventoryAccess;
use App\Modules\Inventory\Services\InventoryDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        InventoryAccess $access,
        InventoryDashboard $dashboard,
    ): View {
        $scope = $access->scope($request);

        return view('tenant.inventory.dashboard', [
            'summary' => $dashboard->summary($scope, $access),
            'inventorySettings' => InventorySetting::current(),
            'contificoSettings' => ContificoSetting::current(),
            'canImportStock' => $access->canImportStock($scope),
            'canConfigure' => $access->isAccountAdministrator($scope),
            'canSynchronize' => $access->canSynchronize($scope),
        ]);
    }
}
