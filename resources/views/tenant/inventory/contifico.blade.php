<x-layouts.tenant
    title="Contífico"
    subtitle="Configuración y sincronización autoritativa del stock por bodega"
>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tr-card mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
            <div>
                <h5 class="mb-1">Sincronización manual</h5>
                <p class="text-muted mb-0">
                    Reemplaza el stock local con la cantidad actual reportada por Contífico.
                </p>
            </div>

            <span class="badge badge-soft-{{ $account->contifico_enabled ? 'success' : 'warning' }}">
                {{ $account->contifico_enabled ? 'Habilitado por el plan' : 'No incluido en el plan' }}
            </span>
        </div>

        <form method="POST" action="{{ route('inventory.sync.bulk') }}">
            @csrf

            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label" for="bulk-warehouse">Alcance</label>
                    <select class="form-select" id="bulk-warehouse" name="warehouse_id">
                        <option value="">Todas las bodegas autorizadas</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">
                                {{ $warehouse->branch?->name }} — {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-4">
                    <button class="btn btn-primary w-100" type="submit">
                        <i data-lucide="refresh-cw" class="me-1"></i>
                        Sincronizar ahora
                    </button>
                </div>
            </div>
        </form>

        @if ($latestExecution)
            <div class="alert alert-light border mt-3 mb-0">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <span>
                        Última ejecución:
                        <strong>{{ $latestExecution->created_at?->format('d/m/Y H:i') }}</strong>
                    </span>
                    <a href="{{ route('inventory.sync-executions.show', $latestExecution) }}">
                        Ver resultado
                    </a>
                </div>

                <div class="small text-muted mt-1">
                    {{ $latestExecution->processed_count }} procesados ·
                    {{ $latestExecution->updated_count }} actualizados ·
                    {{ $latestExecution->unchanged_count }} sin cambios ·
                    {{ $latestExecution->not_found_count }} no encontrados ·
                    {{ $latestExecution->failed_count }} errores
                </div>
            </div>
        @endif
    </div>

    <div class="tr-card mb-3">
        <h5 class="mb-1">Actualizar un SKU</h5>
        <p class="text-muted mb-3">
            Sincroniza una variante específica sin consumir la cuota masiva diaria.
        </p>

        <form method="POST" action="{{ route('inventory.sync.sku') }}">
            @csrf

            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="sync-sku">SKU exacto</label>
                    <input
                        class="form-control"
                        id="sync-sku"
                        name="sku"
                        type="text"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="col-lg-5">
                    <label class="form-label" for="sku-warehouse">Bodega</label>
                    <select class="form-select" id="sku-warehouse" name="warehouse_id">
                        <option value="">Todas las bodegas autorizadas</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">
                                {{ $warehouse->branch?->name }} — {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-3">
                    <button class="btn btn-outline-primary w-100" type="submit">
                        Actualizar stock
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if ($canConfigure)
        <form method="POST" action="{{ route('inventory.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="tr-card mb-3">
                <h5 class="mb-1">Integración y parámetros técnicos</h5>
                <p class="text-muted mb-3">
                    La API Key se cifra en la base tenant y nunca se muestra completa.
                </p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-md-4">
                            <input
                                class="form-check-input"
                                id="manages-warehouses"
                                name="manages_warehouses"
                                type="checkbox"
                                value="1"
                                @checked($inventorySettings->manages_warehouses)
                            >
                            <label class="form-check-label" for="manages-warehouses">
                                Gestionar bodegas
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mt-md-4">
                            <input
                                class="form-check-input"
                                id="stock-sync"
                                name="contifico_stock_sync_enabled"
                                type="checkbox"
                                value="1"
                                @checked($inventorySettings->contifico_stock_sync_enabled)
                            >
                            <label class="form-check-label" for="stock-sync">
                                Sincronización de stock habilitada
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mt-md-4">
                            <input
                                class="form-check-input"
                                id="contifico-active"
                                name="contifico_is_active"
                                type="checkbox"
                                value="1"
                                @checked($contificoSettings->is_active)
                            >
                            <label class="form-check-label" for="contifico-active">
                                Integración activa
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="api-key">API Key</label>
                        <input
                            class="form-control"
                            id="api-key"
                            name="api_key"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Vacío conserva la clave"
                        >
                        @if ($contificoSettings->maskedApiKey())
                            <div class="form-text">
                                Clave guardada: {{ $contificoSettings->maskedApiKey() }}
                            </div>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="batch-size">Batch size técnico</label>
                        <input
                            class="form-control"
                            id="batch-size"
                            name="batch_size"
                            type="number"
                            min="1"
                            max="500"
                            value="{{ $contificoSettings->batch_size }}"
                        >
                    </div>

                    <div class="col-md-4">
                        <div class="form-check form-switch mt-md-4">
                            <input
                                class="form-check-input"
                                id="automatic-sync"
                                name="automatic_sync_enabled"
                                type="checkbox"
                                value="1"
                                @checked($contificoSettings->automatic_sync_enabled)
                            >
                            <label class="form-check-label" for="automatic-sync">
                                Preparar sincronización automática
                            </label>
                        </div>
                        <div class="form-text">
                            Esta fase todavía no ejecuta procesos automáticos.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="sync-interval">
                            Intervalo configurado
                        </label>
                        <select
                            class="form-select"
                            id="sync-interval"
                            name="sync_interval_minutes"
                        >
                            @foreach ([15, 30, 60, 180, 360, 720, 1440] as $minutes)
                                <option
                                    value="{{ $minutes }}"
                                    @selected($contificoSettings->sync_interval_minutes == $minutes)
                                >
                                    {{ $minutes < 60
                                        ? $minutes . ' minutos'
                                        : ($minutes / 60) . ' horas' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="tenant-daily-limit">
                            Límite diario tenant
                        </label>
                        <input
                            class="form-control"
                            id="tenant-daily-limit"
                            name="manual_bulk_syncs_per_day"
                            type="number"
                            min="0"
                            value="{{ $inventorySettings->manual_bulk_syncs_per_day }}"
                            placeholder="Usar plan"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="tenant-minimum-interval">
                            Intervalo mínimo tenant
                        </label>
                        <input
                            class="form-control"
                            id="tenant-minimum-interval"
                            name="manual_bulk_min_interval_minutes"
                            type="number"
                            min="0"
                            value="{{ $inventorySettings->manual_bulk_min_interval_minutes }}"
                            placeholder="Usar plan"
                        >
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th style="width: 260px;">Límite diario individual</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <small class="text-muted">{{ $user->email }}</small>
                                    </td>
                                    <td>
                                        <input
                                            class="form-control"
                                            name="user_limits[{{ $user->id }}]"
                                            type="number"
                                            min="0"
                                            value="{{ $userLimits[$user->id] ?? '' }}"
                                            placeholder="Usar límite tenant"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary" type="submit">
                        Guardar configuración
                    </button>
                </div>
            </div>
        </form>

        <div class="tr-card">
            <h5 class="mb-1">Probar conexión</h5>
            <p class="text-muted">
                La prueba consulta Contífico sin modificar existencias.
            </p>

            <form method="POST" action="{{ route('inventory.settings.test') }}">
                @csrf

                <button class="btn btn-outline-primary" type="submit">
                    <i data-lucide="plug-zap" class="me-1"></i>
                    Probar conexión
                </button>
            </form>
        </div>
    @endif
</x-layouts.tenant>
