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
            help: @json(__('help')),
            map: @json(__('map')),
            notifications: @json(__('notifications')),
            tools: @json(__('tools')),
        };
        window.translations_bg = {
            global: @json(__('global', [], 'bg')),
            settings: @json(__('settings', [], 'bg')),
            menu: @json(__('menu', [], 'bg')),
            city: @json(__('city', [], 'bg')),
            home: @json(__('home', [], 'bg')),
            help: @json(__('help', [], 'bg')),
            map: @json(__('map', [], 'bg')),
            notifications: @json(__('notifications', [], 'bg')),
            tools: @json(__('tools', [], 'bg')),
        };
    </script>
    @vite('resources/js/app.js')
</body>
</html>