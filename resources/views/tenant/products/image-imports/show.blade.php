<x-layouts.tenant
    title="Resultado de importación"
    subtitle="{{ $import->original_filename }}"
>
    @php
        $cards = [
            ['Procesadas', $import->total_count, 'images', 'primary'],
            ['Importadas', $import->imported_count, 'circle-check', 'success'],
            ['Duplicadas', $import->duplicate_count, 'copy', 'secondary'],
            ['Sin coincidencia', $import->unmatched_count, 'circle-help', 'warning'],
            ['Ambiguas', $import->ambiguous_count, 'split', 'danger'],
            ['Fallidas', $import->failed_count, 'triangle-alert', 'danger'],
        ];
    @endphp

    <div class="d-flex justify-content-end gap-2 mb-3">
        <a class="btn btn-light" href="{{ route('products.image-imports.create') }}">
            Historial
        </a>
        <a class="btn btn-primary" href="{{ route('products.index') }}">
            Volver a Productos
        </a>
    </div>

    <div class="row g-3 mb-3">
        @foreach ($cards as [$label, $value, $icon, $color])
            <div class="col-6 col-lg-4 col-xl-2">
                <div class="summary-card h-100">
                    <span class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}">
                        <i data-lucide="{{ $icon }}"></i>
                    </span>
                    <div class="summary-value">{{ $value }}</div>
                    <div class="summary-label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="tr-card mb-3">
        <dl class="row mb-0">
            <dt class="col-md-3">Ejecutada por</dt>
            <dd class="col-md-9">
                {{ $executedBy?->name ?? 'Usuario #' . $import->core_user_id }}
            </dd>

            <dt class="col-md-3">Fecha</dt>
            <dd class="col-md-9">{{ $import->created_at?->format('d/m/Y H:i') }}</dd>

            <dt class="col-md-3">Estado</dt>
            <dd class="col-md-9">{{ str($import->status)->replace('_', ' ')->title() }}</dd>
        </dl>
    </div>

    @if (! empty($import->errors))
        <div class="alert alert-warning">
            <strong>Archivos que no pudieron procesarse:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($import->errors as $error)
                    <li>
                        {{ $error['file'] ?? 'Archivo' }}:
                        {{ $error['message'] ?? 'Error desconocido.' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tr-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Código Catálogo</th>
                        <th>Producto</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($import->preview_rows ?? [] as $row)
                        <tr>
                            <td class="text-break">{{ $row['original_filename'] ?? '—' }}</td>
                            <td>
                                {{ collect($row['catalog_codes'] ?? [])->join(', ') ?: '—' }}
                            </td>
                            <td>{{ $row['product_name'] ?? '—' }}</td>
                            <td>{{ $row['message'] ?? $row['status'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="listing-empty">
                                    No hay detalle disponible para esta importación.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
