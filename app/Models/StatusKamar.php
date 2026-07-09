<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesRsusConnection;

class StatusKamar extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'STATUS_KAMAR';
    protected string $localTable = 'status_kamar';

    protected $primaryKey = 'Kode_Ruang';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = [
        'Kode_Ruang',
        'Kode_Bangsal',
        'Status',
        'Keterangan',
        'NamaUser',
        'KelasBPJS',
        'No_MR',
        'Oksigen',
    ];

    public function ruang()
    {
        return $this->belongsTo(MRuangMaster::class, 'Kode_Ruang', 'Kode_RuangM');
    }

    public function getNamaKelasAttribute(): string
    {
        return $this->ruang?->kelas?->Nama_Kelas ?? $this->KelasBPJS ?? '-';
    }

    public function getKodeKelasAttribute(): string
    {
        return $this->ruang?->kelas?->Kode_Kelas ?? $this->KelasBPJS ?? '-';
    }

    public function isKosong(): bool
    {
        return strtoupper($this->Status) === 'KOSONG';
    }

    public function statusLabel(): string
    {
        return match (strtoupper($this->Status)) {
            'KOSONG'  => 'Tersedia',
            'BOOKING' => 'Booking',
            'ISI'     => 'Terisi',
            default   => $this->Status,
        };
    }
}
