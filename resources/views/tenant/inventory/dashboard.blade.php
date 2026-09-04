<x-layouts.tenant
    title="Inventario"
    subtitle="Bodegas, existencias e integraciones de la cuenta activa"
>
    <div class="row g-3 mb-4">
        @foreach ([
            ['Bodegas', $summary['warehouses'], 'warehouse', 'primary'],
            ['Bodegas activas', $summary['active_warehouses'], 'circle-check', 'success'],
            ['Registros de stock', $summary['stock_records'], 'boxes', 'info'],
            ['Existencias totales', number_format((float) $summary['total_quantity'], 3), 'package-check', 'warning'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="summary-card h-100">
                    <span class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}">
                        <i data-lucide="{{ $icon }}"></i>
                    </span>

                    <div class="summary-value">
                        {{ $value }}
                    </div>

                    <div class="summary-label">
                        {{ $label }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="tr-card h-100">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Accesos de Inventario</h5>
                        <p class="text-muted mb-0">
                            Consulta bodegas y utiliza las herramientas habilitadas para tu perfil.
                        </p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <a
                            class="btn btn-outline-primary w-100 text-start p-3"
                            href="{{ route('inventory.warehouses.index') }}"
                        >
                            <i data-lucide="warehouse" class="me-2"></i>
                            Bodegas
                        </a>
                    </div>

                    @if ($canImportStock)
                        <div class="col-md-6">
                            <a
                                class="btn btn-outline-primary w-100 text-start p-3"
                                href="{{ route('products.stock-imports.create') }}"
                            >
                                <i data-lucide="file-up" class="me-2"></i>
                                Importación de stock
                            </a>
                        </div>

                        <div class="col-md-6">
                            <a
                                class="btn btn-outline-primary w-100 text-start p-3"
                                href="{{ route('inventory.history') }}"
                            >
                                <i data-lucide="history" class="me-2"></i>
                                Historial
                            </a>
                        </div>
                    @endif

                    @if ($canConfigure)
                        <div class="col-md-6">
                            <a
                                class="btn btn-outline-primary w-100 text-start p-3"
                                href="{{ route('inventory.contifico') }}"
                            >
                                <i data-lucide="plug-zap" class="me-2"></i>
                                Contífico y configuración
                            </a>
                        </div>
                    @endif

                    @if ($canSynchronize && ! $canConfigure)
                        <div class="col-md-6">
                            <a
                                class="btn btn-outline-primary w-100 text-start p-3"
                                href="{{ route('inventory.contifico') }}"
                            >
                                <i data-lucide="refresh-cw" class="me-2"></i>
                                Sincronizar con Contífico
                            </a>
                        </div>
                    @endif

                    @if ($canSynchronize && ! $canImportStock)
                        <div class="col-md-6">
                            <a
                                class="btn btn-outline-primary w-100 text-start p-3"
                                href="{{ route('inventory.history') }}"
                            >
                                <i data-lucide="history" class="me-2"></i>
                                Historial
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="tr-card h-100">
                <h5 class="mb-3">Estado de configuración</h5>

                <dl class="row mb-0">
                    <dt class="col-8">Gestionar bodegas</dt>
                    <dd class="col-4 text-end">
                        {{ $inventorySettings->manages_warehouses ? 'Activo' : 'Inactivo' }}
                    </dd>

                    <dt class="col-8">Contífico</dt>
                    <dd class="col-4 text-end">
                        {{ $contificoSettings->is_active ? 'Activo' : 'Inactivo' }}
                    </dd>

                    <dt class="col-8">Sincronización automática</dt>
                    <dd class="col-4 text-end">
                        {{ $contificoSettings->automatic_sync_enabled ? 'Activa' : 'Inactiva' }}
                    </dd>
                </dl>

                @if ($summary['recent_syncs']->first())
                    @php($latestSync = $summary['recent_syncs']->first())
                    <div class="alert alert-light border mt-3 mb-0">
                        <strong>Última sincronización real</strong>
                        <div class="small mt-1">
                            {{ $latestSync->created_at?->format('d/m/Y H:i') }} ·
                            {{ $latestSync->typeLabel() }} ·
                            {{ $latestSync->statusLabel() }}
                        </div>
                        <div class="small text-muted">
                            {{ $latestSync->processed_count }} procesados ·
                            {{ $latestSync->updated_count }} actualizados ·
                            {{ $latestSync->failed_count }} errores
                        </div>
                    </div>
                @else
                    <div class="alert alert-info mt-3 mb-0">
                        Todavía no existen sincronizaciones manuales con Contífico.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.tenant>
