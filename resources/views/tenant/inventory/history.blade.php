<x-layouts.tenant
    title="Historial de Inventario"
    subtitle="Importaciones existentes y ejecuciones futuras de sincronización"
>
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-primary" href="{{ route('products.stock-imports.create') }}">
            <i data-lucide="file-up" class="me-1"></i>
            Importar stock
        </a>
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <div class="p-3 border-bottom">
            <h5 class="mb-1">Importaciones de stock recientes</h5>
            <p class="text-muted mb-0">
                Historial del importador Excel que ya forma parte de Inventario.
            </p>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Archivo</th>
                        <th>Destino</th>
                        <th>Resultado</th>
                        <th>Estado</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($stockImports as $stockImport)
                        <tr>
                            <td>{{ $stockImport->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $userNames[$stockImport->core_user_id] ?? 'Usuario no disponible' }}</td>
                            <td>{{ $stockImport->original_filename }}</td>
                            <td>
                                {{ $stockImport->branch?->name ?? '—' }} —
                                {{ $stockImport->warehouse?->name ?? '—' }}
                            </td>
                            <td>
                                {{ $stockImport->updated_count }} actualizados /
                                {{ $stockImport->error_count }} errores
                            </td>
                            <td>{{ ucfirst($stockImport->status) }}</td>
                            <td class="text-end">
                                @if (
                                    $stockImport->status !== 'previewed'
                                    || (int) $stockImport->core_user_id === $currentUserId
                                )
                                    <a
                                        class="btn btn-sm btn-light"
                                        href="{{ route('products.stock-imports.show', $stockImport) }}"
                                    >
                                        Ver
                                    </a>
                                @else
                                    <span class="text-muted">Pendiente de otro usuario</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-5" colspan="7">
                                Todavía no existen importaciones de stock.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="tr-card p-0 overflow-hidden mt-3">
        <div class="p-3 border-bottom">
            <h5 class="mb-1">Sincronizaciones Contífico</h5>
            <p class="text-muted mb-0">
                Esta estructura registrará las sincronizaciones cuando se habiliten en una fase futura.
            </p>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>Alcance</th>
                        <th>Bodega</th>
                        <th>Resultado</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($executions as $execution)
                        <tr>
                            <td>{{ $execution->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $userNames[$execution->requested_by_core_user_id] ?? 'Usuario no disponible' }}</td>
                            <td>{{ $execution->type }}</td>
                            <td>{{ $execution->scope }}</td>
                            <td>
                                @if ($execution->warehouse)
                                    {{ $execution->branch?->name }} — {{ $execution->warehouse->name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                {{ $execution->succeeded_count }} correctos /
                                {{ $execution->failed_count }} fallidos
                            </td>
                            <td>{{ ucfirst($execution->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-5" colspan="7">
                                Todavía no existen ejecuciones de sincronización.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $executions->links() }}
        </div>
    </div>
</x-layouts.tenant>
