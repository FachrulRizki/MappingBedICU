<!DOCTYPE html>
<html lang="id" class="h-full dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <meta name="theme-color" content="#0D1A17" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <link rel="icon" type="image/png" href="{{ asset('images/logo-urip.png') }}">
    <title>{{ config('app.name', 'ICU Monitoring') }}</title>
    {{-- Prevent theme flash: apply saved theme before CSS loads --}}
    <script>
        (function() {
            var t = localStorage.getItem('icu-theme');
            document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @routes
    @inertiaHead
</head>
<body class="h-full antialiased">
    @inertia
</body>
</html>
