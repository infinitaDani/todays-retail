<x-layouts.tenant title="Dashboard" subtitle="Resumen de tu sesión activa">
    <div class="tr-card">
        <p class="text-muted mb-4">Bienvenida a Today's Retail. Tu sesión está lista en la cuenta seleccionada.</p>
        <div class="row g-3">
            <div class="col-md-4"><div class="border rounded p-3 h-100"><span class="text-muted small d-block mb-1">Usuario</span><strong>{{ auth()->user()->name }}</strong></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><span class="text-muted small d-block mb-1">Cuenta activa</span><strong>{{ $account->name }}</strong></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><span class="text-muted small d-block mb-1">Rol en esta cuenta</span><strong>{{ $membership->role->name }}</strong></div></div>
        </div>
    </div>

    <div class="actions mt-3">
        @if (in_array(strtolower(auth()->user()->email), config('core_admin.emails', []), true))
            <a class="btn btn-outline-secondary" href="{{ route('admin.accounts.index') }}">Core Admin</a>
        @endif

        @if ($canSwitchAccounts)
            <a class="btn btn-outline-secondary" href="{{ route('accounts.select') }}">Cambiar de cuenta</a>
        @endif
    </div>
</x-layouts.tenant>
