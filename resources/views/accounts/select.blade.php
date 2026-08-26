<x-layouts.app title="Seleccionar cuenta · Today's Retail">
    <h1>Selecciona una cuenta</h1>
    <p>Elige la cuenta con la que deseas trabajar.</p>

    <form method="POST" action="{{ route('accounts.select.store') }}">
        @csrf

        <label for="account_id">Cuenta</label>
        <select id="account_id" name="account_id" required>
            <option value="">Selecciona una cuenta</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                    {{ $account->name }}
                </option>
            @endforeach
        </select>
        @error('account_id')<div class="error">{{ $message }}</div>@enderror

        <div class="actions">
            <button type="submit">Continuar</button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <div class="actions">
            <button class="secondary" type="submit">Cerrar sesión</button>
        </div>
    </form>
</x-layouts.app>
