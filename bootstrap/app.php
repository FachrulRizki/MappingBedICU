<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Global web stack — berjalan di setiap request web
        $middleware->web(append: [
            \App\Http\Middleware\SyncKeycloakRole::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Alias middleware — dipakai langsung di routes
        $middleware->alias([
            'permission'    => \App\Http\Middleware\CheckPermission::class,
            'keycloak.auth' => \App\Http\Middleware\KeycloakAuthenticate::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
