<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BU (Booking ICU) jalur INTERNAL.
 *
 * Alur: pending_admisi → pending_icu → bed_verified | ditolak
 *                                   ↘ waiting_list → bed_verified (saat bed tersedia)
 */
class IcuSpriInternal extends Model
{
    protected $table = 'icu_spri_internal';

    protected $fillable = [
        'No_MR', 'No_Reg',
        'Diagnosis', 'IndikasiRI', 'kebutuhan_bed',
        'asal_ruang', 'Dokter', 'spesialis', 'Keterangan',
        'NameUser',
        'catatan_admisi',
        'allocated_bed_id', 'nama_bed',
        'status', 'alasan_tolak',
        'waiting_alasan', 'waiting_estimasi', 'waiting_by',
        'approved_by', 'approved_at',
        'verified_by', 'verified_at',
    ];

    protected $casts = [
        'waiting_estimasi' => 'datetime',
        'approved_at'      => 'datetime',
        'verified_at'      => 'datetime',
    ];

    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting_list';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_admisi' => 'Menunggu Admisi',
            'pending_icu'    => 'Menunggu ICU',
            'waiting_list'   => 'Waiting List',
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
            'waiting_list'   => 'orange',
            'bed_verified'   => 'teal',
            'ditolak'        => 'red',
            default          => 'gray',
        };
    }
}
