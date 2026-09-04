<x-layouts.tenant
    title="Bodegas"
    subtitle="Ubicaciones de inventario asociadas a sucursales"
>
    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if (! $settings->manages_warehouses)
        <div class="alert alert-warning">
            La gestión de bodegas está desactivada en la configuración de Inventario.
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['Total', $summary['total'], 'warehouse', 'primary'],
            ['Activas', $summary['active'], 'circle-check', 'success'],
            ['Inactivas', $summary['inactive'], 'circle-pause', 'warning'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-xl-4">
                <div class="summary-card">
                    <span class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}">
                        <i data-lucide="{{ $icon }}"></i>
                    </span>

                    <div class="summary-value">{{ $value }}</div>
                    <div class="summary-label">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="tr-card p-0 overflow-hidden">
        <form class="listing-toolbar p-3 border-bottom" method="GET">
            <div class="listing-search input-group">
                <span class="input-group-text bg-transparent">
                    <i data-lucide="search"></i>
                </span>

                <input
                    class="form-control"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Buscar bodega, propósito o código"
                >
            </div>

            <div class="listing-filters">
                <select class="form-select" name="branch_id" onchange="this.form.submit()">
                    <option value="">Todas las sucursales</option>

                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>

                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(request('status') === 'active')>Activas</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option>
                </select>

                <a class="btn btn-outline-secondary" href="{{ route('inventory.warehouses.index') }}">
                    Limpiar
                </a>

                @if ($canManage && $settings->manages_warehouses)
                    <a class="btn btn-primary" href="{{ route('inventory.warehouses.create') }}">
                        <i data-lucide="plus" class="me-1"></i>
                        Nueva bodega
                    </a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bodega</th>
                        <th>Sucursal</th>
                        <th>Propósito</th>
                        <th>Código Contífico</th>
                        <th>Stock registrado</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td class="fw-semibold">{{ $warehouse->name }}</td>
                            <td>{{ $warehouse->branch?->name ?? '—' }}</td>
                            <td>
								@php
									$purposeLabels = [
										'purchase' => 'Compra',
										'sale' => 'Venta',
										'production' => 'Producción',
									];

									$warehousePurposes = collect($warehouse->purposes ?? [])
										->map(
											fn ($purpose) => $purposeLabels[$purpose] ?? $purpose
										);
								@endphp

								{{ $warehousePurposes->isNotEmpty()
									? $warehousePurposes->join(' · ')
									: '—'
								}}
							</td>
                            <td>{{ $warehouse->contifico_code ?: '—' }}</td>
                            <td>{{ $warehouse->stocks_count }} SKU</td>
                            <td>
                                <span
                                    class="badge badge-soft-{{ $warehouse->is_active
                                        ? 'success'
                                        : 'warning' }}"
                                >
                                    {{ $warehouse->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('inventory.warehouses.show', $warehouse) }}"
                                >
                                    Ver
                                </a>

                                @if ($canManage)
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="{{ route('inventory.warehouses.edit', $warehouse) }}"
                                    >
                                        Editar
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-5" colspan="7">
                                No se encontraron bodegas con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $warehouses->links() }}
        </div>
    </div>
</x-layouts.tenant>
