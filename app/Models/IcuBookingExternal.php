<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Booking ICU jalur EXTERNAL.
 *
 * Alur: pending_icu → bed_confirmed → admisi_verified | ditolak
 */
class IcuBookingExternal extends Model
{
    protected $table = 'icu_booking_external';

    protected $fillable = [
        'nama_pasien', 'jenis_kelamin', 'no_identitas', 'asal_rujukan', 'no_telp_keluarga',
        'diagnosa', 'rencana_tindakan', 'kebutuhan_bed',
        'jaminan', 'catatan_jaminan', 'keterangan',
        'No_MR', 'No_Reg',
        'allocated_bed_id', 'nama_bed',
        'status', 'alasan_tolak',
        'created_by', 'confirmed_by', 'verified_by',
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
            'pending_icu'     => 'Menunggu ICU',
            'bed_confirmed'   => 'Bed Dikonfirmasi',
            'admisi_verified' => 'Terverifikasi',
            'ditolak'         => 'Ditolak',
            default           => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending_icu'     => 'amber',
            'bed_confirmed'   => 'blue',
            'admisi_verified' => 'teal',
            'ditolak'         => 'red',
            default           => 'gray',
        };
    }
}
