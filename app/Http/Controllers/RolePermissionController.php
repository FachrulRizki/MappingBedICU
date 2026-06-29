<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public static function permissionMatrix(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'icon'  => 'view-grid',
                'perms' => [
                    'dashboard:view' => ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'],
                ],
            ],

            'booking_external' => [
                'label' => 'Booking External',
                'icon'  => 'home',
                'perms' => [
                    'booking_ext:view'               => ['admin', 'admisi', 'petugas_icu'],
                    'booking_ext:create'             => ['admin', 'admisi'],
                    'booking_ext:konfirmasi_bed'     => ['admin', 'petugas_icu'],
                    'booking_ext:waiting_list'       => ['admin', 'petugas_icu'],
                    'booking_ext:tolak'              => ['admin', 'petugas_icu'],
                    'booking_ext:verifikasi_pasien'  => ['admin', 'admisi'],
                ],
            ],

            'booking_internal' => [
                'label' => 'Booking Internal (SPRI)',
                'icon'  => 'document-text',
                'perms' => [
                    'booking_int:view'          => ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'],
                    'booking_int:create'        => ['admin', 'petugas_ruang'],
                    'booking_int:approve'       => ['admin', 'admisi'],
                    'booking_int:tolak_admisi'  => ['admin', 'admisi'],
                    'booking_int:verifikasi_bed'=> ['admin', 'petugas_icu'],
                    'booking_int:waiting_list'  => ['admin', 'petugas_icu'],
                    'booking_int:tolak_icu'     => ['admin', 'petugas_icu'],
                ],
            ],

            'denah_bed' => [
                'label' => 'Denah Bed ICU',
                'icon'  => 'view-boards',
                'perms' => [
                    'denah_bed:view' => ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'],
                ],
            ],

            'settings_users' => [
                'label' => 'Kelola User',
                'icon'  => 'users',
                'perms' => [
                    'users:view'           => ['admin'],
                    'users:create'         => ['admin'],
                    'users:edit'           => ['admin'],
                    'users:delete'         => ['admin'],
                    'users:reset_password' => ['admin'],
                ],
            ],

            'settings_roles' => [
                'label' => 'Settings - Role',
                'icon'  => 'shield-check',
                'perms' => [
                    'roles:view' => ['admin'],
                ],
            ],

            'activity_log' => [
                'label' => 'Log Aktivitas',
                'icon'  => 'clipboard-list',
                'perms' => [
                    'activity_log:view' => ['admin'],
                ],
            ],
        ];
    }

    public static function permissionCount(): array
    {
        $counts = ['admin' => 0, 'admisi' => 0, 'petugas_icu' => 0, 'petugas_ruang' => 0];
        foreach (self::permissionMatrix() as $module) {
            foreach ($module['perms'] as $roles) {
                foreach ($roles as $role) {
                    if (isset($counts[$role])) {
                        $counts[$role]++;
                    }
                }
            }
        }
        return $counts;
    }

    public function index(): Response
    {
        $roles = [
            ['value' => 'admin',         'label' => 'Administrator',   'color' => '#E0923A',
             'keycloak_role' => 'icu-admin'],
            ['value' => 'admisi',        'label' => 'Petugas Admisi',  'color' => '#4A90D9',
             'keycloak_role' => 'icu-admisi'],
            ['value' => 'petugas_icu',   'label' => 'Petugas ICU',     'color' => '#2DD9A4',
             'keycloak_role' => 'icu-petugas-icu'],
            ['value' => 'petugas_ruang', 'label' => 'Petugas Ruang',   'color' => '#D9517A',
             'keycloak_role' => 'icu-petugas-ruang'],
        ];

        $userCounts = User::selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        return Inertia::render('Settings/Roles', [
            'matrix'          => self::permissionMatrix(),
            'permissionCounts'=> self::permissionCount(),
            'roles'           => $roles,
            'userCounts'      => $userCounts,
            'flash'           => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function updateUserRole(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,admisi,petugas_icu,petugas_ruang',
        ]);

        $user = User::findOrFail($id);

        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        if ($currentUser && $currentUser->id === $user->id) {
            return back()->with('error', 'Tidak dapat mengubah role diri sendiri dari halaman ini.');
        }

        // Blokir perubahan role untuk user Keycloak — kelola di Keycloak Admin Console
        if ($user->isKeycloakUser()) {
            return back()->with('error',
                "Role {$user->name} dikelola oleh Keycloak SSO. "
                // "Ubah role melalui Keycloak Admin Console → Realm Roles → Assign ke user."
            );
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Role {$user->name} diubah ke {$validated['role']}.");
    }
}
