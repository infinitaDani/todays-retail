<?php

namespace App\Http\Controllers;

use App\Core\Users\User;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\ScheduleSetting;
use App\Modules\Products\Models\InventoryStockImport;
use App\Modules\Products\Services\InventoryStockImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryStockImportController extends Controller
{
    public function create(Request $request): View
    {
        $settings = ScheduleSetting::current();
        $branches = Branch::query()
            ->where('status', 'active')
            ->with([
                'warehouses' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
        $imports = InventoryStockImport::query()
            ->with(['branch', 'warehouse'])
            ->latest()
            ->paginate(15);
        $userNames = User::query()
            ->whereIn(
                'id',
                $imports->getCollection()->pluck('core_user_id')->unique(),
            )
            ->pluck('name', 'id');

        return view('tenant.products.stock-imports.create', [
            'settings' => $settings,
            'branches' => $branches,
            'imports' => $imports,
            'userNames' => $userNames,
            'currentUserId' => (int) $request->user()->id,
            'maxFileMegabytes' => (int) (
                InventoryStockImportService::MAX_FILE_KILOBYTES / 1024
            ),
        ]);
    }

    public function preview(
        Request $request,
        InventoryStockImportService $service,
    ): View|RedirectResponse {
        $this->ensureInventoryEnabled();

        $data = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('tenant.branches', 'id')
                    ->where('status', 'active'),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('tenant.warehouses', 'id')
                    ->where('branch_id', $request->integer('branch_id'))
                    ->where('is_active', true),
            ],
            'excel' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:' . InventoryStockImportService::MAX_FILE_KILOBYTES,
            ],
        ], [
            'warehouse_id.exists' => 'La bodega no pertenece a la sucursal seleccionada o está inactiva.',
            'excel.mimes' => 'Selecciona un archivo Excel .xlsx o .xls.',
            'excel.max' => 'El archivo no puede superar 20 MB.',
        ]);

        $account = $request->attributes->get('tenantAccount');
        $file = $data['excel'];
        $path = $file->storeAs(
            "tenants/{$account->id}/inventory-stock-imports",
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'local',
        );
        $import = InventoryStockImport::create([
            'core_user_id' => $request->user()->id,
            'branch_id' => $data['branch_id'],
            'warehouse_id' => $data['warehouse_id'],
            'original_filename' => Str::limit(
                $file->getClientOriginalName(),
                255,
                '',
            ),
            'excel_path' => $path,
            'status' => 'analyzing',
        ]);

        try {
            $preview = $service->analyze($import);
        } catch (\Throwable $exception) {
            $service->cleanup($import);
            $import->update([
                'status' => 'failed',
                'error_count' => 1,
                'errors' => [['message' => $exception->getMessage()]],
                'completed_at' => now(),
            ]);

            return redirect()
                ->route('products.stock-imports.create')
                ->withErrors(['excel' => $exception->getMessage()]);
        }

        $import->update([
            'status' => 'previewed',
            'processed_count' => $preview['summary']['total'],
            'updated_count' => $preview['summary']['update'],
            'unchanged_count' => $preview['summary']['unchanged'],
            'not_found_count' => $preview['summary']['not_found'],
            'error_count' => $preview['summary']['error'],
            'preview_rows' => $preview['rows'],
            'errors' => $preview['errors'],
        ]);

        return view('tenant.products.stock-imports.preview', [
            'import' => $import->fresh(['branch', 'warehouse']),
        ]);
    }

    public function store(
        Request $request,
        InventoryStockImport $inventoryStockImport,
        InventoryStockImportService $service,
    ): RedirectResponse {
        $this->ensureOwner($request, $inventoryStockImport);
        $this->ensureInventoryEnabled();

        if (! $this->claimForProcessing($inventoryStockImport)) {
            return redirect()
                ->route('products.stock-imports.show', $inventoryStockImport)
                ->withErrors([
                    'import' => 'Esta importación ya fue confirmada o dejó de estar disponible.',
                ]);
        }

        try {
            $result = $service->import($inventoryStockImport);
        } catch (\Throwable $exception) {
            $service->cleanup($inventoryStockImport);
            $inventoryStockImport->update([
                'status' => 'failed',
                'error_count' => max(1, $inventoryStockImport->error_count),
                'errors' => [['message' => $exception->getMessage()]],
                'completed_at' => now(),
            ]);

            return redirect()
                ->route('products.stock-imports.show', $inventoryStockImport)
                ->withErrors([
                    'import' => 'No se pudo importar el stock: ' . $exception->getMessage(),
                ]);
        }

        $inventoryStockImport->update([
            'status' => 'completed',
            'processed_count' => $result['summary']['total'],
            'updated_count' => $result['summary']['updated'],
            'unchanged_count' => $result['summary']['unchanged'],
            'not_found_count' => $result['summary']['not_found'],
            'error_count' => $result['summary']['error'],
            'preview_rows' => $result['rows'],
            'errors' => $result['errors'],
            'completed_at' => now(),
        ]);
        $service->cleanup($inventoryStockImport);

        return redirect()
            ->route('products.stock-imports.show', $inventoryStockImport)
            ->with('success', 'La importación de stock terminó correctamente.');
    }

    public function show(
        Request $request,
        InventoryStockImport $inventoryStockImport,
    ): View {
        if ($inventoryStockImport->status === 'previewed') {
            $this->ensureOwner($request, $inventoryStockImport);

            return view('tenant.products.stock-imports.preview', [
                'import' => $inventoryStockImport->load(['branch', 'warehouse']),
            ]);
        }

        return view('tenant.products.stock-imports.show', [
            'import' => $inventoryStockImport->load(['branch', 'warehouse']),
            'executedBy' => User::query()->find(
                $inventoryStockImport->core_user_id
            ),
        ]);
    }

    public function cancel(
        Request $request,
        InventoryStockImport $inventoryStockImport,
        InventoryStockImportService $service,
    ): RedirectResponse {
        $this->ensureOwner($request, $inventoryStockImport);

        if (in_array($inventoryStockImport->status, ['analyzing', 'previewed'], true)) {
            $service->cleanup($inventoryStockImport);
            $inventoryStockImport->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
        }

        return redirect()
            ->route('products.stock-imports.create')
            ->with('success', 'La importación pendiente fue cancelada.');
    }

    private function claimForProcessing(InventoryStockImport $import): bool
    {
        return DB::connection('tenant')->transaction(
            function () use ($import): bool {
                $lockedImport = InventoryStockImport::query()
                    ->whereKey($import->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedImport->status !== 'previewed') {
                    return false;
                }

                $lockedImport->update(['status' => 'processing']);
                $import->status = 'processing';

                return true;
            }
        );
    }

    private function ensureOwner(
        Request $request,
        InventoryStockImport $import,
    ): void {
        if ((int) $import->core_user_id !== (int) $request->user()->id) {
            throw new AuthorizationException(
                'Solo quien preparó esta importación puede confirmarla o cancelarla.'
            );
        }
    }

    private function ensureInventoryEnabled(): void
    {
        $settings = ScheduleSetting::current();

        if (! $settings->manages_inventory) {
            throw ValidationException::withMessages([
                'inventory' => 'La gestión de inventario está desactivada para esta cuenta.',
            ]);
        }

        if (! $settings->inventory_by_branch) {
            throw ValidationException::withMessages([
                'inventory' => 'Para importar stock, habilita Inventario por sucursal en Configuración.',
            ]);
        }
    }

}
