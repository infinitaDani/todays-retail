<x-layouts.tenant
    title="Vista previa de stock"
    subtitle="Confirma el reemplazo de existencias en la bodega seleccionada"
>
    @php
        $cards = [
            ['Total procesados', $import->processed_count, 'rows-3', 'primary'],
            ['A actualizar', $import->updated_count, 'refresh-cw', 'warning'],
            ['Sin cambios', $import->unchanged_count, 'circle-check', 'success'],
            ['SKU no encontrados', $import->not_found_count, 'circle-help', 'secondary'],
            ['Errores', $import->error_count, 'triangle-alert', 'danger'],
        ];
        $statusLabels = [
            'update' => 'Actualizar',
            'unchanged' => 'Sin cambio',
            'not_found' => 'SKU no encontrado',
            'error' => 'Error',
        ];
        $statusColors = [
            'update' => 'warning',
            'unchanged' => 'success',
            'not_found' => 'secondary',
            'error' => 'danger',
        ];
    @endphp

    <div class="row g-3 mb-3">
        @foreach ($cards as [$label, $value, $icon, $color])
            <div class="col-6 col-lg-4 col-xl">
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
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
            <div>
                <strong>{{ $import->original_filename }}</strong>
                <div class="text-muted small">
                    {{ $import->branch?->name }} — {{ $import->warehouse?->name }}
                </div>
                <div class="text-muted small">
                    Al confirmar se volverá a leer el archivo almacenado y a validar el destino.
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <form
                    method="POST"
                    action="{{ route('products.stock-imports.cancel', $import) }}"
                >
                    @csrf
                    @method('DELETE')

                    <button class="btn btn-light" type="submit">Cancelar</button>
                </form>

                <form
                    method="POST"
                    action="{{ route('products.stock-imports.store', $import) }}"
                    onsubmit="return confirm('¿Confirmas reemplazar el stock de esta bodega con los valores válidos del archivo?')"
                >
                    @csrf

                    <button class="btn btn-primary" type="submit">
                        <i data-lucide="database-zap"></i>
                        Confirmar importación
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if ($import->error_count > 0 || $import->not_found_count > 0)
        <div class="alert alert-warning">
            <strong>Filas que no se procesarán:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($import->errors ?? [] as $error)
                    <li>
                        Fila {{ $error['row'] ?? '—' }} ·
                        {{ $error['sku'] ?: 'Sin Código' }}:
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
                        <th>Fila</th>
                        <th>Código</th>
                        <th>Stock actual</th>
                        <th>Stock archivo</th>
                        <th>Resultado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($import->preview_rows ?? [] as $row)
                        @php
                            $status = $row['status'] ?? 'error';
                        @endphp

                        <tr>
                            <td>{{ $row['row_number'] }}</td>
                            <td>{{ $row['sku'] ?: '—' }}</td>
                            <td>
                                {{ $row['current_quantity'] !== null ? $row['current_quantity'] : 'Sin registro' }}
                            </td>
                            <td>
                                {{ $row['file_quantity'] !== null ? $row['file_quantity'] : '—' }}
                            </td>
                            <td>
                                <span class="badge badge-soft-{{ $statusColors[$status] ?? 'secondary' }}">
                                    {{ $statusLabels[$status] ?? $status }}
                                </span>
                            </td>
                            <td class="text-muted">
                                @if ($status === 'update')
                                    Se reemplazará por {{ $row['file_quantity'] }}.
                                @elseif ($status === 'unchanged')
                                    La cantidad ya coincide.
                                @else
                                    {{ implode(' ', $row['messages'] ?? []) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
