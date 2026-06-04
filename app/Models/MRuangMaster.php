<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master ruangan/bed individual.
 * Contoh: HC301 → ICU → ICUNV → "HIGH CARE UNIT 301"
 */
class MRuangMaster extends Model
{
    protected $table      = 'm_ruang_master';
    protected $primaryKey = 'Kode_RuangM';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'Kode_RuangM',
        'Kode_Bangsal',
        'Kode_Kelas',
        'Nama_RuangM',
        'Status',
        'KelasBPJS',
        'KetBed',
    ];

    /** Relasi ke master kelas untuk dapat Nama_Kelas */
    public function kelas()
    {
        return $this->belongsTo(MKelas::class, 'Kode_Kelas', 'Kode_Kelas');
    }

    /** Relasi ke status occupancy */
    public function statusKamar()
    {
        return $this->hasOne(StatusKamar::class, 'Kode_Ruang', 'Kode_RuangM');
    }
}
