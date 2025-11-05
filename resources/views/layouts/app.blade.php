<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#1e88e5">
    <!-- Apple -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='192' height='192'><rect width='192' height='192' fill='%231e88e5' rx='16'/><text x='50%' y='50%' font-family='Arial, Helvetica, sans-serif' font-size='56' fill='%23fff' text-anchor='middle' dominant-baseline='central'>VJ</text></svg>">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite('resources/css/app.css')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/">{{ config('app.name', 'Laravel') }}</a>
            <div>
                <a class="nav-link d-inline" href="/releases">Releases</a>
            </div>
        </div>
    </nav>

    <main class="py-4">
        @yield('content')
    </main>

    <footer class="text-center text-muted py-4">
        <small>&copy; {{ date('Y') }} {{ config('app.name') }}</small>
    </footer>

    @vite('resources/js/app.js')
</body>
</html>
