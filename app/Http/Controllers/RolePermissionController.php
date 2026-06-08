<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Role & Permission — admin dapat melihat dan mengubah role user
 * sekaligus melihat matriks akses per role.
 */
class RolePermissionController extends Controller
{
    /** Definisi permission per role — single source of truth */
    public static function permissionMatrix(): array
    {
        return [
            'dashboard' => [
                'label'  => 'Dashboard',
                'icon'   => 'view-grid',
                'perms'  => [
                    'view' => ['admin','admisi','petugas_icu','petugas_ruang'],
                ],
            ],
            'booking_external' => [
                'label' => 'Booking External',
                'icon'  => 'home',
                'perms' => [
                    'view'              => ['admin','admisi','petugas_icu'],
                    'buat_booking'      => ['admin','admisi'],
                    'konfirmasi_icu'    => ['admin','petugas_icu'],
                    'tolak_icu'         => ['admin','petugas_icu'],
                    'validasi_admisi'   => ['admin','admisi'],
                    'link_pasien_tiba'  => ['admin','admisi'],
                    'verifikasi_bed'    => ['admin','admisi'],
                    'konfirmasi_masuk'  => ['admin','petugas_icu'],
                    'pulangkan'         => ['admin','petugas_icu'],
                ],
            ],
            'spri_internal' => [
                'label' => 'Surat Permintaan ICU',
                'icon'  => 'document-text',
                'perms' => [
                    'view'              => ['admin','admisi','petugas_icu','petugas_ruang'],
                    'buat_surat'        => ['admin','petugas_ruang'],
                    'approve_admisi'    => ['admin','admisi'],
                    'tolak_admisi'      => ['admin','admisi'],
                    'booking_bed_icu'   => ['admin','petugas_icu'],
                    'tolak_icu'         => ['admin','petugas_icu'],
                    'verifikasi_admisi' => ['admin','admisi'],
                    'konfirmasi_masuk'  => ['admin','petugas_icu'],
                    'pulangkan'         => ['admin','petugas_icu'],
                ],
            ],
            'denah_bed' => [
                'label' => 'Denah Bed ICU',
                'icon'  => 'view-boards',
                'perms' => [
                    'view' => ['admin','admisi','petugas_icu','petugas_ruang'],
                ],
            ],
            'pasien_icu' => [
                'label' => 'Pasien ICU',
                'icon'  => 'user-group',
                'perms' => [
                    'view'      => ['admin','admisi','petugas_icu'],
                    'pulangkan' => ['admin','petugas_icu'],
                ],
            ],
            'settings_users' => [
                'label' => 'Kelola User',
                'icon'  => 'users',
                'perms' => [
                    'view'    => ['admin'],
                    'tambah'  => ['admin'],
                    'edit'    => ['admin'],
                    'hapus'   => ['admin'],
                    'reset_pw'=> ['admin'],
                ],
            ],
        ];
    }

    public function index(): Response
    {
        $roles = [
            ['value' => 'admin',         'label' => 'Administrator',   'color' => '#E0923A'],
            ['value' => 'admisi',        'label' => 'Petugas Admisi',  'color' => '#4A90D9'],
            ['value' => 'petugas_icu',   'label' => 'Petugas ICU',     'color' => '#2DD9A4'],
            ['value' => 'petugas_ruang', 'label' => 'Petugas Ruang',   'color' => '#D9517A'],
        ];

        // Statistik users per role
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

    /**
     * Update role seorang user (shortcut dari halaman ini).
     */
    public function updateUserRole(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,admisi,petugas_icu,petugas_ruang',
        ]);

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat mengubah role diri sendiri dari halaman ini.');
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Role {$user->name} diubah ke {$validated['role']}.");
    }
}
