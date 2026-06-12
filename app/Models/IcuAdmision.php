<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IcuAdmision — alur LEGACY (jalur lama).
 * Jalur baru: IcuSpriInternal (internal) & IcuBookingExternal (external).
 */
class IcuAdmision extends Model
{
    protected $table = 'icu_admision';

    protected $fillable = [
        'No_Reg',
        'No_MR',
        'nama_pasien_ext',
        'asal_rs',
        'jaminan',
        'catatan_jaminan',
        'jenis_pasien',
        'status',
        'required_bed_type',
        'catatan_admisi',
        'allocated_bed_id',
        'match_status',
    ];

    // ── Relasi ────────────────────────────────────────────────────────────

    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function getNamaPasienAttribute(): string
    {
        return $this->pasien?->Nama_Pasien ?? $this->nama_pasien_ext ?? '-';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'daftar'      => 'Terdaftar',
            'igd_periksa' => 'Di IGD',
            'spri_dibuat' => 'SPRI Dibuat',
            'waiting_icu' => 'Menunggu ICU',
            'booking_icu' => 'Booking Bed',
            'di_icu'      => 'Di ICU',
            'pulang'      => 'Pulang',
            default       => $this->status,
        };
    }
}
