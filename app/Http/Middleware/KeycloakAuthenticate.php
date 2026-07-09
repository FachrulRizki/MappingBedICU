<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeycloakAuthenticate
{
    public function __construct(
        private readonly KeycloakService $keycloak,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('keycloak.enabled', true)) {
            return $next($request);
        }

        $accessToken = session('keycloak_access_token');

        if (! $accessToken) {
            return redirect()->route('login');
        }

        try {
            $info = $this->keycloak->introspectToken($accessToken);
        } catch (\Throwable) {
            return redirect()->route('login')->with('error', 'Layanan autentikasi tidak tersedia.');
        }

        if (! empty($info['active'])) {
            return $next($request);
        }

        // Token expired — coba refresh sekali
        $newTokens = $this->keycloak->refreshToken(session('keycloak_refresh_token', ''));

        if (! empty($newTokens['access_token'])) {
            session([
                'keycloak_access_token'  => $newTokens['access_token'],
                'keycloak_refresh_token' => $newTokens['refresh_token'] ?? session('keycloak_refresh_token'),
            ]);
            return $next($request);
        }

        $request->session()->flush();
        return redirect()->route('login');
    }
}
