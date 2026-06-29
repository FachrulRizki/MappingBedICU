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
    public function __construct(
        private readonly KeycloakService $keycloak,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (
            $user &&
            $user->auth_provider === 'keycloak' &&
            $request->session()->has('keycloak_realm_roles')
        ) {
            $realmRoles = $request->session()->get('keycloak_realm_roles', []);
            // Cek apakah ada token payload lengkap di session (termasuk client roles)
            $tokenPayload = $request->session()->get('keycloak_token_payload', []);
            $newRole = !empty($tokenPayload)
                ? $this->keycloak->resolveRoleFromToken($tokenPayload)
                : $this->keycloak->mapRole($realmRoles);

            // Selalu sync role dari Keycloak — DB tidak boleh override Keycloak
            if ($user->role !== $newRole) {
                Log::info("[SyncKeycloakRole] Role di-sync: {$user->name} {$user->role} → {$newRole} (dari Keycloak)");
                $user->update(['role' => $newRole]);
                Auth::setUser($user->fresh());
            }
        }

        return $next($request);
    }
}
