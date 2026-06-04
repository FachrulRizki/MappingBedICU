<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pendaftaran extends Model
{
    protected $table      = 'pendaftaran';
    protected $primaryKey = 'No_Reg';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'No_Reg',
        'No_MR',
        'Kode_Masuk',
        'Kode_Asal',
        'referensi',
        'medis',
        'PermintaanDPJP',
        'Kode_Dokter',
        'NameUser',
    ];

    /** Kunjungan ini milik satu pasien */
    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }

    /** Satu kunjungan bisa punya satu atau beberapa SPRI */
    public function spris()
    {
        return $this->hasMany(Spri::class, 'No_Reg', 'No_Reg');
    }

    /** SPRI terbaru/aktif untuk kunjungan ini */
    public function spriAktif()
    {
        return $this->hasOne(Spri::class, 'No_Reg', 'No_Reg')->latestOfMany();
    }

    /** Data ICU Admision untuk kunjungan ini */
    public function icuAdmision()
    {
        return $this->hasOne(IcuAdmision::class, 'No_Reg', 'No_Reg');
    }
}
