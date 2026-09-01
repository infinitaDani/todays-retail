<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Models\ProductImageImport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProductImageZipImportService
{
    public const MAX_ARCHIVE_KILOBYTES = 102400;

    private const MAX_ENTRIES = 1000;

    private const MAX_IMAGE_BYTES = 15728640;

    private const MAX_UNCOMPRESSED_BYTES = 536870912;

    private const MAX_COMPRESSION_RATIO = 100;

    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    private array $legacyImageHashes = [];

    public function analyze(ProductImageImport $import): array
    {
        $this->ensureZipSupport();

        $disk = Storage::disk('local');

        if (! $disk->exists($import->zip_path)) {
            throw new RuntimeException('El archivo ZIP temporal ya no está disponible.');
        }

        $productsByCode = Product::query()
            ->whereNotNull('catalog_code')
            ->where('catalog_code', '!=', '')
            ->get(['id', 'catalog_code', 'name'])
            ->groupBy(fn (Product $product): string => $this->normalize($product->catalog_code));

        $archive = new ZipArchive();
        $opened = $archive->open($disk->path($import->zip_path));

        if ($opened !== true) {
            throw new RuntimeException('El archivo no es un ZIP válido o está dañado.');
        }

        if ($archive->numFiles > self::MAX_ENTRIES) {
            $archive->close();

            throw new RuntimeException(
                'El ZIP supera el máximo permitido de ' . self::MAX_ENTRIES . ' archivos.'
            );
        }

        $rows = [];
        $totalUncompressedBytes = 0;
        $seenHashes = [];

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);

                if (! is_array($stat)) {
                    $rows[] = $this->invalidRow('Entrada desconocida', 'No se pudo leer la entrada del ZIP.');
                    continue;
                }

                $archivePath = (string) ($stat['name'] ?? '');

                if ($this->isDirectory($archivePath)) {
                    continue;
                }

                $originalFilename = $this->safeOriginalFilename($archivePath);
                $totalUncompressedBytes += max(0, (int) ($stat['size'] ?? 0));

                if ($totalUncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException(
                        'El contenido descomprimido del ZIP supera el límite seguro de 512 MB.'
                    );
                }

                if ($this->isSystemFile($archivePath, $originalFilename)) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'Archivo del sistema o miniatura ignorado.'
                    );
                    continue;
                }

                $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'Formato no admitido.'
                    );
                    continue;
                }

                if ((int) ($stat['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'La imagen supera el máximo permitido de 15 MB.'
                    );
                    continue;
                }

                if ($this->hasSuspiciousCompressionRatio($stat)) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'La relación de compresión del archivo no es segura.'
                    );
                    continue;
                }

                $contents = $archive->getFromIndex($index);

                if (! is_string($contents) || $contents === '') {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'No se pudo extraer la imagen.'
                    );
                    continue;
                }

                if (strlen($contents) > self::MAX_IMAGE_BYTES) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'La imagen extraída supera el máximo permitido de 15 MB.'
                    );
                    continue;
                }

                $mimeType = $this->validImageMime($contents, $extension);

                if ($mimeType === null) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'El contenido no corresponde a una imagen válida.'
                    );
                    continue;
                }

                $contentHash = hash('sha256', $contents);
                $matches = $this->matchingProducts($originalFilename, $productsByCode);
                $temporaryPath = $this->temporaryImagePath($import, $extension);

                if (! $disk->put($temporaryPath, $contents)) {
                    $rows[] = $this->invalidRow(
                        $originalFilename,
                        'No se pudo guardar la imagen temporal.'
                    );
                    continue;
                }

                $row = [
                    'archive_path' => Str::limit($archivePath, 500, ''),
                    'original_filename' => Str::limit($originalFilename, 255, ''),
                    'temporary_path' => $temporaryPath,
                    'content_hash' => $contentHash,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'catalog_codes' => $matches->pluck('catalog_code')->unique()->values()->all(),
                    'product_id' => null,
                    'product_name' => $matches->pluck('name')->unique()->join(' / ') ?: null,
                    'status' => 'unmatched',
                    'message' => 'Sin coincidencia.',
                ];

                if ($matches->isEmpty()) {
                    $rows[] = $row;
                    continue;
                }

                if ($matches->pluck('id')->unique()->count() > 1) {
                    $row['status'] = 'ambiguous';
                    $row['message'] = 'Coincidencia ambigua.';
                    $rows[] = $row;
                    continue;
                }

                $product = $matches->first();
                $duplicateKey = $product->id . ':' . $contentHash;
                $alreadyExists = $this->imageHashExists(
                    $product->id,
                    $contentHash
                );

                $row['product_id'] = $product->id;

                if ($alreadyExists || isset($seenHashes[$duplicateKey])) {
                    $row['status'] = 'duplicate';
                    $row['message'] = 'Duplicada.';
                    $rows[] = $row;
                    continue;
                }

                $seenHashes[$duplicateKey] = true;
                $row['status'] = 'ready';
                $row['message'] = 'Lista para importar.';
                $rows[] = $row;
            }
        } finally {
            $archive->close();
        }

        return [
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ];
    }

    public function import(ProductImageImport $import, int $accountId): array
    {
        $rows = $import->preview_rows ?? [];
        $imported = 0;
        $duplicates = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $position => $row) {
            if (($row['status'] ?? null) !== 'ready') {
                continue;
            }

            try {
                $result = $this->importRow($import, $row, $accountId);
                $rows[$position]['status'] = $result;
                $rows[$position]['message'] = $result === 'imported'
                    ? 'Importada.'
                    : 'Duplicada durante la confirmación.';

                if ($result === 'imported') {
                    $imported++;
                } else {
                    $duplicates++;
                }
            } catch (Throwable $exception) {
                $failed++;
                $rows[$position]['status'] = 'failed';
                $rows[$position]['message'] = 'No se pudo importar.';
                $errors[] = [
                    'file' => $row['original_filename'] ?? 'Archivo desconocido',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $this->cleanup($import);

        return [
            'rows' => $rows,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    public function catalogCodesInFilename(
        string $filename,
        iterable $catalogCodes
    ): array {
        $normalizedFilename = $this->normalize($filename);

        return collect($catalogCodes)
            ->map(fn ($code): string => $this->normalize((string) $code))
            ->filter(
				fn (string $code): bool => $code !== ''
					&& preg_match(
						'/(?<![A-Z0-9])' . preg_quote($code, '/') . '(?![A-Z0-9])/u',
						$normalizedFilename
					) === 1
			)
            ->unique()
            ->values()
            ->all();
    }

    public function cleanup(ProductImageImport $import): void
    {
        $disk = Storage::disk('local');
        $disk->delete($import->zip_path);
        $disk->deleteDirectory($import->temporary_directory);
    }

    public function purgeExpiredPreviews(): void
    {
        ProductImageImport::query()
            ->whereIn('status', ['analyzing', 'previewed', 'processing'])
            ->where('created_at', '<', now()->subDay())
            ->get()
            ->each(function (ProductImageImport $import): void {
                $this->cleanup($import);
                $wasProcessing = $import->status === 'processing';
                $import->update([
                    'status' => $wasProcessing ? 'failed' : 'expired',
                    'imported_count' => $import->images()->count(),
                    'failed_count' => $wasProcessing ? 1 : $import->failed_count,
                    'errors' => $wasProcessing
                        ? [['message' => 'La importación fue interrumpida antes de finalizar.']]
                        : $import->errors,
                    'completed_at' => $wasProcessing ? now() : null,
                ]);
            });
    }

    private function importRow(
        ProductImageImport $import,
        array $row,
        int $accountId
    ): string {
        $disk = Storage::disk('local');
        $temporaryPath = (string) ($row['temporary_path'] ?? '');

        if (! $this->belongsToTemporaryDirectory($temporaryPath, $import->temporary_directory)) {
            throw new RuntimeException('La ruta temporal de la imagen no es válida.');
        }

        if (! $disk->exists($temporaryPath)) {
            throw new RuntimeException('La imagen temporal ya no está disponible.');
        }

        $contentHash = hash_file('sha256', $disk->path($temporaryPath));

        if (! is_string($contentHash)) {
            throw new RuntimeException('No se pudo verificar la imagen temporal.');
        }

        if (! hash_equals((string) ($row['content_hash'] ?? ''), $contentHash)) {
            throw new RuntimeException('La imagen cambió después de la vista previa.');
        }

        $product = Product::query()->findOrFail((int) ($row['product_id'] ?? 0));
        $normalizedFilename = $this->normalize((string) ($row['original_filename'] ?? ''));
        $normalizedCatalogCode = $this->normalize((string) $product->catalog_code);

        if ($normalizedCatalogCode === '' || ! str_contains($normalizedFilename, $normalizedCatalogCode)) {
            throw new RuntimeException('El producto ya no coincide con el Código Catálogo detectado.');
        }

        if ($this->imageHashExists($product->id, $contentHash)) {
            return 'duplicate';
        }

        $extension = (string) ($row['extension'] ?? 'jpg');
        $directory = "tenants/{$accountId}/products/{$product->id}/images";
        $permanentPath = $directory . '/' . Str::uuid() . '.' . $extension;

        if (! $disk->move($temporaryPath, $permanentPath)) {
            throw new RuntimeException('No se pudo guardar la imagen en el almacenamiento privado.');
        }

        try {
            ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'product_image_import_id' => $import->id,
                'path' => $permanentPath,
                'original_filename' => $row['original_filename'] ?? null,
                'content_hash' => $contentHash,
                'is_primary' => ! $product->generalImages()->exists(),
                'sort_order' => ((int) $product->generalImages()->max('sort_order')) + 1,
            ]);
        } catch (QueryException $exception) {
            $disk->delete($permanentPath);

            if ($this->isDuplicateException($exception)) {
                return 'duplicate';
            }

            throw $exception;
        } catch (Throwable $exception) {
            $disk->delete($permanentPath);
            throw $exception;
        }

        return 'imported';
    }

    private function matchingProducts(
        string $filename,
        Collection $productsByCode
    ): Collection {
        $matchingCodes = $this->catalogCodesInFilename(
            $filename,
            $productsByCode->keys()
        );

        return $productsByCode
            ->filter(
                fn ($products, $code): bool => in_array(
                    $this->normalize((string) $code),
                    $matchingCodes,
                    true
                )
            )
            ->flatten(1)
            ->unique('id')
            ->values();
    }

    private function summary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total' => $collection->count(),
            'matched' => $collection->where('status', 'ready')->count(),
            'unmatched' => $collection->where('status', 'unmatched')->count(),
            'ambiguous' => $collection->where('status', 'ambiguous')->count(),
            'duplicate' => $collection->where('status', 'duplicate')->count(),
            'invalid' => $collection->where('status', 'invalid')->count(),
        ];
    }

    private function invalidRow(string $filename, string $message): array
    {
        return [
            'archive_path' => null,
            'original_filename' => Str::limit($filename, 255, ''),
            'temporary_path' => null,
            'content_hash' => null,
            'mime_type' => null,
            'extension' => null,
            'catalog_codes' => [],
            'product_id' => null,
            'product_name' => null,
            'status' => 'invalid',
            'message' => $message,
        ];
    }

    private function validImageMime(string $contents, string $extension): ?string
    {
        $information = @getimagesizefromstring($contents);
        $mimeType = is_array($information) ? ($information['mime'] ?? null) : null;

        if (! is_string($mimeType) || ! isset(self::SUPPORTED_MIME_TYPES[$mimeType])) {
            return null;
        }

        return in_array($extension, self::SUPPORTED_MIME_TYPES[$mimeType], true)
            ? $mimeType
            : null;
    }

    private function hasSuspiciousCompressionRatio(array $stat): bool
    {
        $size = max(0, (int) ($stat['size'] ?? 0));
        $compressedSize = max(0, (int) ($stat['comp_size'] ?? 0));

        if ($size === 0) {
            return false;
        }

        if ($compressedSize === 0) {
            return true;
        }

        return ($size / $compressedSize) > self::MAX_COMPRESSION_RATIO;
    }

    private function isDirectory(string $archivePath): bool
    {
        return str_ends_with(str_replace('\\', '/', $archivePath), '/');
    }

    private function isSystemFile(string $archivePath, string $filename): bool
    {
        $normalizedPath = str_replace('\\', '/', $archivePath);
        $segments = array_filter(explode('/', $normalizedPath), 'strlen');
        $lowerFilename = strtolower($filename);

        return in_array('__MACOSX', $segments, true)
            || str_starts_with($filename, '._')
            || in_array($lowerFilename, ['.ds_store', 'thumbs.db', 'desktop.ini'], true)
            || preg_match(
                '/(^|[-_.\s])(thumb|thumbnail|miniatura)([-_.\s]|$)/i',
                $filename
            ) === 1;
    }

    private function safeOriginalFilename(string $archivePath): string
    {
        $normalizedPath = str_replace('\\', '/', str_replace("\0", '', $archivePath));
        $filename = basename($normalizedPath);

        return $filename !== '' ? $filename : 'archivo-sin-nombre';
    }

    private function temporaryImagePath(ProductImageImport $import, string $extension): string
    {
        return trim($import->temporary_directory, '/')
            . '/images/'
            . Str::uuid()
            . '.'
            . $extension;
    }

    private function belongsToTemporaryDirectory(string $path, string $directory): bool
    {
        $prefix = trim($directory, '/') . '/images/';

        return $path !== ''
            && ! str_contains($path, '..')
            && str_starts_with($path, $prefix);
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function isDuplicateException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pimg_product_hash_uq')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint');
    }

    private function imageHashExists(int $productId, string $contentHash): bool
    {
        if (ProductImage::query()
            ->where('product_id', $productId)
            ->where('content_hash', $contentHash)
            ->exists()) {
            return true;
        }

        if (! array_key_exists($productId, $this->legacyImageHashes)) {
            $this->legacyImageHashes[$productId] = ProductImage::query()
                ->where('product_id', $productId)
                ->whereNull('content_hash')
                ->pluck('path')
                ->map(function (string $path): ?string {
                    $disk = Storage::disk('local');

                    if (! $disk->exists($path)) {
                        return null;
                    }

                    $existingHash = @hash_file('sha256', $disk->path($path));

                    return is_string($existingHash) ? $existingHash : null;
                })
                ->filter()
                ->values()
                ->all();
        }

        return collect($this->legacyImageHashes[$productId])
            ->contains(function (string $existingHash) use ($contentHash): bool {
                return hash_equals($contentHash, $existingHash);
            });
    }

    private function ensureZipSupport(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'El servidor necesita la extensión PHP ZIP para procesar archivos comprimidos.'
            );
        }
    }
}
