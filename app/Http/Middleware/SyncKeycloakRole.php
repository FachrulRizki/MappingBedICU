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

        if 
        (
            $user && $user->auth_provider === 'keycloak' && $request->session()->has('keycloak_token_payload')
        ) 
        {
            $tokenPayload = $request->session()->get('keycloak_token_payload', []);

            // Sync role dari token Keycloak
            $newRole = $this->keycloak->resolveRoleFromToken($tokenPayload);
            if ($user->role !== $newRole) {
                Log::info("[SyncKeycloakRole] Role di-sync: {$user->name} {$user->role} → {$newRole}");
                $user->update(['role' => $newRole]);
                Auth::setUser($user->fresh());
            }

            // Sync permissions dari Keycloak Authorization Services ke session
            if ($user->role !== 'admin') {
                $permissions = $this->keycloak->extractPermissionsFromToken($tokenPayload);
                $request->session()->put('keycloak_permissions', $permissions);
                Log::debug('[SyncKeycloakRole] Permissions: ' . (implode(', ', $permissions) ?: '(kosong)'));
            }
        }

        return $next($request);
    }
}
