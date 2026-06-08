<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Surat Permintaan Rawat ICU — jalur INTERNAL.
 * Pasien sudah ada di RS, indikasi pindah ke ICU dari ruang lain.
 */
class IcuSpriInternal extends Model
{
    protected $table = 'icu_spri_internal';

    protected $fillable = [
        'No_MR',
        'No_Reg',
        'Diagnosis',
        'IndikasiRI',
        'kebutuhan_bed',
        'asal_ruang',
        'Dokter',
        'spesialis',
        'Keterangan',
        'NameUser',
        'allocated_bed_id',
        'status',
        'alasan_tolak',
        'approved_by',
        'booked_by',
        'verified_by',
    ];

    // ── Relasi ────────────────────────────────────────────────────────────

    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    public function bed()
    {
        return $this->belongsTo(StatusKamar::class, 'allocated_bed_id', 'Kode_Ruang');
    }

    // ── Status helpers ────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return match ($this->status) {
            'spri_dibuat'     => 'Surat Dibuat',
            'pending_admisi'  => 'Menunggu Admisi',
            'admisi_approved' => 'Disetujui Admisi',
            'pending_icu'     => 'Menunggu Booking ICU',
            'bed_booked'      => 'Bed Dipesan ICU',
            'admisi_verified' => 'Diverifikasi Admisi',
            'di_icu'          => 'Di ICU',
            'ditolak'         => 'Ditolak',
            default           => $this->status,
        };
    }
}
