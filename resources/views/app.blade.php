<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <meta name="theme-color" content="#15803d" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <title>{{ config('app.name', 'ICU Monitoring') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @routes
    @inertiaHead
</head>
<body class="h-full bg-gray-50 antialiased">
    @inertia
</body>
</html>
