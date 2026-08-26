<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? "Today's Retail" }}</title>
        <style>
            :root { color: #1d2939; background: #f4f7fb; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
            body { margin: 0; min-height: 100vh; }
            main { max-width: 680px; margin: 0 auto; padding: 56px 24px; }
            .card { background: white; border: 1px solid #e4e7ec; border-radius: 16px; box-shadow: 0 12px 28px rgb(16 24 40 / 8%); padding: 32px; }
            h1 { margin: 0 0 8px; font-size: 1.75rem; } p { color: #475467; line-height: 1.5; }
            label { display: block; font-weight: 600; margin: 18px 0 6px; }
            input, select { box-sizing: border-box; width: 100%; padding: 11px 12px; border: 1px solid #d0d5dd; border-radius: 8px; font: inherit; }
            button, .button { display: inline-block; border: 0; border-radius: 8px; background: #155eef; color: white; cursor: pointer; font: inherit; font-weight: 600; padding: 11px 16px; text-decoration: none; }
            button.secondary, .button.secondary { background: #fff; border: 1px solid #d0d5dd; color: #344054; }
            .actions { display: flex; align-items: center; gap: 12px; margin-top: 24px; }
            .error { color: #b42318; font-size: .9rem; margin-top: 6px; }
            .details { margin: 24px 0; padding: 20px; background: #f9fafb; border-radius: 10px; }
            .details dt { color: #667085; font-size: .9rem; } .details dd { font-weight: 600; margin: 3px 0 16px; }
        </style>
    </head>
    <body>
        <main>
            <div class="card">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
