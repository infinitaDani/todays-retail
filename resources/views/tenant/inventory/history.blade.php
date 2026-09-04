<x-layouts.tenant
    title="Historial de Inventario"
    subtitle="Sincronizaciones Contífico e importaciones Excel"
>
    <div class="tr-card p-0 overflow-hidden mb-3">
        <div class="p-3 border-bottom">
            <h5 class="mb-1">Sincronizaciones Contífico</h5>
            <p class="text-muted mb-0">
                Cada fila corresponde a una acción completa del usuario.
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
                        <th>Procesados</th>
                        <th>Actualizados</th>
                        <th>Sin cambios</th>
                        <th>No encontrados</th>
                        <th>Errores</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($executions as $execution)
                        <tr>
                            <td>{{ $execution->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                {{ $userNames[$execution->requested_by_core_user_id] ?? 'Usuario no disponible' }}
                            </td>
                            <td>
                                {{ $execution->typeLabel() }}
                            </td>
                            <td>
                                @if ($execution->warehouse)
                                    {{ $execution->branch?->name }} — {{ $execution->warehouse->name }}
                                @else
                                    Todas las bodegas autorizadas
                                @endif
                            </td>
                            <td>{{ $execution->processed_count }}</td>
                            <td>{{ $execution->updated_count }}</td>
                            <td>{{ $execution->unchanged_count }}</td>
                            <td>{{ $execution->not_found_count }}</td>
                            <td>{{ $execution->failed_count }}</td>
                            <td>
                                {{ $execution->durationInSeconds() !== null
                                    ? $execution->durationInSeconds() . ' s'
                                    : '—' }}
                            </td>
                            <td>{{ $execution->statusLabel() }}</td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('inventory.sync-executions.show', $execution) }}"
                                >
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-5" colspan="12">
                                Todavía no existen sincronizaciones.
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

    <div class="tr-card p-0 overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between gap-3">
            <div>
                <h5 class="mb-1">Importaciones Excel recientes</h5>
                <p class="text-muted mb-0">El importador de stock continúa independiente.</p>
            </div>

            @if ($canImportStock)
                <a class="btn btn-primary" href="{{ route('products.stock-imports.create') }}">
                    Importar stock
                </a>
            @endif
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
                        <th></th>
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
                                @if ($canImportStock)
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
                                        <span class="text-muted">Pendiente</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
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
</x-layouts.tenant>
