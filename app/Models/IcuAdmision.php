<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IcuAdmision extends Model
{
    protected $table = 'icu_admision';

    protected $fillable = [
        'No_Reg',
        'No_MR',
        'status',
        'required_bed_type',
        'allocated_bed_id',
        'match_status',
    ];

    // ─── Relasi ──────────────────────────────────────────────

    /** Data kunjungan (pendaftaran) */
    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    /** Data pasien (registrasi) */
    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    /** Bed yang dialokasikan — relasi ke STATUS_KAMAR.Kode_Ruang */
    public function bed()
    {
        return $this->belongsTo(StatusKamar::class, 'allocated_bed_id', 'Kode_Ruang');
    }

    // ─── Helper ──────────────────────────────────────────────

    /** Nama lengkap pasien via relasi */
    public function getNamaPasienAttribute(): string
    {
        return $this->pasien?->Nama_Pasien ?? '-';
    }

    /** Label badge warna berdasarkan status */
    public function statusBadge(): string
    {
        return match ($this->status) {
            'daftar'      => 'secondary',
            'igd_periksa' => 'warning',
            'spri_dibuat' => 'info',
            'waiting_icu' => 'danger',
            'booking_icu' => 'primary',
            'di_icu'      => 'dark',
            'pulang'      => 'success',
            default       => 'light',
        };
    }

    /** Label status yang mudah dibaca */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'daftar'      => 'Terdaftar',
            'igd_periksa' => 'Di IGD',
            'spri_dibuat' => 'SPRI Dibuat',
            'waiting_icu' => 'Menunggu Kamar',
            'booking_icu' => 'Booking Kamar',
            'di_icu'      => 'Di ICU',
            'pulang'      => 'Pulang',
            default       => $this->status,
        };
    }
}
