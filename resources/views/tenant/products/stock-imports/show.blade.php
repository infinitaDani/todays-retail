<x-layouts.tenant
    title="Resultado de importación de stock"
    subtitle="{{ $import->original_filename }}"
>
    @php
        $cards = [
            ['Procesados', $import->processed_count, 'rows-3', 'primary'],
            ['Actualizados', $import->updated_count, 'refresh-cw', 'warning'],
            ['Sin cambios', $import->unchanged_count, 'circle-check', 'success'],
            ['No encontrados', $import->not_found_count, 'circle-help', 'secondary'],
            ['Errores', $import->error_count, 'triangle-alert', 'danger'],
        ];
    @endphp

    <div class="d-flex justify-content-end gap-2 mb-3">
        <a class="btn btn-light" href="{{ route('products.stock-imports.create') }}">
            Historial
        </a>
        <a class="btn btn-primary" href="{{ route('products.index') }}">
            Volver a Productos
        </a>
    </div>

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
        <dl class="row mb-0">
            <dt class="col-md-3">Sucursal</dt>
            <dd class="col-md-9">{{ $import->branch?->name ?? 'Sucursal eliminada' }}</dd>

            <dt class="col-md-3">Bodega</dt>
            <dd class="col-md-9">{{ $import->warehouse?->name ?? 'Bodega eliminada' }}</dd>

            <dt class="col-md-3">Archivo</dt>
            <dd class="col-md-9">{{ $import->original_filename }}</dd>

            <dt class="col-md-3">Ejecutada por</dt>
            <dd class="col-md-9">
                {{ $executedBy?->name ?? 'Usuario #' . $import->core_user_id }}
            </dd>

            <dt class="col-md-3">Fecha</dt>
            <dd class="col-md-9">
                {{ ($import->completed_at ?? $import->created_at)?->format('d/m/Y H:i') }}
            </dd>

            <dt class="col-md-3">Estado</dt>
            <dd class="col-md-9">
                {{ str($import->status)->replace('_', ' ')->title() }}
            </dd>
        </dl>
    </div>

    @if (! empty($import->errors))
        <div class="alert alert-warning">
            <strong>Filas no procesadas o errores:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($import->errors as $error)
                    <li>
                        @if (isset($error['row']))
                            Fila {{ $error['row'] }} · {{ $error['sku'] ?: 'Sin Código' }}:
                        @endif
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
                        <th>Stock anterior</th>
                        <th>Stock archivo</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($import->preview_rows ?? [] as $row)
                        <tr>
                            <td>{{ $row['row_number'] ?? '—' }}</td>
                            <td>{{ $row['sku'] ?: '—' }}</td>
                            <td>
                                {{ $row['current_quantity'] !== null ? $row['current_quantity'] : 'Sin registro' }}
                            </td>
                            <td>
                                {{ $row['file_quantity'] !== null ? $row['file_quantity'] : '—' }}
                            </td>
                            <td>
                                @switch($row['status'] ?? 'error')
                                    @case('update')
                                        Actualizado
                                        @break

                                    @case('unchanged')
                                        Sin cambio
                                        @break

                                    @case('not_found')
                                        SKU no encontrado
                                        @break

                                    @default
                                        Error
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
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
