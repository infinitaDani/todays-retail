<x-layouts.core-admin title="Roles · Core Admin">
    <div class="toolbar"><div><h1>Roles</h1><p>Los roles se asignan únicamente a membresías de cuenta.</p></div><a class="button" href="{{ route('admin.roles.create') }}">Nuevo rol</a></div>
    <div class="card"><table><thead><tr><th>Nombre</th><th>Código</th><th>Acciones</th></tr></thead><tbody>
        @forelse ($roles as $role)<tr><td>{{ $role->name }}</td><td>{{ $role->code }}</td><td><a class="button secondary" href="{{ route('admin.roles.edit', $role) }}">Editar</a></td></tr>
        @empty <tr><td colspan="3" class="muted">No hay roles registrados.</td></tr>
        @endforelse
    </tbody></table>{{ $roles->links() }}</div>
</x-layouts.core-admin>
