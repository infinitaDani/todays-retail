<x-layouts.tenant
    title="Vista previa de imágenes"
    subtitle="Revisa las asociaciones antes de confirmar"
>
    @php
        $cards = [
            ['Total encontradas', $import->total_count, 'images', 'primary'],
            ['Asociadas', $import->matched_count, 'circle-check', 'success'],
            ['Sin coincidencia', $import->unmatched_count, 'circle-help', 'warning'],
            ['Ambiguas', $import->ambiguous_count, 'split', 'danger'],
            ['Duplicadas', $import->duplicate_count, 'copy', 'secondary'],
            ['Inválidas / ignoradas', $import->invalid_count, 'file-x', 'secondary'],
        ];
        $statusLabels = [
            'ready' => 'Lista para importar',
            'unmatched' => 'Sin coincidencia',
            'ambiguous' => 'Coincidencia ambigua',
            'duplicate' => 'Duplicada',
            'invalid' => 'Archivo inválido',
        ];
        $statusColors = [
            'ready' => 'success',
            'unmatched' => 'warning',
            'ambiguous' => 'danger',
            'duplicate' => 'secondary',
            'invalid' => 'secondary',
        ];
    @endphp

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
        <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <strong>{{ $import->original_filename }}</strong>
                <div class="text-muted small">
                    Solo se importarán las filas con estado “Lista para importar”.
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <form
                    method="POST"
                    action="{{ route('products.image-imports.cancel', $import) }}"
                >
                    @csrf
                    @method('DELETE')

                    <button class="btn btn-light" type="submit">Cancelar</button>
                </form>

                @if ($import->matched_count > 0)
                    <form
                        method="POST"
                        action="{{ route('products.image-imports.store', $import) }}"
                    >
                        @csrf

                        <button class="btn btn-primary" type="submit">
                            <i data-lucide="upload-cloud"></i>
                            Importar {{ $import->matched_count }} imágenes
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Código Catálogo detectado</th>
                        <th>Producto</th>
                        <th>Estado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($import->preview_rows ?? [] as $row)
                        @php
                            $status = $row['status'] ?? 'invalid';
                        @endphp

                        <tr>
                            <td class="text-break">{{ $row['original_filename'] ?? '—' }}</td>
                            <td>
                                {{ collect($row['catalog_codes'] ?? [])->join(', ') ?: '—' }}
                            </td>
                            <td>{{ $row['product_name'] ?? '—' }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $statusColors[$status] ?? 'secondary' }}">
                                    {{ $statusLabels[$status] ?? $status }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $row['message'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
