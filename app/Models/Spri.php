<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SPRI — alur LEGACY, terikat dengan IcuAdmision.
 * Status: 'draft' | 'approved' | 'rejected'
 */
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

    public function pendaftaran()
    {
        return $this->belongsTo(Pendaftaran::class, 'No_Reg', 'No_Reg');
    }

    public function isApproved(): bool
    {
        return $this->Status === 'approved';
    }
}
