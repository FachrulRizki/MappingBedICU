<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Spri extends Model
{
    protected $table = 'spri';

    protected $fillable = [
        'No_Reg',
        'Diagnosis',
        'IndikasiRI',
        'spesialis',
        'Dokter',
        'NameUser',
        'Perawatan',
        'Keterangan',
        'Status',
    ];

    /** SPRI ini milik kunjungan mana */
    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    /** Helper: apakah SPRI sudah disetujui? */
    public function isApproved(): bool
    {
        return $this->Status === 'approved';
    }
}
