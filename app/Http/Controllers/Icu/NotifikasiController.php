<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotifikasiController extends Controller
{
    /**
     * GET /icu/notifikasi/poll
     *
     * Mengembalikan notifikasi yang relevan berdasarkan role user.
     */
    public function poll(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['notifs' => []]);
        }

        // Permissions disimpan di session (di-sync dari Keycloak oleh SyncKeycloakRole middleware)
        $permissions = $request->session()->get('keycloak_permissions', []);
        $userId      = $user->id;

        // Key cache untuk menyimpan timestamp terakhir kali user di-notif
        $cacheKey    = "notif_last_seen_{$userId}";
        $lastSeenRaw = Cache::get($cacheKey);

        // Pertama kali buka: gunakan 5 menit lalu agar langsung dapat notif yang ada
        $lastSeen = $lastSeenRaw
            ? \Carbon\Carbon::parse($lastSeenRaw)
            : now()->subMinutes(5);

        $now   = now();
        $notifs = [];

        // ── ICU: notif saat ada booking baru (internal atau external) masuk ──
        $isIcu = in_array('booking_ext:konfirmasi_bed', $permissions)
               || in_array('booking_int:verifikasi_bed', $permissions);

        if ($isIcu) {
            // Booking Internal baru (pending_icu) — dari petugas ruang
            $newInternal = IcuSpriInternal::where('status', 'pending_icu')
                ->where('updated_at', '>', $lastSeen)
                ->count();

            // Booking External baru (pending_icu) — dari admisi
            $newExternal = IcuBookingExternal::where('status', 'pending_icu')
                ->where('updated_at', '>', $lastSeen)
                ->count();

            if ($newInternal > 0) {
                $notifs[] = [
                    'type'    => 'new_booking_internal',
                    'sound'   => 'noning_internal',
                    'count'   => $newInternal,
                    'message' => $newInternal === 1
                        ? 'Ada bookingan pasien dari internal nih!'
                        : "Ada {$newInternal} bookingan pasien baru dari internal!",
                ];
            }

            if ($newExternal > 0) {
                $notifs[] = [
                    'type'    => 'new_booking_external',
                    'sound'   => 'noning_external',
                    'count'   => $newExternal,
                    'message' => $newExternal === 1
                        ? 'Ada bookingan pasien dari eksternal nih!'
                        : "Ada {$newExternal} bookingan pasien baru dari eksternal!",
                ];
            }
        }

        // ── Admisi / Petugas Ruang: notif saat bed tersedia & diverifikasi ICU ──
        $isAdmisi  = in_array('booking_ext:create', $permissions)
                   || in_array('booking_ext:verifikasi_pasien', $permissions)
                   || in_array('booking_int:approve', $permissions);
        $isPetugas = in_array('booking_int:create', $permissions);

        if ($isAdmisi || $isPetugas) {
            // External: bed_confirmed baru (ICU konfirmasi bed → admisi perlu update di Bed Management)
            if ($isAdmisi) {
                $newBedConfirmed = IcuBookingExternal::where('status', 'bed_confirmed')
                    ->where('confirmed_at', '>', $lastSeen)
                    ->count();

                if ($newBedConfirmed > 0) {
                    $notifs[] = [
                        'type'    => 'bed_confirmed',
                        'sound'   => 'ningnong',
                        'count'   => $newBedConfirmed,
                        'message' => $newBedConfirmed === 1
                            ? 'Ningnong! ICU sudah konfirmasi bed, segera update di Bed Management!'
                            : "Ningnong! {$newBedConfirmed} bed dikonfirmasi ICU, segera update di Bed Management!",
                    ];
                }
            }

            // Internal: bed_verified baru
            $newBedVerified = IcuSpriInternal::where('status', 'bed_verified')
                ->where('verified_at', '>', $lastSeen)
                ->count();

            if ($newBedVerified > 0) {
                $notifs[] = [
                    'type'    => 'bed_verified_internal',
                    'sound'   => 'ningnong',
                    'count'   => $newBedVerified,
                    'message' => $newBedVerified === 1
                        ? 'Ningnong! ICU sudah verifikasi bed, segera update di Bed Management!'
                        : "Ningnong! {$newBedVerified} bed diverifikasi ICU, segera update di Bed Management!",
                ];
            }

            // Notif pasien sudah masuk ICU (terdeteksi via STATUS_KAMAR ISI oleh monitor)
            $newMasukExt = IcuBookingExternal::where('status', 'masuk_icu')
                ->where('masuk_at', '>', $lastSeen)
                ->count();

            $newMasukInt = IcuSpriInternal::where('status', 'masuk_icu')
                ->where('masuk_at', '>', $lastSeen)
                ->count();

            $totalMasuk = $newMasukExt + $newMasukInt;

            if ($totalMasuk > 0) {
                $notifs[] = [
                    'type'    => 'pasien_masuk_icu',
                    'sound'   => 'ningnong',
                    'count'   => $totalMasuk,
                    'message' => $totalMasuk === 1
                        ? 'Pasien sudah masuk ICU dan menempati bed!'
                        : "{$totalMasuk} pasien sudah masuk ICU dan menempati bed!",
                ];
            }
        }

        // Update timestamp last seen
        Cache::put($cacheKey, $now->toIso8601String(), now()->addHours(12));

        return response()->json([
            'notifs'      => $notifs,
            'server_ts'   => $now->toIso8601String(),
            '_debug'      => [
                'permissions' => $permissions,
                'is_icu'      => $isIcu,
                'is_admisi'   => $isAdmisi,
                'is_petugas'  => $isPetugas,
                'last_seen'   => $lastSeen->toIso8601String(),
            ],
        ]);
    }
}
