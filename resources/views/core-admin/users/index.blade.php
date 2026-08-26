<x-layouts.core-admin title="Usuarios · Core Admin">
    <div class="toolbar"><div><h1>Usuarios globales</h1><p>Los usuarios se administran una sola vez y pueden pertenecer a varias cuentas.</p></div><a class="button" href="{{ route('admin.users.create') }}">Nuevo usuario</a></div>
    <div class="card"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr></thead><tbody>
        @forelse ($users as $user)
            <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td class="status">{{ $user->status }}</td><td>{{ $user->created_at?->format('Y-m-d H:i') }}</td><td class="actions"><a class="button secondary" href="{{ route('admin.users.edit', $user) }}">Editar / contraseña</a><form class="inline" method="POST" action="{{ route('admin.users.status', $user) }}">@csrf @method('PATCH')<button type="submit">{{ $user->status === 'active' ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @empty <tr><td colspan="5" class="muted">No hay usuarios registrados.</td></tr>
        @endforelse
    </tbody></table>{{ $users->links() }}</div>
</x-layouts.core-admin>
