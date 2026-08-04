<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IcuBookingExternal extends Model
{
    protected $table = 'IB_icu_booking_external';

    protected $fillable = [
        'nama_pasien', 'jenis_kelamin', 'no_identitas', 'asal_rujukan', 'no_telp_keluarga',
        'diagnosa', 'diagnosa_icd', 'rencana_tindakan', 'kebutuhan_bed',
        'jaminan', 'catatan_jaminan', 'keterangan',
        'No_MR', 'No_Reg',
        'allocated_bed_id', 'nama_bed',
        'status', 'alasan_tolak',
        'alasan_batal', 'dibatalkan_by', 'dibatalkan_at',
        'waiting_alasan', 'waiting_estimasi', 'waiting_by',
        'pindah_alasan', 'pindah_bed_lama', 'pindah_by', 'pindah_at',
        'created_by', 'confirmed_by', 'confirmed_at',
        'verified_by', 'verified_at',
        'masuk_at', 'masuk_by',
        'keluar_at', 'keluar_by',
    ];

    protected $casts = [
        'waiting_estimasi' => 'datetime',
        'confirmed_at'     => 'datetime',
        'verified_at'      => 'datetime',
        'pindah_at'        => 'datetime',
        'masuk_at'         => 'datetime',
        'keluar_at'        => 'datetime',
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
            'pending_icu'     => 'Menunggu ICU',
            'waiting_list'    => 'Waiting List',
            'bed_confirmed'   => 'Bed Dikonfirmasi',
            'admisi_verified' => 'Terverifikasi',
            'masuk_icu'       => 'Sudah Masuk ICU',
            'selesai'         => 'Keluar ICU',
            'ditolak'         => 'Ditolak',
            'dibatalkan'      => 'Dibatalkan',
            default           => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending_icu'     => 'amber',
            'waiting_list'    => 'orange',
            'bed_confirmed'   => 'blue',
            'admisi_verified' => 'teal',
            'masuk_icu'       => 'green',
            'selesai'         => 'slate',
            'ditolak'         => 'red',
            'dibatalkan'      => 'gray',
            default           => 'gray',
        };
    }
}
