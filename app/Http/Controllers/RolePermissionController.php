<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    /** Matrix permission per role — dipakai halaman Settings/Roles sebagai referensi. */
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
                    'booking_ext:view'              => ['admin', 'admisi', 'petugas_icu'],
                    'booking_ext:create'            => ['admin', 'admisi'],
                    'booking_ext:konfirmasi_bed'    => ['admin', 'petugas_icu'],
                    'booking_ext:waiting_list'      => ['admin', 'petugas_icu'],
                    'booking_ext:tolak'             => ['admin', 'petugas_icu'],
                    'booking_ext:verifikasi_pasien' => ['admin', 'admisi'],
                ],
            ],
            'booking_internal' => [
                'label' => 'Booking Internal (SPRI)',
                'icon'  => 'document-text',
                'perms' => [
                    'booking_int:view'           => ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'],
                    'booking_int:create'         => ['admin', 'petugas_ruang'],
                    'booking_int:approve'        => ['admin', 'admisi'],
                    'booking_int:tolak_admisi'   => ['admin', 'admisi'],
                    'booking_int:verifikasi_bed' => ['admin', 'petugas_icu'],
                    'booking_int:waiting_list'   => ['admin', 'petugas_icu'],
                    'booking_int:tolak_icu'      => ['admin', 'petugas_icu'],
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
                    'users:view'  => ['admin'],
                    'users:edit'  => ['admin'],
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

    public function index(): Response
    {
        $roles = [
            ['value' => 'admin',         'label' => 'Administrator',  'color' => '#E0923A', 'keycloak_role' => 'icu-admin'],
            ['value' => 'admisi',        'label' => 'Petugas Admisi', 'color' => '#4A90D9', 'keycloak_role' => 'icu-admisi'],
            ['value' => 'petugas_icu',   'label' => 'Petugas ICU',    'color' => '#2DD9A4', 'keycloak_role' => 'icu-petugas-icu'],
            ['value' => 'petugas_ruang', 'label' => 'Petugas Ruang',  'color' => '#D9517A', 'keycloak_role' => 'icu-petugas-ruang'],
        ];

        $userCounts = User::selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        return Inertia::render('Settings/Roles', [
            'matrix'     => self::permissionMatrix(),
            'roles'      => $roles,
            'userCounts' => $userCounts,
            'flash'      => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
