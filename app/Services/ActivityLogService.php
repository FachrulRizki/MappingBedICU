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
        try {
            $user = Auth::user();

            ActivityLog::create([
                'user_id'         => $user?->id,
                'user_name'       => $user?->name,
                'user_role'       => $user?->role,
                'jenis_aktivitas' => $jenisAktivitas,
                'aktivitas'       => $aktivitas,
                'module'          => $module,
                'subject_id'      => $subjectId,
                'subject_type'    => $subjectType,
                'properties'      => $properties,
                'ip_address'      => Request::ip(),
                'user_agent'      => substr(Request::userAgent() ?? '', 0, 300),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ActivityLog] Gagal mencatat: ' . $e->getMessage());
        }
    }

    // ── Auth ───────────────────────────────────────────────────────────────

    public function loginLog(): void
    {
        $this->log('Autentikasi', 'Melakukan login', 'auth');
    }

    public function logoutLog(): void
    {
        $this->log('Autentikasi', 'Melakukan logout', 'auth');
    }

    // ── Booking External (Admisi) ──────────────────────────────────────────

    /** Admisi membuat booking ICU baru untuk pasien eksternal */
    public function bookingBaru(int $id, string $namaPasien): void
    {
        $this->log(
            'Buat Data',
            "Membuat booking ICU untuk {$namaPasien}",
            'booking_external',
            $id,
            'IcuBookingExternal',
        );
    }

    /** Admisi memverifikasi data pasien eksternal setelah bed dikonfirmasi ICU */
    public function verifikasiPasien(int $id, string $namaPasien, string $noMr): void
    {
        $this->log(
            'Verifikasi Pasien',
            "Verifikasi pasien {$namaPasien} — No. MR: {$noMr}",
            'booking_external',
            $id,
            'IcuBookingExternal',
        );
    }

    // ── SPRI Internal (Admisi) ─────────────────────────────────────────────

    /** Admisi menyetujui SPRI dari petugas ruang → diteruskan ke ICU */
    public function approveSpri(int $id, string $namaPasien): void
    {
        $this->log(
            'Setujui Data',
            "Menyetujui BU SPRI untuk {$namaPasien} — diteruskan ke ICU",
            'spri_internal',
            $id,
            'IcuSpriInternal',
        );
    }

    /** Admisi menolak SPRI */
    public function tolakSpriAdmisi(int $id, string $namaPasien, string $alasan): void
    {
        $this->log(
            'Tolak Data',
            "Menolak BU SPRI {$namaPasien}: {$alasan}",
            'spri_internal',
            $id,
            'IcuSpriInternal',
        );
    }

    // ── Petugas ICU ────────────────────────────────────────────────────────

    /** ICU mengkonfirmasi bed untuk booking external */
    public function konfirmasibed(int $id, string $namaPasien, string $namaBed): void
    {
        $this->log(
            'Konfirmasi Bed',
            "Konfirmasi bed {$namaBed} untuk {$namaPasien}",
            'booking_external',
            $id,
            'IcuBookingExternal',
        );
    }

    /** ICU menolak booking external */
    public function tolakBookingIcu(int $id, string $namaPasien, string $alasan): void
    {
        $this->log(
            'Tolak Data',
            "Menolak booking ICU {$namaPasien}: {$alasan}",
            'booking_external',
            $id,
            'IcuBookingExternal',
        );
    }

    /** ICU memverifikasi bed untuk SPRI internal */
    public function verifikasibed(int $id, string $namaPasien, string $namaBed): void
    {
        $this->log(
            'Verifikasi Bed',
            "Verifikasi bed {$namaBed} untuk SPRI {$namaPasien}",
            'spri_internal',
            $id,
            'IcuSpriInternal',
        );
    }

    /** ICU menolak SPRI internal */
    public function tolakSpriIcu(int $id, string $namaPasien, string $alasan): void
    {
        $this->log(
            'Tolak Data',
            "Menolak BU SPRI {$namaPasien} oleh ICU: {$alasan}",
            'spri_internal',
            $id,
            'IcuSpriInternal',
        );
    }

    // ── Petugas Ruang ──────────────────────────────────────────────────────

    /** Petugas ruang / IGD membuat BU SPRI internal */
    public function buatSpri(int $id, string $namaPasien): void
    {
        $this->log(
            'Buat Data',
            "Membuat BU SPRI (Booking ICU) untuk {$namaPasien}",
            'spri_internal',
            $id,
            'IcuSpriInternal',
        );
    }
}
