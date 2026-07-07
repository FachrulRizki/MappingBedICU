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

        // Admin selalu full akses (sama seperti CheckRole sebelumnya)
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Ambil permissions dari session (diisi oleh SyncKeycloakRole)
        $userPermissions = $request->session()->get('keycloak_permissions', []);

        // ── FALLBACK: Keycloak Authorization belum diaktifkan ──────────────
        // Sebelum admin Keycloak setup Authorization Services,
        // session 'keycloak_permissions' akan kosong.
        // Gunakan role-based fallback supaya aplikasi tetap jalan.
        if (empty($userPermissions)) {
            return $this->fallbackRoleCheck($request, $next, $user, $requiredPermissions);
        }

        // ── PERMISSION CHECK dari Keycloak Authorization ───────────────────
        // Cek apakah user punya salah satu dari permissions yang diminta (OR logic)
        if ($this->permissionService->hasAnyPermission($userPermissions, $requiredPermissions)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    private function fallbackRoleCheck(Request $request, Closure $next, $user, array $requiredPermissions): Response
    {
        // Mapping: permission → role yang boleh akses
        $permissionRoleMap = [
            // Dashboard
            'dashboard:view'                => ['admisi', 'petugas_icu', 'petugas_ruang'],

            // Booking External (Menu Admisi)
            'booking_ext:create'            => ['admisi'],
            'booking_ext:view'              => ['admisi', 'petugas_icu'],
            'booking_ext:verifikasi'        => ['admisi'],
            'booking_ext:konfirmasi_bed'    => ['petugas_icu'],
            'booking_ext:tolak'             => ['admisi', 'petugas_icu'],
            'booking_ext:waiting_list'      => ['petugas_icu'],

            // Booking Internal (Menu ICU & Petugas)
            'booking_int:view'              => ['admisi', 'petugas_icu', 'petugas_ruang'],
            'booking_int:create'            => ['petugas_ruang'],
            'booking_int:approve'           => ['admisi'],
            'booking_int:tolak_admisi'      => ['admisi'],
            'booking_int:verifikasi_bed'    => ['petugas_icu'],
            'booking_int:tolak_icu'         => ['petugas_icu'],
            'booking_int:waiting_list'      => ['petugas_icu'],

            // Denah Bed
            'denah_bed:view'                => ['admisi', 'petugas_icu', 'petugas_ruang'],

            // Settings
            'settings_users:view'           => [],
            'settings_users:create'         => [],
            'settings_users:edit'           => [],
            'settings_users:delete'         => [],
            'settings_users:reset_password' => [],
            'settings_roles:view'           => [],
            'settings_roles:edit'           => [],

            // Activity Log
            'activity_log:view'             => [],
        ];

        foreach ($requiredPermissions as $permission) {
            $allowedRoles = $permissionRoleMap[$permission] ?? [];
            if (in_array($user->role, $allowedRoles, true)) {
                return $next($request);
            }
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}