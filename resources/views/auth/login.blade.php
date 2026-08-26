<x-layouts.app title="Iniciar sesión · Today's Retail">
    <h1>Today's Retail</h1>
    <p>Inicia sesión para continuar.</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <div class="actions">
            <button type="submit">Ingresar</button>
        </div>
    </form>
</x-layouts.app>
