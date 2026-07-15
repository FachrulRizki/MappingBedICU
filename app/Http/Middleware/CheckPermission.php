<?php

namespace App\Http\Middleware;

use App\Services\KeycloakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private readonly KeycloakService $keycloak,
    ) {}

    public function handle(Request $request, Closure $next, string ...$required): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $permissions = $request->session()->get('keycloak_permissions', []);

        if (empty($permissions)) {
            abort(403, 'Sesi tidak memiliki akses. Silakan login ulang.');
        }

        if ($this->keycloak->hasAnyPermission($permissions, $required)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
