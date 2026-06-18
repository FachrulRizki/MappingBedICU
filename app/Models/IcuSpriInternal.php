<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SPRI jalur INTERNAL.
 *
 * Alur: pending_admisi → pending_icu → bed_verified | ditolak
 */
class IcuSpriInternal extends Model
{
    protected $table = 'icu_spri_internal';

    protected $fillable = [
        'No_MR', 'No_Reg',
        'Diagnosis', 'IndikasiRI', 'kebutuhan_bed',
        'asal_ruang', 'Dokter', 'spesialis', 'Keterangan',
        'NameUser', 'NamaUser',   // support kedua nama kolom
        'catatan_admisi',
        'allocated_bed_id', 'nama_bed',
        'status', 'alasan_tolak',
        'approved_by', 'verified_by',
    ];

    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_admisi' => 'Menunggu Admisi',
            'pending_icu'    => 'Menunggu ICU',
            'bed_verified'   => 'Bed Terverifikasi',
            'ditolak'        => 'Ditolak',
            default          => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending_admisi' => 'yellow',
            'pending_icu'    => 'amber',
            'bed_verified'   => 'teal',
            'ditolak'        => 'red',
            default          => 'gray',
        };
    }
}
