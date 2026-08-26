<x-layouts.tenant title="Equipo" subtitle="Roles operativos y sucursal principal de cada colaborador">
    <div class="tr-card">
        <p class="text-muted">Solo se pueden asignar los roles operativos autorizados: Management, Administrador de Tienda y Asesora. Las membresías y los roles globales siguen protegidos en Core.</p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Colaborador</th><th>Rol operativo</th><th>Sucursal principal</th><th></th></tr></thead>
                <tbody>
                    @forelse ($memberships as $membership)
                        @php($profile = $profiles->get($membership->user_id))
                        <tr>
                            <td><strong>{{ $membership->user->name }}</strong><div class="text-muted small">{{ $membership->user->email }}</div></td>
                            <td colspan="3">
                                <form class="row g-2 align-items-end mb-0" method="POST" action="{{ route('team.update', $membership) }}">
                                    @csrf @method('PATCH')
                                    <div class="col-md-4"><label class="form-label small" for="role-{{ $membership->id }}">Rol</label><select class="form-select" id="role-{{ $membership->id }}" name="role_id" required>@foreach ($roles as $role)<option value="{{ $role->id }}" @selected($membership->role_id === $role->id)>{{ $role->name }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label small" for="branch-{{ $membership->id }}">Sucursal principal</label><select class="form-select" id="branch-{{ $membership->id }}" name="branch_id"><option value="">Sin sucursal (solo Management)</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($profile?->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><button class="btn btn-primary" type="submit">Guardar</button></div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No hay colaboradores en esta cuenta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.tenant>
