<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    // Relasi
    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    // public function bed()
    // {
    //     return $this->belongsTo(StatusKamar::class, 'allocated_bed_id', 'Kode_Ruang');
    // }

    public function getBedAttribute()
    {
        if (!$this->allocated_bed_id) {
            return null;
        }

        return \App\Models\StatusKamar::on('sqlsrv_rsus')
            ->with('ruang.kelas')
            ->where('Kode_Ruang', $this->allocated_bed_id)
            ->first();
    }

    // Helper
    public function getNamaPasienAttribute(): string
    {
        return $this->pasien?->Nama_Pasien ?? $this->nama_pasien_ext ?? '-';
    }

    public function isExternal(): bool
    {
        return $this->jenis_pasien === 'external';
    }

    public function isWaitingIcu(): bool
    {
        return in_array($this->status, ['waiting_icu', 'ext_waiting']);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'daftar'       => 'secondary',
            'igd_periksa'  => 'warning',
            'spri_dibuat'  => 'info',
            'waiting_icu'  => 'danger',
            'ext_request'  => 'warning',
            'ext_waiting'  => 'danger',
            'booking_icu'  => 'primary',
            'di_icu'       => 'dark',
            'pulang'       => 'success',
            default        => 'light',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'daftar'       => 'Terdaftar',
            'igd_periksa'  => 'Di IGD',
            'spri_dibuat'  => 'SPRI Dibuat',
            'waiting_icu'  => 'Menunggu ICU',
            'ext_request'  => 'Request Masuk',
            'ext_waiting'  => 'Menunggu ICU',
            'booking_icu'  => 'Booking Bed',
            'di_icu'       => 'Di ICU',
            'pulang'       => 'Pulang',
            default        => $this->status,
        };
    }
}
