<?php

namespace App\Http\Middleware;

use App\Services\KeycloakPermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private readonly KeycloakPermissionService $permissionService,
    ) {}

    public function handle(Request $request, Closure $next, string ...$requiredPermissions): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Admin selalu full akses — tidak perlu cek permissions
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Ambil permissions dari session (diisi oleh SyncKeycloakRole saat tiap request)
        $userPermissions = $request->session()->get('keycloak_permissions', []);

        // Permissions wajib ada — sudah divalidasi saat login di handleCallback
        // Jika kosong berarti ada anomali (token expired, session rusak, dll)
        if (empty($userPermissions)) {
            abort(403, 'Sesi Anda tidak memiliki akses. Silakan login ulang.');
        }

        // Cek apakah user punya salah satu dari permissions yang diminta (OR logic)
        if ($this->permissionService->hasAnyPermission($userPermissions, $requiredPermissions)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
