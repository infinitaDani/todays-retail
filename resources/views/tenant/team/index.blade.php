<x-layouts.tenant title="Colaboradores" subtitle="Roles operativos y sucursal principal de cada colaborador">
    <div class="row g-3 mb-4">@foreach ([['Total', $summary['total'], 'users', 'primary'], ['Activos', $summary['active'], 'user-check', 'success'], ['Management', $summary['management'], 'briefcase-business', 'primary'], ['Administradores de tienda', $summary['store_admin'], 'store', 'warning'], ['Asesoras', $summary['advisor'], 'badge-check', 'success']] as [$label, $value, $icon, $color])<div class="col-6 col-xl"><div class="summary-card"><span class="summary-icon bg-{{ $color }}-subtle text-{{ $color }}"><i data-lucide="{{ $icon }}"></i></span><div class="summary-value">{{ $value }}</div><div class="summary-label">{{ $label }}</div></div></div>@endforeach</div>
    <div class="tr-card p-0 overflow-hidden">
        <form class="listing-toolbar p-3 border-bottom" method="GET"><div class="listing-search input-group"><span class="input-group-text bg-transparent"><i data-lucide="search"></i></span><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Buscar colaborador"></div><div class="listing-filters"><select class="form-select" name="branch_id" onchange="this.form.submit()"><option value="">Todas las sucursales</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select><select class="form-select" name="role" onchange="this.form.submit()"><option value="">Todos los roles</option>@foreach($roles as $role)<option value="{{ $role->code }}" @selected(request('role') === $role->code)>{{ $role->name }}</option>@endforeach</select><select class="form-select" name="status" onchange="this.form.submit()"><option value="">Todos los estados</option><option value="active" @selected(request('status') === 'active')>Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option></select><a class="btn btn-outline-secondary" href="{{ route('team.index') }}">Limpiar</a><button class="btn btn-primary" type="button" disabled title="La creación de colaboradores corresponde al CRUD funcional."><i data-lucide="plus" class="me-1"></i>Nuevo colaborador</button></div></form>
        <div class="table-responsive"><table class="table table-custom align-middle mb-0"><thead><tr><th>Colaborador</th><th>Configuración operativa</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($memberships as $membership)
                        @php($profile = $profiles->get($membership->user_id))
                        <tr>
                            <td><strong>{{ $membership->user->name }}</strong><div class="text-muted small">{{ $membership->user->email }}</div></td>
                            <td>
                                <form class="row g-2 align-items-end mb-0" method="POST" action="{{ route('team.update', $membership) }}">
                                    @csrf @method('PATCH')
                                    <div class="col-md-4"><label class="form-label small" for="role-{{ $membership->id }}">Rol</label><select class="form-select" id="role-{{ $membership->id }}" name="role_id" required>@foreach ($roles as $role)<option value="{{ $role->id }}" @selected($membership->role_id === $role->id)>{{ $role->name }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label small" for="branch-{{ $membership->id }}">Sucursal principal</label><select class="form-select" id="branch-{{ $membership->id }}" name="branch_id"><option value="">Sin sucursal (solo Management)</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($profile?->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><button class="btn btn-sm btn-primary" type="submit">Actualizar</button></div>
                                </form>
                            </td>
                            <td><span class="badge badge-label badge-soft-{{ $membership->user->status === 'active' ? 'success' : 'warning' }}">{{ $membership->user->status === 'active' ? 'Activo' : 'Inactivo' }}</span></td>
                            <td class="text-end"><span class="text-muted small">Perfil</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No hay colaboradores en esta cuenta.</td></tr>
                    @endforelse
                </tbody>
            </table></div><div class="listing-pagination px-3">{{ $memberships->links() }}</div></div>
</x-layouts.tenant>
