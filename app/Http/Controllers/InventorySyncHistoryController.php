<?php

namespace App\Http\Controllers;

use App\Core\Users\User;
use App\Modules\Inventory\Models\InventorySyncExecution;
use App\Modules\Products\Models\InventoryStockImport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySyncHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $executions = InventorySyncExecution::query()
            ->with(['branch', 'warehouse'])
            ->latest()
            ->paginate(20);
        $stockImports = InventoryStockImport::query()
            ->with(['branch', 'warehouse'])
            ->latest()
            ->limit(20)
            ->get();
        $userIds = $executions->getCollection()
            ->pluck('requested_by_core_user_id')
            ->merge($stockImports->pluck('core_user_id'))
            ->unique();
        $users = User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        return view('tenant.inventory.history', [
            'executions' => $executions,
            'stockImports' => $stockImports,
            'userNames' => $users,
            'currentUserId' => (int) $request->user()->id,
        ]);
    }
}
