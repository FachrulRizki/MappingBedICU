<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SyncKeycloakRole
{
    // Interval introspection: 300 detik = 5 menit
    private const INTROSPECT_INTERVAL = 300;

    public function __construct(
        private readonly KeycloakService $keycloak,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (
            $user &&
            $user->auth_provider === 'keycloak' &&
            $request->session()->has('keycloak_token_payload')
        ) {
            $tokenPayload = $request->session()->get('keycloak_token_payload', []);

            // Cek ke Keycloak apakah access token masih aktif.
            $accessToken  = $request->session()->get('keycloak_access_token');
            $lastIntrospect = $request->session()->get('keycloak_last_introspect', 0);
            $now = time();

            if ($accessToken && ($now - $lastIntrospect) >= self::INTROSPECT_INTERVAL) {
                $result = $this->keycloak->introspectToken($accessToken);

                if (! empty($result) && ($result['active'] ?? false) === false) {
                    // Token sudah tidak aktif di Keycloak (revoked/expired/user disabled)
                    Log::info("[SyncKeycloakRole] Token tidak aktif — paksa logout: {$user->name}");
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')
                        ->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
                }

                // Update timestamp introspection terakhir
                $request->session()->put('keycloak_last_introspect', $now);
                Log::debug("[SyncKeycloakRole] Introspect OK: {$user->name} active=" . ($result['active'] ?? '?'));
            }

            // Sync role dari token
            $newRole = $this->keycloak->resolveRoleFromToken($tokenPayload);
            if ($user->role !== $newRole) {
                Log::info("[SyncKeycloakRole] Role di-sync: {$user->name} {$user->role} → {$newRole}");
                $user->update(['role' => $newRole]);
                Auth::setUser($user->fresh());
            }

            // Sync permissions ke session 
            if ($user->role !== 'admin') {
                $permissions = $this->keycloak->extractPermissionsFromToken($tokenPayload);
                $request->session()->put('keycloak_permissions', $permissions);
                Log::debug('[SyncKeycloakRole] Permissions: ' . (implode(', ', $permissions) ?: '(kosong)'));
            }
        }

        return $next($request);
    }
}
