{{-- Páginas de error en español (Laravel muestra las suyas en inglés). Sin
     scripts: deben verse aunque falle todo lo demás. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fafafa; color: #18181b; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 30rem; padding: 2rem 1.5rem; text-align: center; }
        .code { font-size: .8rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: #71717a; }
        h1 { font-size: 1.5rem; line-height: 1.3; margin: .5rem 0 1rem; }
        p { margin: 0; color: #52525b; line-height: 1.6; }
        a { display: inline-block; margin-top: 1.75rem; padding: .6rem 1.1rem; border-radius: .6rem; background: #18181b; color: #fff; font-weight: 600; text-decoration: none; }
        @media (prefers-color-scheme: dark) {
            body { background: #09090b; color: #fafafa; }
            p { color: #a1a1aa; }
            a { background: #fafafa; color: #09090b; }
        }
    </style>
</head>
<body>
    <main>
        <div class="code">Error @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">Volver al inicio</a>
    </main>
</body>
</html>
