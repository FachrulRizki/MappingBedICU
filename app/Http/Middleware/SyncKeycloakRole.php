<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SyncKeycloakRole
{
    private const INTROSPECT_INTERVAL = 300;
    private const ROLE_SYNC_INTERVAL  = 120;

    public function __construct(
        private readonly KeycloakService $keycloak,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user?->auth_provider === 'keycloak' && $request->session()->has('keycloak_token_payload')) {
            $redirect = $this->sync($request, $user);
            if ($redirect) return $redirect;
        }

        return $next($request);
    }

    private function sync(Request $request, $user): ?\Illuminate\Http\RedirectResponse
    {
        $accessToken = $request->session()->get('keycloak_access_token');

        if ($accessToken) {
            $last = $request->session()->get('keycloak_last_introspect', 0);

            if ((time() - $last) >= self::INTROSPECT_INTERVAL) {
                $info = $this->keycloak->introspectToken($accessToken);

                if (! empty($info) && ($info['active'] ?? false) === false) {
                    // Coba refresh token sebelum logout paksa
                    $refreshToken = $request->session()->get('keycloak_refresh_token');

                    if ($refreshToken) {
                        $newTokens = $this->keycloak->refreshToken($refreshToken);

                        if (! empty($newTokens['access_token'])) {
                            $newPayload = $this->keycloak->decodeJwtPayload($newTokens['access_token']);
                            $request->session()->put([
                                'keycloak_access_token'    => $newTokens['access_token'],
                                'keycloak_refresh_token'   => $newTokens['refresh_token'] ?? $refreshToken,
                                'keycloak_token_payload'   => $newPayload,
                                'keycloak_last_introspect' => time(),
                            ]);
                            return null;
                        }
                    }

                    Log::info("[SyncKeycloakRole] Token tidak aktif, logout: {$user->name}");
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
                }

                $request->session()->put('keycloak_last_introspect', time());
            }
        }

        $payload = $request->session()->get('keycloak_token_payload', []);

        if (empty($payload)) return null;

        // Sync role — throttle DB write tiap ROLE_SYNC_INTERVAL detik per user
        $newRole       = $this->keycloak->resolveRoleFromToken($payload);
        $roleSyncKey   = "keycloak_role_synced_{$user->id}";
        $roleSyncDone  = Cache::get($roleSyncKey);

        if ($user->role !== $newRole) {
            Log::info("[SyncKeycloakRole] Role sync: {$user->name} [{$user->role}→{$newRole}]");
            $user->update(['role' => $newRole]);
            Auth::setUser($user->fresh());
            Cache::put($roleSyncKey, $newRole, self::ROLE_SYNC_INTERVAL);
        } elseif (! $roleSyncDone) {
            // Role sama tapi cache belum ada — refresh cache tanpa DB write
            Cache::put($roleSyncKey, $newRole, self::ROLE_SYNC_INTERVAL);
        }

        // Sync permissions dari token ke session — hanya tulis jika ada perubahan
        $permissions   = $this->keycloak->extractPermissionsFromToken($payload);
        $existingPerms = $request->session()->get('keycloak_permissions', []);
        $permsDiffer   = count($permissions) !== count($existingPerms)
                      || array_diff($permissions, $existingPerms)
                      || array_diff($existingPerms, $permissions);

        if ($permsDiffer) {
            $request->session()->put('keycloak_permissions', $permissions);
        }

        return null;
    }
}
