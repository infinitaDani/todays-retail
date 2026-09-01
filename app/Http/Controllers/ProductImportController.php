<?php

namespace App\Http\Controllers;

use App\Modules\Products\Models\ProductImport;
use App\Modules\Products\Models\Warehouse;
use App\Modules\Products\Services\ProductExcelImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductImportController extends Controller
{
    public function create(Request $request): View
    {
        return view('tenant.products.import.create', [
            'imports' => ProductImport::query()
                ->where('core_user_id', $request->user()->id)
                ->latest()
                ->paginate(15),
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->with('branch')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function preview(
        Request $request,
        ProductExcelImportService $service
    ): View|RedirectResponse {
        $data = $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant.warehouses', 'id')
                    ->where('is_active', true),
            ],
        ]);

        $account = $request->attributes->get('tenantAccount');
        $file = $data['excel'];
        $path = $file->storeAs(
            "tenants/{$account->id}/product-imports",
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'local'
        );

        try {
            $preview = $service->preview($path);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            return back()->withErrors([
                'excel' => $exception->getMessage(),
            ]);
        }

        $import = ProductImport::create([
            'core_user_id' => $request->user()->id,
            'status' => 'previewed',
            'excel_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'total_count' => $preview['summary']['total'],
            'existing_count' => $preview['summary']['existing'],
            'warning_count' => $preview['summary']['warning'],
            'error_count' => $preview['summary']['error'],
            'errors' => collect($preview['rows'])
                ->where('status', 'error')
                ->take(100)
                ->map(fn (array $row) => [
                    'row' => $row['row_number'],
                    'sku' => $row['sku'],
                    'message' => implode(' ', $row['messages']),
                ])
                ->values()
                ->all(),
            'warehouse_id' => $data['warehouse_id'] ?? null,
        ]);
        $import->load('warehouse.branch');

        return view('tenant.products.import.preview', [
            'import' => $import,
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
        ]);
    }

    public function store(
        Request $request,
        ProductImport $productImport,
        ProductExcelImportService $service
    ): RedirectResponse {
        $this->ensureOwner($request, $productImport);

        if ($productImport->status !== 'previewed') {
            return redirect()
                ->route('products.imports.create')
                ->withErrors(['import' => 'Esta importación ya fue procesada.']);
        }

        try {
            $result = $service->import($productImport);
        } catch (\Throwable $exception) {
            $productImport->update([
                'status' => 'failed',
                'errors' => [['message' => $exception->getMessage()]],
            ]);

            return redirect()
                ->route('products.imports.create')
                ->withErrors(['import' => 'No se pudo procesar el archivo: ' . $exception->getMessage()]);
        }

        $productImport->update([
            'status' => 'completed',
            'processed_count' => $result['total'],
            'created_count' => $result['created'],
            'updated_count' => 0,
            'existing_count' => $result['existing'],
            'warning_count' => $result['warnings'],
            'error_count' => count($result['errors']),
            'errors' => $result['errors'],
        ]);

        return redirect()
            ->route('products.imports.show', $productImport)
            ->with('success', 'Importación completada.');
    }

    public function show(Request $request, ProductImport $productImport): View
    {
        $this->ensureOwner($request, $productImport);

        return view('tenant.products.import.show', [
            'import' => $productImport,
        ]);
    }

    private function ensureOwner(Request $request, ProductImport $productImport): void
    {
        if ((int) $productImport->core_user_id !== (int) $request->user()->id) {
            throw new AuthorizationException('No puedes acceder a una importación de otro usuario.');
        }
    }
}
