<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Core Admin · Today\'s Retail' }}</title>
        <style>
            :root { color: #182230; background: #f5f7fa; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
            body { margin: 0; } main { max-width: 1100px; margin: 0 auto; padding: 32px 24px 64px; }
            header { background: #101828; color: #fff; } header > div { max-width: 1100px; margin: 0 auto; padding: 16px 24px; display: flex; align-items: center; gap: 24px; }
            nav { display: flex; gap: 16px; } nav a { color: #d0d5dd; text-decoration: none; } nav a:hover { color: #fff; }
            h1 { margin: 0 0 8px; } h2 { margin-top: 32px; } p { color: #475467; } .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: 20px 0; }
            .card { background: #fff; border: 1px solid #e4e7ec; border-radius: 12px; padding: 24px; margin-top: 20px; }
            table { border-collapse: collapse; width: 100%; } th, td { border-bottom: 1px solid #eaecf0; padding: 12px 8px; text-align: left; vertical-align: top; } th { color: #475467; font-size: .85rem; }
            label { display: block; font-weight: 600; margin: 16px 0 6px; } input, select { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #d0d5dd; border-radius: 7px; font: inherit; }
            button, .button { display: inline-block; background: #155eef; border: 0; border-radius: 7px; color: #fff; cursor: pointer; font: inherit; font-weight: 600; padding: 9px 13px; text-decoration: none; }
            .button.secondary, button.secondary { background: #fff; border: 1px solid #d0d5dd; color: #344054; } .button.danger, button.danger { background: #b42318; }
            .actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; } .inline { display: inline; } .error { color: #b42318; font-size: .9rem; margin-top: 4px; }
            .notice { background: #ecfdf3; border: 1px solid #abefc6; border-radius: 8px; color: #067647; margin: 16px 0; padding: 12px; }
            .muted { color: #667085; font-size: .9rem; } .status { font-weight: 600; text-transform: capitalize; }
        </style>
    </head>
    <body>
        <header>
            <div>
                <strong>Today's Retail · Core Admin</strong>
                <nav>
                    <a href="{{ route('admin.accounts.index') }}">Cuentas</a>
                    <a href="{{ route('admin.users.index') }}">Usuarios</a>
                    <a href="{{ route('admin.roles.index') }}">Roles</a>
                    <a href="{{ route('dashboard') }}">Volver al dashboard</a>
                </nav>
            </div>
        </header>
        <main>
            @if (session('success'))
                <div class="notice">{{ session('success') }}</div>
            @endif
            {{ $slot }}
        </main>
    </body>
</html>
