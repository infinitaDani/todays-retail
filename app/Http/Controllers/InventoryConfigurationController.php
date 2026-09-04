<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInventoryConfigurationRequest;
use App\Modules\Inventory\Models\ContificoSetting;
use App\Modules\Inventory\Models\InventorySetting;
use App\Modules\Inventory\Models\InventoryUserLimit;
use App\Modules\Inventory\Services\ContificoClient;
use App\Modules\Inventory\Services\InventoryConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class InventoryConfigurationController extends Controller
{
    public function edit(Request $request): View
    {
        $account = $request->attributes->get('tenantAccount');
        $users = $account->users()->orderBy('name')->get();

        return view('tenant.inventory.contifico', [
            'account' => $account,
            'inventorySettings' => InventorySetting::current(),
            'contificoSettings' => ContificoSetting::current(),
            'users' => $users,
            'userLimits' => InventoryUserLimit::query()
                ->whereIn('core_user_id', $users->pluck('id'))
                ->pluck('manual_bulk_syncs_per_day', 'core_user_id'),
        ]);
    }

    public function update(
        UpdateInventoryConfigurationRequest $request,
        InventoryConfigurationService $service,
    ): RedirectResponse {
        $account = $request->attributes->get('tenantAccount');
        $data = $request->validated();
        $request->request->remove('api_key');
        $userIds = $account->users()
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $service->update($account, $data, $userIds);

        return back()->with('success', 'Configuración de Inventario guardada.');
    }

    public function test(
        Request $request,
        ContificoClient $client,
    ): RedirectResponse {
        $account = $request->attributes->get('tenantAccount');

        try {
            $client->testConnection(
                $account,
                ContificoSetting::current(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'connection' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Conexión con Contífico verificada correctamente.');
    }
}
