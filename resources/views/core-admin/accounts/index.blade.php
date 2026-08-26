<x-layouts.core-admin title="Cuentas · Core Admin">
    <div class="toolbar">
        <div><h1>Cuentas</h1><p>Clientes independientes registrados en el Core.</p></div>
        <a class="button" href="{{ route('admin.accounts.create') }}">Nueva cuenta</a>
    </div>
    <div class="card">
        <table>
            <thead><tr><th>Nombre</th><th>RUC</th><th>Database name</th><th>Estado</th><th>Creada</th><th>Acciones</th></tr></thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td><a href="{{ route('admin.accounts.show', $account) }}">{{ $account->name }}</a></td>
                        <td>{{ $account->ruc }}</td><td>{{ $account->database_name }}</td><td class="status">{{ $account->status }}</td>
                        <td>{{ $account->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="actions">
                            <a class="button secondary" href="{{ route('admin.accounts.edit', $account) }}">Editar</a>
                            <form class="inline" method="POST" action="{{ route('admin.accounts.status', $account) }}">@csrf @method('PATCH')<button type="submit">{{ $account->status === 'active' ? 'Desactivar' : 'Activar' }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No hay cuentas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $accounts->links() }}
    </div>
</x-layouts.core-admin>
