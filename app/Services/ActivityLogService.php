<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public function log(
        string  $jenisAktivitas,
        string  $aktivitas,
        ?string $module      = null,
        ?int    $subjectId   = null,
        ?string $subjectType = null,
        ?array  $properties  = null,
    ): void {
        // Kumpulkan data sekarang (dalam request context) sebelum dispatch
        $user      = Auth::user();
        $userId    = $user?->id;
        $userName  = $user?->name;
        $userRole  = $user?->role;
        $ipAddress = Request::ip();
        $userAgent = substr(Request::userAgent() ?? '', 0, 300);

        // Dispatch ke queue agar tidak memblokir response — fire-and-forget
        dispatch(static function () use (
            $userId, $userName, $userRole,
            $jenisAktivitas, $aktivitas, $module,
            $subjectId, $subjectType, $properties,
            $ipAddress, $userAgent
        ) {
            try {
                ActivityLog::create([
                    'user_id'         => $userId,
                    'user_name'       => $userName,
                    'user_role'       => $userRole,
                    'jenis_aktivitas' => $jenisAktivitas,
                    'aktivitas'       => $aktivitas,
                    'module'          => $module,
                    'subject_id'      => $subjectId,
                    'subject_type'    => $subjectType,
                    'properties'      => $properties,
                    'ip_address'      => $ipAddress,
                    'user_agent'      => $userAgent,
                ]);
            } catch (\Throwable $e) {
                Log::error('[ActivityLog] ' . $e->getMessage());
            }
        })->afterResponse();
    }

    // Auth

    public function loginLog(): void
    {
        $this->log('Autentikasi', 'Melakukan login', 'auth');
    }

    public function logoutLog(): void
    {
        $this->log('Autentikasi', 'Melakukan logout', 'auth');
    }

    // Booking External

    public function bookingBaru(int $id, string $namaPasien): void
    {
        $this->log('Buat Data', "Membuat booking ICU untuk {$namaPasien}", 'booking_external', $id, 'IcuBookingExternal');
    }

    public function verifikasiPasien(int $id, string $namaPasien, string $noMr): void
    {
        $this->log('Verifikasi Pasien', "Verifikasi {$namaPasien} — No. MR: {$noMr}", 'booking_external', $id, 'IcuBookingExternal');
    }

    // SPRI Internal (Admisi)

    public function approveSpri(int $id, string $namaPasien): void
    {
        $this->log('Setujui Data', "Menyetujui BU SPRI untuk {$namaPasien}", 'spri_internal', $id, 'IcuSpriInternal');
    }

    public function tolakSpriAdmisi(int $id, string $namaPasien, string $alasan): void
    {
        $this->log('Tolak Data', "Menolak BU SPRI {$namaPasien}: {$alasan}", 'spri_internal', $id, 'IcuSpriInternal');
    }

    // Petugas ICU─

    public function konfirmasibed(int $id, string $namaPasien, string $namaBed): void
    {
        $this->log('Konfirmasi Bed', "Konfirmasi bed {$namaBed} untuk {$namaPasien}", 'booking_external', $id, 'IcuBookingExternal');
    }

    public function tolakBookingIcu(int $id, string $namaPasien, string $alasan): void
    {
        $this->log('Tolak Data', "Menolak booking ICU {$namaPasien}: {$alasan}", 'booking_external', $id, 'IcuBookingExternal');
    }

    public function verifikasibed(int $id, string $namaPasien, string $namaBed): void
    {
        $this->log('Verifikasi Bed', "Verifikasi bed {$namaBed} untuk SPRI {$namaPasien}", 'spri_internal', $id, 'IcuSpriInternal');
    }

    public function tolakSpriIcu(int $id, string $namaPasien, string $alasan): void
    {
        $this->log('Tolak Data', "Menolak BU SPRI {$namaPasien} oleh ICU: {$alasan}", 'spri_internal', $id, 'IcuSpriInternal');
    }

    public function pindahBedExt(int $id, string $namaPasien, string $bedLama, string $bedBaru): void
    {
        $this->log('Pindah Bed', "Pindah bed {$namaPasien}: {$bedLama} → {$bedBaru}", 'booking_external', $id, 'IcuBookingExternal');
    }

    public function pindahBedInt(int $id, string $namaPasien, string $bedLama, string $bedBaru): void
    {
        $this->log('Pindah Bed', "Pindah bed {$namaPasien}: {$bedLama} → {$bedBaru}", 'spri_internal', $id, 'IcuSpriInternal');
    }

    // Auto-detect masuk ICU (via STATUS_KAMAR ISI)

    public function masukIcu(int $id, string $namaPasien, string $namaBed, string $module, string $subjectType): void
    {
        $this->log(
            'Pasien Masuk ICU',
            "Pasien {$namaPasien} terdeteksi sudah menempati bed {$namaBed} (STATUS_KAMAR = ISI)",
            $module,
            $id,
            $subjectType
        );
    }

    // Auto-detect keluar ICU (via STATUS_KAMAR KOSONG)
    public function keluarIcu(int $id, string $namaPasien, string $namaBed, string $module, string $subjectType): void
    {
        $this->log(
            'Pasien Keluar ICU',
            "Pasien {$namaPasien} keluar ICU — bed {$namaBed} dilepas oleh Bed Management (STATUS_KAMAR = KOSONG)",
            $module,
            $id,
            $subjectType
        );
    }

    // Petugas Ruang
    public function buatSpri(int $id, string $namaPasien): void
    {
        $this->log('Buat Data', "Membuat BU SPRI (Booking ICU) untuk {$namaPasien}", 'spri_internal', $id, 'IcuSpriInternal');
    }
}
