<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrasiPasien extends Model
{
    protected $table      = 'registrasi_pasien';
    protected $primaryKey = 'No_MR';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'No_MR',
        'Nama_Pasien',
        'tgl_regist',
        'No_Identitas',
        'KartuBPJS',
        'NameUser',
    ];

    protected $casts = [
        'tgl_regist' => 'datetime',
    ];

    /** Satu pasien bisa punya banyak kunjungan (pendaftaran) */
    public function pendaftarans()
    {
        return $this->hasMany(Pendaftaran::class, 'No_MR', 'No_MR');
    }

    /** ICU admissions yang terkait dengan pasien ini */
    public function icuAdmisions()
    {
        return $this->hasMany(IcuAdmision::class, 'No_MR', 'No_MR');
    }
}
