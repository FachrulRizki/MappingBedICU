<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

class Pendaftaran extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'PENDAFTARAN';
    protected string $localTable = 'pendaftaran';

    protected $primaryKey = 'No_Reg';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

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

    public function pasien()
    {
        return $this->belongsTo(RegistrasiPasien::class, 'No_MR', 'No_MR');
    }
}
