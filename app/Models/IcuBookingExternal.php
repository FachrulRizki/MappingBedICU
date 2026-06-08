<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Booking ICU jalur EXTERNAL.
 * Pasien dari luar RS — belum punya No_MR saat booking dibuat.
 */
class IcuBookingExternal extends Model
{
    protected $table = 'icu_booking_external';

    protected $fillable = [
        'nama_pasien',
        'jenis_kelamin',
        'no_identitas',
        'asal_rujukan',
        'no_telp_keluarga',
        'diagnosa',
        'rencana_tindakan',
        'kebutuhan_bed',
        'keterangan',
        'No_MR',
        'No_Reg',
        'allocated_bed_id',
        'status',
        'alasan_tolak',
        'created_by',
        'confirmed_by',
        'validated_by',
    ];

    // ── Relasi ke data RS (setelah pasien tiba) ───────────────────────────

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
            'booking_request'  => 'Booking Masuk',
            'pending_icu'      => 'Menunggu Cek ICU',
            'bed_confirmed'    => 'Bed Dikonfirmasi ICU',
            'admisi_validated' => 'Divalidasi Admisi',
            'pasien_tiba'      => 'Pasien Tiba di IGD',
            'bed_verified'     => 'Bed Diverifikasi',
            'di_icu'           => 'Di ICU',
            'ditolak'          => 'Ditolak',
            default            => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'booking_request'  => 'sky',
            'pending_icu'      => 'amber',
            'bed_confirmed'    => 'teal',
            'admisi_validated' => 'teal',
            'pasien_tiba'      => 'amber',
            'bed_verified'     => 'teal',
            'di_icu'           => 'green',
            'ditolak'          => 'red',
            default            => 'gray',
        };
    }

    /** Apakah bisa di-link ke No_MR (pasien sudah tiba) */
    public function bisaLinkNoMr(): bool
    {
        return in_array($this->status, ['admisi_validated']) && is_null($this->No_MR);
    }
}
