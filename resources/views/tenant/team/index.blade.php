<x-layouts.tenant title="Colaboradores" subtitle="Roles operativos y sucursal principal de cada colaborador">
    <div class="row g-3 mb-4">@foreach ([['Total', $summary['total'], 'users', 'primary'], ['Activos', $summary['active'], 'user-check', 'success'], ['Management', $summary['management'], 'briefcase-business', 'primary'], ['Administradores de tienda', $summary['store_admin'], 'store', 'warning'], ['Asesoras', $summary['advisor'], 'badge-check', 'success']] as [$label, $value, $icon, $color])<div class="col-6 col-xl"><div class="summary-card"><span class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}"><i data-lucide="{{ $icon }}"></i></span><div class="summary-value">{{ $value }}</div><div class="summary-label">{{ $label }}</div></div></div>@endforeach</div>
    <div class="tr-card p-0 overflow-hidden">
        <form class="listing-toolbar p-3 border-bottom" method="GET"><div class="listing-search input-group"><span class="input-group-text bg-transparent"><i data-lucide="search"></i></span><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Buscar colaborador"></div><div class="listing-filters"><select class="form-select" name="branch_id" onchange="this.form.submit()"><option value="">Todas las sucursales</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select><select class="form-select" name="role" onchange="this.form.submit()"><option value="">Todos los roles</option>@foreach($roles as $role)<option value="{{ $role->code }}" @selected(request('role') === $role->code)>{{ $role->name }}</option>@endforeach</select><select class="form-select" name="status" onchange="this.form.submit()"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select><a class="btn btn-outline-secondary" href="{{ route('team.index') }}">Limpiar</a><a class="btn btn-primary" href="{{ route('team.create') }}"><i data-lucide="plus" class="me-1"></i>Nuevo colaborador</a></div></form>
        <div class="table-responsive"><table class="table table-custom align-middle mb-0"><thead><tr><th>Colaborador</th><th>Configuración operativa</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($memberships as $membership)
                        @php($profile = $profiles->get($membership->user_id))
                        <tr>
                            <td><strong>{{ $membership->user->name }}</strong><div class="text-muted small">{{ $membership->user->email }}</div></td>
                            <td>
                                <span class="badge badge-label badge-soft-primary">{{ $membership->role->name }}</span><div class="small text-muted mt-1">{{ $profile?->branch?->name ?? 'Sin sucursal' }}</div>
                            </td>
                            <td><span class="badge badge-label badge-soft-{{ $profile?->status === 'inactive' ? 'warning' : 'success' }}">{{ $profile?->status === 'inactive' ? 'Inactivo' : 'Activo' }}</span></td>
                            <td class="text-end">@if($profile)<a class="btn btn-sm btn-light" href="{{ route('team.show', $profile) }}" title="Ver"><i data-lucide="eye"></i></a><a class="btn btn-sm btn-light" href="{{ route('team.edit', $profile) }}" title="Editar"><i data-lucide="pencil"></i></a>@else<span class="text-muted small">Perfil pendiente</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No hay colaboradores en esta cuenta.</td></tr>
                    @endforelse
                </tbody>
            </table></div><div class="listing-pagination px-3">{{ $memberships->links() }}</div></div>
</x-layouts.tenant>
