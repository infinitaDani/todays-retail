<x-layouts.core-admin title="{{ $account->name }} · Core Admin">
    <div class="toolbar"><div><h1>{{ $account->name }}</h1><p>RUC: {{ $account->ruc }} · Database name: {{ $account->database_name }} · <span class="status">{{ $account->status }}</span></p></div><a class="button secondary" href="{{ route('admin.accounts.edit', $account) }}">Editar cuenta</a></div>
    <div class="card">
        <h2>Membresías</h2>
        <table><thead><tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Acciones</th></tr></thead><tbody>
            @forelse ($memberships as $membership)
                <tr><td>{{ $membership->user->name }}</td><td>{{ $membership->user->email }}</td><td>
                    <form class="actions" method="POST" action="{{ route('admin.accounts.memberships.update', [$account, $membership]) }}">@csrf @method('PATCH')<select name="role_id">@foreach ($roles as $role)<option value="{{ $role->id }}" @selected($membership->role_id === $role->id)>{{ $role->name }}</option>@endforeach</select><button type="submit">Cambiar rol</button></form>
                </td><td><form method="POST" action="{{ route('admin.accounts.memberships.destroy', [$account, $membership]) }}">@csrf @method('DELETE')<button class="danger" type="submit">Quitar</button></form></td></tr>
            @empty <tr><td colspan="4" class="muted">No hay usuarios asignados.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="card">
        <h2>Agregar usuario</h2>
        @if ($availableUsers->isEmpty() || $roles->isEmpty())
            <p class="muted">Para agregar una membresía necesitas al menos un usuario disponible y un rol creado.</p>
        @else
            <form method="POST" action="{{ route('admin.accounts.memberships.store', $account) }}">@csrf
                <label for="user_id">Usuario</label><select id="user_id" name="user_id" required><option value="">Selecciona un usuario</option>@foreach ($availableUsers as $user)<option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>@endforeach</select>@error('user_id')<div class="error">{{ $message }}</div>@enderror
                <label for="role_id">Rol</label><select id="role_id" name="role_id" required><option value="">Selecciona un rol</option>@foreach ($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>@endforeach</select>@error('role_id')<div class="error">{{ $message }}</div>@enderror
                <div class="actions"><button type="submit">Agregar a la cuenta</button></div>
            </form>
        @endif
    </div>
</x-layouts.core-admin>
