<x-layouts.app title="Dashboard · Today's Retail">
    <h1>Bienvenida a Today's Retail</h1>
    <p>Tu sesión está lista en la cuenta seleccionada.</p>

    <dl class="details">
        <dt>Usuario</dt>
        <dd>{{ auth()->user()->name }}</dd>

        <dt>Cuenta activa</dt>
        <dd>{{ $account->name }}</dd>

        <dt>Rol en esta cuenta</dt>
        <dd>{{ $membership->role->name }}</dd>
    </dl>

    <div class="actions">
        @if (in_array(strtolower(auth()->user()->email), config('core_admin.emails', []), true))
            <a class="button secondary" href="{{ route('admin.accounts.index') }}">Core Admin</a>
        @endif

        @if ($canSwitchAccounts)
            <a class="button secondary" href="{{ route('accounts.select') }}">Cambiar de cuenta</a>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Cerrar sesión</button>
        </form>
    </div>
</x-layouts.app>
