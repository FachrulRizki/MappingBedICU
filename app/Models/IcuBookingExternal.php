<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Booking ICU jalur EXTERNAL.
 * Pasien dari luar RS — belum punya No_MR saat booking dibuat.
 *
 * Alur: pending_icu → bed_confirmed → di_icu → pulang
 * Admisi isi keterangan + jaminan, ICU yang tentukan bed.
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
        'jaminan',           // BPJS | Umum | Asuransi | Lainnya
        'catatan_jaminan',   // detail jaminan dari admisi
        'keterangan',
        'No_MR',
        'No_Reg',
        'allocated_bed_id',
        'status',
        'alasan_tolak',
        'created_by',
        'confirmed_by',
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
            'pending_icu'   => 'Menunggu ICU',
            'bed_confirmed' => 'Bed Dikonfirmasi — Siap Antar',
            'di_icu'        => 'Di ICU',
            'ditolak'       => 'Ditolak',
            'pulang'        => 'Pulang',
            default         => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending_icu'   => 'amber',
            'bed_confirmed' => 'teal',
            'di_icu'        => 'green',
            'ditolak'       => 'red',
            'pulang'        => 'gray',
            default         => 'gray',
        };
    }
}
