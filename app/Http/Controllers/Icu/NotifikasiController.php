<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RolePermissionController;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\RegistrasiPasien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotifikasiController extends Controller
{
    /**
     * Resolve permissions dari session (Keycloak) atau fallback ke role user (local auth).
     */
    private function resolvePermissions(\App\Models\User $user, Request $request): array
    {
        $sessionPerms = $request->session()->get('keycloak_permissions', []);
        if (! empty($sessionPerms)) {
            return $sessionPerms;
        }

        // Fallback untuk local user: derive permissions dari role
        $role   = $user->role ?? '';
        $matrix = RolePermissionController::permissionMatrix();
        $perms  = [];
        foreach ($matrix as $group) {
            foreach ($group['perms'] as $perm => $roles) {
                if (in_array($role, $roles, true)) {
                    $perms[] = $perm;
                }
            }
        }
        return $perms;
    }

    public function poll(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['notifs' => []]);
        }

        $permissions = $this->resolvePermissions($user, $request);
        $userId      = $user->id;

        $cacheKey    = "notif_last_seen_{$userId}";
        $lastSeenRaw = Cache::get($cacheKey);
        $lastSeen    = $lastSeenRaw
            ? \Carbon\Carbon::parse($lastSeenRaw)
            : now()->subMinutes(5);

        $now    = now();
        $notifs = [];

        // ─── Helper: ambil nama pasien dari No_MR ────────────────────────────
        $getNamaPasien = function (string $noMr): string {
            // Cache per No_MR — TTL 5 menit, biar polling 10 detik tidak hammering RSUS
            return Cache::remember('pasien_nama:' . $noMr, 300, function () use ($noMr) {
                try {
                    return RegistrasiPasien::where('No_MR', $noMr)->value('Nama_Pasien') ?? $noMr;
                } catch (\Exception) {
                    return $noMr;
                }
            });
        };

        // ─── ICU ─────────────────────────────────────────────────────────────
        $isIcu = in_array('booking_ext:konfirmasi_bed', $permissions)
               || in_array('booking_int:verifikasi_bed', $permissions);

        if ($isIcu) {
            // Internal baru — sebut nama pasien + asal ruang
            $newInternals = IcuSpriInternal::where('status', 'pending_icu')
                ->where('updated_at', '>', $lastSeen)
                ->get(['No_MR', 'asal_ruang']);

            if ($newInternals->count() > 0) {
                if ($newInternals->count() === 1) {
                    $item    = $newInternals->first();
                    $nama    = $getNamaPasien($item->No_MR);
                    $ruang   = $item->asal_ruang ? " dari {$item->asal_ruang}" : '';
                    $message = "Permintaan ICU: {$nama}{$ruang}";
                } else {
                    $message = "Ada {$newInternals->count()} permintaan ICU baru dari ruang rawat inap";
                }
                $notifs[] = [
                    'type'    => 'new_booking_internal',
                    'sound'   => 'noning_internal',
                    'count'   => $newInternals->count(),
                    'message' => $message,
                ];
            }

            // External baru — sebut nama pasien + asal rujukan
            $newExternals = IcuBookingExternal::where('status', 'pending_icu')
                ->where('updated_at', '>', $lastSeen)
                ->get(['nama_pasien', 'asal_rujukan']);

            if ($newExternals->count() > 0) {
                if ($newExternals->count() === 1) {
                    $item    = $newExternals->first();
                    $asal    = $item->asal_rujukan ? " dari {$item->asal_rujukan}" : '';
                    $message = "Permintaan ICU eksternal: {$item->nama_pasien}{$asal}";
                } else {
                    $message = "Ada {$newExternals->count()} permintaan ICU baru dari pasien eksternal";
                }
                $notifs[] = [
                    'type'    => 'new_booking_external',
                    'sound'   => 'noning_external',
                    'count'   => $newExternals->count(),
                    'message' => $message,
                ];
            }
        }

        // ─── Admisi & Petugas ─────────────────────────────────────────────────
        $isAdmisi  = in_array('booking_ext:create', $permissions)
                   || in_array('booking_ext:verifikasi_pasien', $permissions)
                   || in_array('booking_int:approve', $permissions);
        $isPetugas = in_array('booking_int:create', $permissions);

        // Semua nama/alias user ini yang tersimpan di kolom NameUser
        $petugasNames = array_unique(array_filter([
            $user->name,
            $user->keycloak_username ?? null,
            $user->username          ?? null,
        ]));

        if ($isAdmisi || $isPetugas) {

            // Admisi: bed_confirmed external — sebut nama pasien
            if ($isAdmisi) {
                $confirmed = IcuBookingExternal::where('status', 'bed_confirmed')
                    ->where('confirmed_at', '>', $lastSeen)
                    ->get(['nama_pasien', 'nama_bed']);

                if ($confirmed->count() > 0) {
                    if ($confirmed->count() === 1) {
                        $item    = $confirmed->first();
                        $bed     = $item->nama_bed ? " — Bed {$item->nama_bed}" : '';
                        $message = "Bed dikonfirmasi ICU: {$item->nama_pasien}{$bed}";
                    } else {
                        $message = "{$confirmed->count()} bed dikonfirmasi ICU, segera update di Bed Management";
                    }
                    $notifs[] = [
                        'type'    => 'bed_confirmed',
                        'sound'   => 'ningnong',
                        'count'   => $confirmed->count(),
                        'message' => $message,
                    ];
                }
            }

            // Bed verified internal — petugas hanya miliknya, admisi semua
            $qBedVerified = IcuSpriInternal::where('status', 'bed_verified')
                ->where('verified_at', '>', $lastSeen);

            if ($isPetugas && ! $isAdmisi) {
                $qBedVerified->whereIn('NameUser', $petugasNames);
            }

            $bedVerified = $qBedVerified->get(['No_MR', 'nama_bed', 'asal_ruang']);

            if ($bedVerified->count() > 0) {
                if ($bedVerified->count() === 1) {
                    $item    = $bedVerified->first();
                    $nama    = $getNamaPasien($item->No_MR);
                    $bed     = $item->nama_bed ? " — Bed {$item->nama_bed}" : '';
                    $message = "Bed ICU tersedia untuk {$nama}{$bed}";
                } else {
                    $message = "{$bedVerified->count()} bed ICU diverifikasi untuk pasien Anda";
                }
                $notifs[] = [
                    'type'    => 'bed_verified_internal',
                    'sound'   => 'ningnong',
                    'count'   => $bedVerified->count(),
                    'message' => $message,
                ];
            }

            // Pasien masuk ICU
            $qMasukInt = IcuSpriInternal::where('status', 'masuk_icu')
                ->where('masuk_at', '>', $lastSeen);

            if ($isPetugas && ! $isAdmisi) {
                $qMasukInt->whereIn('NameUser', $petugasNames);
            }

            $masukInt = $qMasukInt->get(['No_MR', 'nama_bed']);
            $masukExt = $isAdmisi
                ? IcuBookingExternal::where('status', 'masuk_icu')
                    ->where('masuk_at', '>', $lastSeen)
                    ->get(['nama_pasien', 'nama_bed'])
                : collect();

            $totalMasuk = $masukInt->count() + $masukExt->count();

            if ($totalMasuk > 0) {
                if ($totalMasuk === 1) {
                    if ($masukInt->count() === 1) {
                        $item    = $masukInt->first();
                        $nama    = $getNamaPasien($item->No_MR);
                        $bed     = $item->nama_bed ? " di Bed {$item->nama_bed}" : '';
                        $message = "{$nama} sudah masuk ICU{$bed}";
                    } else {
                        $item    = $masukExt->first();
                        $bed     = $item->nama_bed ? " di Bed {$item->nama_bed}" : '';
                        $message = "{$item->nama_pasien} sudah masuk ICU{$bed}";
                    }
                } else {
                    $message = "{$totalMasuk} pasien sudah masuk ICU";
                }
                $notifs[] = [
                    'type'    => 'pasien_masuk_icu',
                    'sound'   => 'ningnong',
                    'count'   => $totalMasuk,
                    'message' => $message,
                ];
            }
        }

        Cache::put($cacheKey, $now->toIso8601String(), now()->addHours(12));

        return response()->json([
            'notifs'    => $notifs,
            'server_ts' => $now->toIso8601String(),
            '_debug'    => [
                'permissions'   => $permissions,
                'is_icu'        => $isIcu,
                'is_admisi'     => $isAdmisi,
                'is_petugas'    => $isPetugas,
                'petugas_names' => $petugasNames,
                'last_seen'     => $lastSeen->toIso8601String(),
                // Debug: cek berapa BU milik user ini
                'my_bu_total'   => $isPetugas
                    ? IcuSpriInternal::whereIn('NameUser', $petugasNames)->count()
                    : null,
                'my_bu_verified'=> $isPetugas
                    ? IcuSpriInternal::whereIn('NameUser', $petugasNames)
                        ->where('status', 'bed_verified')
                        ->where('verified_at', '>', $lastSeen)
                        ->count()
                    : null,
            ],
        ]);
    }
}
