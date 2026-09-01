<?php

namespace App\Modules\Products\Services;

use App\Modules\Operations\Models\Branch;
use App\Modules\Products\Models\InventoryStock;
use App\Modules\Products\Models\InventoryStockImport;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class InventoryStockImportService
{
    public const MAX_FILE_KILOBYTES = 20480;

    public const MAX_ROWS = 20000;

    private const CODE_HEADERS = [
        'codigo',
        'codigo_producto',
        'code',
        'sku',
    ];

    private const STOCK_HEADERS = [
        'stock',
        'existencia',
        'existencias',
        'stock_actual',
    ];

    public function analyze(InventoryStockImport $import): array
    {
        $warehouse = $this->validDestination($import);
        $rows = $this->readRows($import->excel_path);
        $skus = collect($rows)
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values();

        $variants = ProductVariant::query()
            ->whereIn('sku', $skus)
            ->get(['id', 'sku']);
        $stocks = InventoryStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->get(['id', 'product_variant_id', 'quantity']);

        return $this->classifyRows($rows, $variants, $stocks);
    }

    public function import(InventoryStockImport $import): array
    {
        $preview = $this->analyze($import);
        $warehouse = $this->validDestination($import);
        $updated = 0;
        $unchanged = 0;

        DB::connection('tenant')->transaction(
            function () use (
                $preview,
                $warehouse,
                &$updated,
                &$unchanged,
            ): void {
                foreach ($preview['rows'] as $row) {
                    if (! in_array($row['status'], ['update', 'unchanged'], true)) {
                        continue;
                    }

                    $quantityChanged = $this->recordAuthoritativeStock(
                        $warehouse,
                        $row,
                    );

                    if ($quantityChanged) {
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
            }
        );

        return [
            'summary' => array_merge($preview['summary'], [
                'updated' => $updated,
                'unchanged' => $unchanged,
            ]),
            'rows' => $preview['rows'],
            'errors' => $preview['errors'],
        ];
    }

    public function classifyRows(
        array $rows,
        Collection $variants,
        Collection $stocks,
    ): array {
        $occurrences = collect($rows)
            ->pluck('sku')
            ->filter(fn (?string $sku): bool => $sku !== null && $sku !== '')
            ->countBy();
        $stocksByVariant = $stocks->keyBy('product_variant_id');
        $classified = [];

        foreach ($rows as $row) {
            $messages = $row['errors'];
            $sku = $row['sku'];

            if ($sku !== '' && ($occurrences[$sku] ?? 0) > 1) {
                $messages[] = 'El Código está duplicado dentro del archivo.';
            }

            $variant = $variants->first(
                fn (ProductVariant $candidate): bool => $candidate->sku === $sku
            );
            $stock = $variant
                ? $stocksByVariant->get($variant->id)
                : null;
            $currentQuantity = $stock
                ? $this->decimal((string) $stock->quantity)
                : null;

            if ($messages !== []) {
                $status = 'error';
            } elseif (! $variant) {
                $status = 'not_found';
                $messages[] = 'No existe una variante con este Código/SKU.';
            } elseif ($stock && $currentQuantity === $row['file_quantity']) {
                $status = 'unchanged';
            } else {
                $status = 'update';
            }

            $classified[] = array_merge($row, [
                'variant_id' => $variant?->id,
                'current_quantity' => $currentQuantity,
                'status' => $status,
                'messages' => $messages,
            ]);
        }

        return [
            'rows' => $classified,
            'summary' => [
                'total' => count($classified),
                'update' => collect($classified)->where('status', 'update')->count(),
                'unchanged' => collect($classified)->where('status', 'unchanged')->count(),
                'not_found' => collect($classified)->where('status', 'not_found')->count(),
                'error' => collect($classified)->where('status', 'error')->count(),
            ],
            'errors' => $this->errorsFromRows($classified),
        ];
    }

    public function cleanup(InventoryStockImport $import): void
    {
        if ($import->excel_path) {
            Storage::disk('local')->delete($import->excel_path);
            $import->update(['excel_path' => null]);
        }
    }

    private function readRows(?string $path): array
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException(
                'El archivo temporal ya no está disponible. Vuelve a cargarlo.'
            );
        }

        $sheet = IOFactory::load(Storage::disk('local')->path($path))
            ->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);
        $headerRow = null;
        $codeColumn = null;
        $stockColumn = null;

        foreach (array_slice($rawRows, 0, 20, true) as $index => $cells) {
            $headers = array_map(
                fn ($value): string => $this->header((string) $value),
                $cells,
            );
            $possibleCodeColumn = $this->headerColumn($headers, self::CODE_HEADERS);
            $possibleStockColumn = $this->headerColumn($headers, self::STOCK_HEADERS);

            if ($possibleCodeColumn !== null && $possibleStockColumn !== null) {
                $headerRow = $index;
                $codeColumn = $possibleCodeColumn;
                $stockColumn = $possibleStockColumn;

                break;
            }
        }

        if ($headerRow === null) {
            throw new RuntimeException(
                'No se encontraron los encabezados Código y Stock dentro de las primeras 20 filas.'
            );
        }

        $rows = [];

        foreach (array_slice($rawRows, $headerRow + 1, null, true) as $index => $cells) {
            $rawSku = trim((string) ($cells[$codeColumn] ?? ''));
            $rawQuantity = trim((string) ($cells[$stockColumn] ?? ''));

            if ($rawSku === '' && $rawQuantity === '') {
                continue;
            }

            $errors = [];
            $quantity = $this->quantity($rawQuantity, $errors);

            if ($rawSku === '') {
                $errors[] = 'Código es obligatorio.';
            }

            $rows[] = [
                'row_number' => $index + 1,
                'sku' => $rawSku,
                'file_quantity' => $quantity,
                'errors' => $errors,
            ];

            if (count($rows) > self::MAX_ROWS) {
                throw new RuntimeException(
                    'El archivo supera el máximo permitido de ' . self::MAX_ROWS . ' filas.'
                );
            }
        }

        if ($rows === []) {
            throw new RuntimeException('El archivo no contiene filas de stock para procesar.');
        }

        return $rows;
    }

    private function validDestination(InventoryStockImport $import): Warehouse
    {
        $branch = Branch::query()
            ->whereKey($import->branch_id)
            ->where('status', 'active')
            ->first();

        if (! $branch) {
            throw new RuntimeException('La sucursal seleccionada ya no está activa o disponible.');
        }

        $warehouse = Warehouse::query()
            ->whereKey($import->warehouse_id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->first();

        if (! $warehouse) {
            throw new RuntimeException(
                'La bodega seleccionada no pertenece a la sucursal o ya no está activa.'
            );
        }

        return $warehouse;
    }

    private function recordAuthoritativeStock(
        Warehouse $warehouse,
        array $row,
    ): bool {
        $variant = ProductVariant::query()
            ->where('sku', $row['sku'])
            ->lockForUpdate()
            ->get()
            ->first(
                fn (ProductVariant $candidate): bool => $candidate->sku === $row['sku']
            );

        if (! $variant) {
            throw new RuntimeException(
                "El SKU {$row['sku']} dejó de estar disponible antes de confirmar."
            );
        }

        $stock = InventoryStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->first();
        $quantityChanged = ! $stock
            || $this->decimal((string) $stock->quantity) !== $row['file_quantity'];
        $values = [
            'quantity' => $row['file_quantity'],
            'last_synced_at' => now(),
            'sync_source' => 'excel_import',
        ];

        if ($stock) {
            $stock->update($values);

            return $quantityChanged;
        }

        InventoryStock::create(array_merge($values, [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
        ]));

        return true;
    }

    private function quantity(string $value, array &$errors): ?string
    {
        if ($value === '') {
            $errors[] = 'Stock es obligatorio; cero debe escribirse como 0.';

            return null;
        }

        $normalized = $this->numericValue($value);

        if (! is_numeric($normalized)) {
            $errors[] = 'Stock no es un número válido.';

            return null;
        }

        $quantity = (float) $normalized;

        if (abs($quantity) > 99999999999.999) {
            $errors[] = 'Stock está fuera del rango numérico permitido.';

            return null;
        }

        return $this->decimal($normalized);
    }

    private function headerColumn(array $headers, array $accepted): ?int
    {
        foreach ($headers as $column => $header) {
            if (in_array($header, $accepted, true)) {
                return $column;
            }
        }

        return null;
    }

    private function header(string $value): string
    {
        return trim(
            preg_replace(
                '/[^a-z0-9]+/',
                '_',
                mb_strtolower(Str::ascii(trim($value))),
            ),
            '_',
        );
    }

    private function numericValue(string $value): string
    {
        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                return str_replace(',', '.', str_replace('.', '', $value));
            }

            return str_replace(',', '', $value);
        }

        return str_replace(',', '.', $value);
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    private function errorsFromRows(array $rows): array
    {
        return collect($rows)
            ->filter(
                fn (array $row): bool => in_array(
                    $row['status'],
                    ['error', 'not_found'],
                    true,
                )
            )
            ->map(fn (array $row): array => [
                'row' => $row['row_number'],
                'sku' => $row['sku'],
                'message' => implode(' ', $row['messages']),
            ])
            ->values()
            ->all();
    }
}
