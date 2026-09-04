<x-layouts.tenant
    title="Contífico"
    subtitle="Configuración de Inventario, integración y límites comerciales"
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

    <form method="POST" action="{{ route('inventory.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="tr-card mb-3">
            <h5 class="mb-1">Inventario</h5>
            <p class="text-muted mb-3">
                Controla si la cuenta administra bodegas y deja preparada la sincronización futura.
            </p>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            id="manages-warehouses"
                            name="manages_warehouses"
                            type="checkbox"
                            value="1"
                            @checked(old('manages_warehouses', $inventorySettings->manages_warehouses))
                        >
                        <label class="form-check-label" for="manages-warehouses">
                            Gestionar bodegas
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            id="stock-sync"
                            name="contifico_stock_sync_enabled"
                            type="checkbox"
                            value="1"
                            @checked(old(
                                'contifico_stock_sync_enabled',
                                $inventorySettings->contifico_stock_sync_enabled,
                            ))
                        >
                        <label class="form-check-label" for="stock-sync">
                            Sincronización de stock con Contífico
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="tr-card mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="mb-1">Integración Contífico</h5>
                    <p class="text-muted mb-0">
                        La API Key se cifra en la base tenant y nunca se vuelve a mostrar completa.
                    </p>
                </div>

                <span class="badge badge-soft-{{ $account->contifico_enabled ? 'success' : 'warning' }}">
                    {{ $account->contifico_enabled ? 'Habilitado por el plan' : 'No incluido en el plan' }}
                </span>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch mt-md-4">
                        <input
                            class="form-check-input"
                            id="contifico-active"
                            name="contifico_is_active"
                            type="checkbox"
                            value="1"
                            @checked(old('contifico_is_active', $contificoSettings->is_active))
                        >
                        <label class="form-check-label" for="contifico-active">
                            Integración activa
                        </label>
                    </div>
                </div>

                <div class="col-md-8">
                    <label class="form-label" for="api-key">API Key</label>
                    <input
                        class="form-control"
                        id="api-key"
                        name="api_key"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Dejar vacío para conservar la clave actual"
                    >
                    @if ($contificoSettings->maskedApiKey())
                        <div class="form-text">
                            Clave guardada: {{ $contificoSettings->maskedApiKey() }}
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch mt-md-4">
                        <input
                            class="form-check-input"
                            id="automatic-sync"
                            name="automatic_sync_enabled"
                            type="checkbox"
                            value="1"
                            @checked(old('automatic_sync_enabled', $contificoSettings->automatic_sync_enabled))
                        >
                        <label class="form-check-label" for="automatic-sync">
                            Sincronización automática activa
                        </label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="sync-interval">Intervalo</label>
                    <select class="form-select" id="sync-interval" name="sync_interval_minutes">
                        @foreach ([15, 30, 60, 180, 360, 720, 1440] as $minutes)
                            <option
                                value="{{ $minutes }}"
                                @selected(old(
                                    'sync_interval_minutes',
                                    $contificoSettings->sync_interval_minutes,
                                ) == $minutes)
                            >
                                {{ $minutes < 60 ? $minutes . ' minutos' : ($minutes / 60) . ' horas' }}
                            </option>
                        @endforeach
                    </select>
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
                        value="{{ old('batch_size', $contificoSettings->batch_size) }}"
                    >
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                Guardar estas opciones no ejecuta sincronizaciones. La sincronización masiva,
                por producto y programada se implementará en una fase posterior.
            </div>
        </div>

        <div class="tr-card mb-3">
            <h5 class="mb-1">Límites comerciales</h5>
            <p class="text-muted mb-3">
                Puede ajustar los límites diarios acordes a su plan
            </p>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Sincronizaciones diarias del plan</label>
                    <input
                        class="form-control"
                        value="{{ $account->manual_bulk_syncs_per_day ?? 'Sin límite configurado' }}"
                        disabled
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">Intervalo mínimo del plan</label>
                    <input
                        class="form-control"
                        value="{{ $account->manual_bulk_min_interval_minutes !== null
                            ? $account->manual_bulk_min_interval_minutes . ' minutos'
                            : 'Sin intervalo configurado' }}"
                        disabled
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="tenant-daily-limit">Límite diario del tenant</label>
                    <input
                        class="form-control"
                        id="tenant-daily-limit"
                        name="manual_bulk_syncs_per_day"
                        type="number"
                        min="0"
                        value="{{ old('manual_bulk_syncs_per_day', $inventorySettings->manual_bulk_syncs_per_day) }}"
                        placeholder="Usar límite del plan"
                    >
                </div>
				<div class="col-md-3">
					<label
						class="form-label"
						for="tenant-minimum-interval"
					>
						Intervalo mínimo del tenant
					</label>

					<input
						class="form-control"
						id="tenant-minimum-interval"
						name="manual_bulk_min_interval_minutes"
						type="number"
						min="0"
						value="{{ old(
							'manual_bulk_min_interval_minutes',
							$inventorySettings->manual_bulk_min_interval_minutes
						) }}"
						placeholder="Usar mínimo del plan"
					>
				</div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th style="width: 240px;">Límite diario individual</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <small class="text-muted">{{ $user->email }}</small>
                                </td>
                                <td>{{ $user->pivot?->role_id ? 'Membresía activa' : '—' }}</td>
                                <td>
                                    <input
                                        class="form-control"
                                        name="user_limits[{{ $user->id }}]"
                                        type="number"
                                        min="0"
                                        value="{{ old('user_limits.' . $user->id, $userLimits[$user->id] ?? '') }}"
                                        placeholder="Usar límite tenant"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">
                Guardar configuración
            </button>
        </div>
    </form>

    <div class="tr-card mt-3">
        <h5 class="mb-1">Probar conexión</h5>
        <p class="text-muted">
            Guarda primero la API Key. La prueba consulta un único resultado y no modifica datos.
        </p>

        <form method="POST" action="{{ route('inventory.settings.test') }}">
            @csrf

            <button class="btn btn-outline-primary" type="submit">
                <i data-lucide="plug-zap" class="me-1"></i>
                Probar conexión
            </button>
        </form>
    </div>
</x-layouts.tenant>
