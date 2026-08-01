<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IcuSpriInternal extends Model
{
    protected $table = 'IB_icu_spri_internal';

    protected $fillable = [
        'No_MR', 'No_Reg',
        'Diagnosis', 'Diagnosis_ICD', 'IndikasiRI', 'kebutuhan_bed',
        'asal_ruang', 'Dokter', 'spesialis', 'Keterangan',
        'NameUser',
        'catatan_admisi',
        'allocated_bed_id', 'nama_bed',
        'status', 'alasan_tolak',
        'alasan_batal', 'dibatalkan_by', 'dibatalkan_at',
        'waiting_alasan', 'waiting_estimasi', 'waiting_by',
        'pindah_alasan', 'pindah_bed_lama', 'pindah_by', 'pindah_at',
        'approved_by', 'approved_at',
        'verified_by', 'verified_at',
        'masuk_at', 'masuk_by',
    ];

    protected $casts = [
        'waiting_estimasi' => 'datetime',
        'approved_at'      => 'datetime',
        'verified_at'      => 'datetime',
        'pindah_at'        => 'datetime',
        'masuk_at'         => 'datetime',
        'dibatalkan_at'    => 'datetime',
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
            'masuk_icu'      => 'Sudah Masuk ICU',
            'selesai'        => 'Keluar ICU',
            'ditolak'        => 'Ditolak',
            'dibatalkan'     => 'Dibatalkan',
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
            'masuk_icu'      => 'green',
            'selesai'        => 'slate',
            'ditolak'        => 'red',
            'dibatalkan'     => 'gray',
            default          => 'gray',
        };
    }
}
