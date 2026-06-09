<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

class MKelas extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'M_KELAS';
    protected string $localTable = 'm_kelas';

    protected $primaryKey = 'Kode_Kelas';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = [
        'Kode_Kelas',
        'Nama_Kelas',
        'Kelas',
    ];

    public function ruangMasters()
    {
        return $this->hasMany(MRuangMaster::class, 'Kode_Kelas', 'Kode_Kelas');
    }
}
