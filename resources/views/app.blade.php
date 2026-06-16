<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <meta name="theme-color" content="#F0F4F8" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <link rel="icon" type="image/png" href="{{ asset('images/logo-urip.png') }}">
    <title>{{ config('app.name', 'ICU Monitoring') }}</title>
    {{-- Anti-flash: terapkan tema tersimpan sebelum CSS dimuat --}}
    <script>
        (function() {
            try {
                var t = localStorage.getItem('icu-theme');
                document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
            } catch(e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
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
