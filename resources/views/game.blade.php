<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Resource Legends - Онлайн игра за събиране на ресурси</title>
    @vite('resources/css/app.css')
</head>
<body>
    <div id="app"></div>
    <script>
        window.locale = '{{ app()->getLocale() }}';
        window.translations = {
            global: @json(__('global')),
            settings: @json(__('settings')),
            menu: @json(__('menu')),
            city: @json(__('city')),
            home: @json(__('home')),
            map: @json(__('map')),
        };
    </script>
    @vite('resources/js/app.js')
</body>
</html>