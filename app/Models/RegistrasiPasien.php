<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

/**
 * Model pasien.
 *
 * SQL Server: DB_RSUS.dbo.REGISTER_PASIEN
 * MySQL lokal: registrasi_pasien (dari migration)
 *
 * Kolom utama disesuaikan dengan kolom di DB RS.
 * Saat SQL Server aktif, pastikan nama kolom cocok dengan
 * hasil query: SELECT TOP 1 * FROM REGISTER_PASIEN
 */
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
