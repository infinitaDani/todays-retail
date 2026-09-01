<x-layouts.tenant
    title="Sucursales"
    subtitle="Estructura operativa de la cuenta activa"
>
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total', $summary['total'], 'building-2', 'primary'],
            ['Activas', $summary['active'], 'check-circle-2', 'success'],
            ['Inactivas', $summary['inactive'], 'pause-circle', 'warning'],
            ['Colaboradores asignados', $summary['assigned_staff'], 'users', 'primary'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="summary-card">
                    <span
                        class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}"
                    >
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

    <div class="tr-card p-0 overflow-hidden">
        <form
            class="listing-toolbar p-3 border-bottom"
            method="GET"
        >
            <div class="listing-search input-group">
                <span class="input-group-text bg-transparent">
                    <i data-lucide="search"></i>
                </span>

                <input
                    class="form-control"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Buscar por nombre o código"
                >
            </div>

            <div class="listing-filters">
                <select
                    class="form-select"
                    name="status"
                    onchange="this.form.submit()"
                >
                    <option value="">
                        Todos los estados
                    </option>

                    <option
                        value="active"
                        @selected(request('status') === 'active')
                    >
                        Activas
                    </option>

                    <option
                        value="inactive"
                        @selected(request('status') === 'inactive')
                    >
                        Inactivas
                    </option>
                </select>

                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('operations.branches') }}"
                >
                    Limpiar
                </a>

                <a
                    class="btn btn-primary"
                    href="{{ route('operations.branches.create') }}"
                >
                    <i data-lucide="plus" class="me-1"></i>
                    Nueva sucursal
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        <th>Código</th>
                        <th>Colaboradores</th>
                        <th>Asignaciones</th>
                        <th>Estado</th>
                        <th class="text-end">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td class="fw-semibold">
                                {{ $branch->name }}
                            </td>

                            <td>
                                {{ $branch->code ?: '—' }}
                            </td>

                            <td>
                                {{ $branch->staff_profiles_count }}
                            </td>

                            <td>
                                {{ $branch->assignments_count }}
                            </td>

                            <td>
                                <span
                                    class="badge badge-label badge-soft-{{ $branch->status === 'active'
                                        ? 'success'
                                        : 'warning' }}"
                                >
                                    {{ $branch->status === 'active'
                                        ? 'Activa'
                                        : 'Inactiva' }}
                                </span>
                            </td>

                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('operations.branches.show', $branch) }}"
                                    title="Ver"
                                >
                                    <i data-lucide="eye"></i>
                                </a>

                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('operations.branches.edit', $branch) }}"
                                    title="Editar"
                                >
                                    <i data-lucide="pencil"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="listing-empty">
                                    <i
                                        data-lucide="building-2"
                                        class="mb-2"
                                    ></i>

                                    <div>
                                        No se encontraron sucursales.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="listing-pagination px-3">
            {{ $branches->links() }}
        </div>
    </div>
</x-layouts.tenant>