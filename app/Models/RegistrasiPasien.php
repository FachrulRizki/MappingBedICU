<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

class RegistrasiPasien extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'REGISTER_PASIEN';
    protected string $localTable = 'registrasi_pasien';

    protected $primaryKey = 'No_MR';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = [
        'No_MR',
        'Nama_Pasien',
        'jenis_kelamin',
        'tgl_regist',
        'No_Identitas',
        'KartuBPJS',
        'NameUser',
    ];

    protected $casts = [
        'tgl_regist' => 'datetime',
    ];

    public function pendaftarans()
    {
        return $this->hasMany(Pendaftaran::class, 'No_MR', 'No_MR');
    }

    public function icuAdmisions()
    {
        return $this->hasMany(IcuAdmision::class, 'No_MR', 'No_MR');
    }
}
