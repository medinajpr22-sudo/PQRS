<!DOCTYPE html>
@php
    // El portal público de cada empresa siempre se muestra en modo claro (colores de marca).
    $forceLight = tenancy()->initialized && ! request()->is('admin', 'admin/*');
    $tenantTheme = tenancy()->initialized ? tenant()->resolvedTheme() : null;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ! $forceLight && ($appearance ?? 'system') == 'dark']) @if($forceLight) data-force-light @endif data-site-name="{{ tenancy()->initialized ? tenant('name') : config('app.name') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $forceLight ? "light" : ($appearance ?? "system") }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @if ($tenantTheme && $tenantTheme['logo_url'])
        <link rel="icon" href="{{ $tenantTheme['logo_url'] }}">
        @else
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @fonts

        @if ($tenantTheme && $tenantTheme['font_url'])
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="{{ $tenantTheme['font_url'] }}" rel="stylesheet">
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ tenancy()->initialized ? tenant('name') : config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
