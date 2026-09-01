<?php

namespace App\Http\Controllers;

use App\Core\Users\User;
use App\Modules\Products\Models\ProductImageImport;
use App\Modules\Products\Services\ProductImageZipImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductImageImportController extends Controller
{
    public function create(
        ProductImageZipImportService $service
    ): View {
        $service->purgeExpiredPreviews();

        $imports = ProductImageImport::query()
            ->latest()
            ->paginate(15);
        $userNames = User::query()
            ->whereIn('id', $imports->getCollection()->pluck('core_user_id')->unique())
            ->pluck('name', 'id');

        return view('tenant.products.image-imports.create', [
            'imports' => $imports,
            'userNames' => $userNames,
            'maxArchiveMegabytes' => (int) (
                ProductImageZipImportService::MAX_ARCHIVE_KILOBYTES / 1024
            ),
        ]);
    }

    public function preview(
        Request $request,
        ProductImageZipImportService $service
    ): View|RedirectResponse {
        $data = $request->validate([
            'zip' => [
                'required',
                'file',
                'extensions:zip',
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
                'max:' . ProductImageZipImportService::MAX_ARCHIVE_KILOBYTES,
            ],
        ], [
            'zip.extensions' => 'Selecciona un archivo con extensión .zip.',
            'zip.mimetypes' => 'El archivo seleccionado no parece ser un ZIP válido.',
            'zip.max' => 'El ZIP no puede superar 100 MB.',
        ]);

        $account = $request->attributes->get('tenantAccount');
        $identifier = (string) Str::uuid();
        $temporaryDirectory = "tenants/{$account->id}/product-image-imports/{$identifier}";
        $zipPath = $data['zip']->storeAs(
            $temporaryDirectory,
            'source.zip',
            'local'
        );

        $import = ProductImageImport::create([
            'core_user_id' => $request->user()->id,
            'status' => 'analyzing',
            'zip_path' => $zipPath,
            'temporary_directory' => $temporaryDirectory,
            'original_filename' => Str::limit(
                $data['zip']->getClientOriginalName(),
                255,
                ''
            ),
        ]);

        try {
            $preview = $service->analyze($import);
        } catch (\Throwable $exception) {
            $service->cleanup($import);
            $import->update([
                'status' => 'failed',
                'failed_count' => 1,
                'errors' => [['message' => $exception->getMessage()]],
            ]);

            return redirect()
                ->route('products.image-imports.create')
                ->withErrors(['zip' => $exception->getMessage()]);
        }

        $import->update([
            'status' => 'previewed',
            'total_count' => $preview['summary']['total'],
            'matched_count' => $preview['summary']['matched'],
            'unmatched_count' => $preview['summary']['unmatched'],
            'ambiguous_count' => $preview['summary']['ambiguous'],
            'duplicate_count' => $preview['summary']['duplicate'],
            'invalid_count' => $preview['summary']['invalid'],
            'preview_rows' => $preview['rows'],
        ]);

        return view('tenant.products.image-imports.preview', [
            'import' => $import->fresh(),
        ]);
    }

    public function store(
        Request $request,
        ProductImageImport $productImageImport,
        ProductImageZipImportService $service
    ): RedirectResponse {
        if (! $this->claimForProcessing($productImageImport)) {
            return redirect()
                ->route('products.image-imports.show', $productImageImport)
                ->withErrors([
                    'import' => 'Esta importación ya fue procesada o dejó de estar disponible.',
                ]);
        }

        $account = $request->attributes->get('tenantAccount');

        try {
            $result = $service->import($productImageImport, (int) $account->id);
        } catch (\Throwable $exception) {
            $service->cleanup($productImageImport);
            $productImageImport->update([
                'status' => 'failed',
                'failed_count' => 1,
                'errors' => [['message' => $exception->getMessage()]],
                'completed_at' => now(),
            ]);

            return redirect()
                ->route('products.image-imports.show', $productImageImport)
                ->withErrors([
                    'import' => 'No se pudo completar la importación: '
                        . $exception->getMessage(),
                ]);
        }

        $productImageImport->update([
            'status' => $result['failed'] > 0
                ? 'completed_with_errors'
                : 'completed',
            'imported_count' => $result['imported'],
            'duplicate_count' => $productImageImport->duplicate_count
                + $result['duplicates'],
            'failed_count' => $result['failed'],
            'preview_rows' => $result['rows'],
            'errors' => $result['errors'],
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('products.image-imports.show', $productImageImport)
            ->with('success', 'La importación de imágenes terminó correctamente.');
    }

    public function show(ProductImageImport $productImageImport): View
    {
        if ($productImageImport->status === 'previewed') {
            return view('tenant.products.image-imports.preview', [
                'import' => $productImageImport,
            ]);
        }

        return view('tenant.products.image-imports.show', [
            'import' => $productImageImport,
            'executedBy' => User::query()->find($productImageImport->core_user_id),
        ]);
    }

    public function cancel(
        ProductImageImport $productImageImport,
        ProductImageZipImportService $service
    ): RedirectResponse {
        if (in_array($productImageImport->status, ['analyzing', 'previewed'], true)) {
            $service->cleanup($productImageImport);
            $productImageImport->update(['status' => 'cancelled']);
        }

        return redirect()
            ->route('products.image-imports.create')
            ->with('success', 'La importación pendiente fue cancelada.');
    }

    private function claimForProcessing(ProductImageImport $import): bool
    {
        return DB::connection('tenant')->transaction(
            function () use ($import): bool {
                $lockedImport = ProductImageImport::query()
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
}
