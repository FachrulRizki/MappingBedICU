<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master jenis/kelas bed ICU.
 * Contoh data: ICUNV, HCU, ICU, CVCU, BPICU
 */
class MKelas extends Model
{
    protected $table      = 'm_kelas';
    protected $primaryKey = 'Kode_Kelas';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'Kode_Kelas',
        'Nama_Kelas',
        'Kelas',
    ];

    /** Semua ruangan yang masuk kelas ini */
    public function ruangMasters()
    {
        return $this->hasMany(MRuangMaster::class, 'Kode_Kelas', 'Kode_Kelas');
    }
}
