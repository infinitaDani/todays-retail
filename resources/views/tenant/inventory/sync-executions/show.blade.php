<x-layouts.tenant
    title="Resultado de sincronización"
    subtitle="Ejecución #{{ $execution->id }}"
>
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-light" href="{{ route('inventory.history') }}">
            Volver al historial
        </a>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Procesados', $execution->processed_count, 'list-checks'],
            ['Actualizados', $execution->updated_count, 'refresh-cw'],
            ['Sin cambios', $execution->unchanged_count, 'equal'],
            ['No encontrados', $execution->not_found_count, 'search-x'],
            ['Errores', $execution->failed_count, 'triangle-alert'],
        ] as [$label, $value, $icon])
            <div class="col-6 col-xl">
                <div class="summary-card h-100">
                    <span class="summary-icon bg-primary-subtle text-primary">
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
            <dt class="col-md-3">Usuario</dt>
            <dd class="col-md-9">{{ $requestedBy?->name ?? 'Usuario no disponible' }}</dd>

            <dt class="col-md-3">Tipo</dt>
            <dd class="col-md-9">
                {{ $execution->typeLabel() }}
            </dd>

            <dt class="col-md-3">Alcance</dt>
            <dd class="col-md-9">
                @if ($execution->warehouse)
                    {{ $execution->branch?->name }} — {{ $execution->warehouse->name }}
                @else
                    Todas las bodegas autorizadas
                @endif
            </dd>

            <dt class="col-md-3">Estado</dt>
            <dd class="col-md-9">{{ $execution->statusLabel() }}</dd>

            <dt class="col-md-3">Duración</dt>
            <dd class="col-md-9">
                {{ $execution->durationInSeconds() !== null
                    ? $execution->durationInSeconds() . ' segundos'
                    : '—' }}
            </dd>
        </dl>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="p-3 border-bottom">
            <h5 class="mb-0">Detalle por SKU</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th>Bodega</th>
                        <th>Stock anterior</th>
                        <th>Stock Contífico</th>
                        <th>Resultado</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->sku }}</td>
                            <td>{{ $item->product?->name ?? '—' }}</td>
                            <td>
                                {{ $item->warehouse?->branch?->name ?? '—' }} —
                                {{ $item->warehouse?->name ?? '—' }}
                            </td>
                            <td>{{ $item->previous_quantity ?? '—' }}</td>
                            <td>{{ $item->remote_quantity ?? '—' }}</td>
                            <td>{{ $item->resultLabel() }}</td>
                            <td>{{ $item->message ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-5" colspan="7">
                                La ejecución no registró SKUs.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $items->links() }}
        </div>
    </div>
</x-layouts.tenant>
