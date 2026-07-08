<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Concerns\UsesRsusConnection;

class MRuangMaster extends Model
{
    use UsesRsusConnection;

    protected string $rsusTable  = 'M_RUANG_MASTER';
    protected string $localTable = 'm_ruang_master';

    protected $primaryKey = 'Kode_RuangM';
    public    $incrementing = false;
    protected $keyType     = 'string';
    public    $timestamps  = false;

    protected $fillable = [
        'Kode_RuangM',
        'Kode_Bangsal',
        'Kode_Kelas',
        'Nama_RuangM',
        'Status',
        'KelasBPJS',
        'KetBed',
    ];

    public function kelas()
    {
        return $this->belongsTo(MKelas::class, 'Kode_Kelas', 'Kode_Kelas');
    }

    public function statusKamar()
    {
        return $this->hasOne(StatusKamar::class, 'Kode_Ruang', 'Kode_RuangM');
    }

    public static function bedIcuDenganStatus(): \Illuminate\Support\Collection
    {
        $instance = new static();
        $rm       = $instance->getTable();
        $mk       = (new MKelas())->getTable();
        $sk       = (new StatusKamar())->getTable();

        try {
            return DB::connection('sqlsrv_rsus')
                ->table("{$rm} as rm")
                ->leftJoin("{$mk} as mk", 'rm.Kode_Kelas', '=', 'mk.Kode_Kelas')
                ->leftJoin("{$sk} as sk", 'rm.Kode_RuangM', '=', 'sk.Kode_Ruang')
                ->where('rm.Kode_Bangsal', 'ICU')
                ->select([
                    'rm.Kode_RuangM',
                    'rm.Nama_RuangM',
                    'rm.Kode_Kelas',
                    'mk.Kode_Kelas as kelas_master',
                    'mk.Nama_Kelas',
                    'sk.Status',
                    'sk.No_MR',
                ])
                ->orderBy('mk.Nama_Kelas')
                ->orderBy('rm.Nama_RuangM')
                ->get();
        } catch (\Exception $e) {
            Log::error('[MRuangMaster::bedIcuDenganStatus] ' . $e->getMessage());
            return collect();
        }
    }

    public static function bedKosong(): \Illuminate\Support\Collection
    {
        return static::bedIcuDenganStatus()
            ->where('Status', 'KOSONG')
            ->map(fn($row) => [
                'Kode_Ruang' => $row->Kode_RuangM,
                'nama_ruang' => $row->Nama_RuangM,
                'kode_kelas' => $row->kelas_master ?? $row->Kode_Kelas,
                'nama_kelas' => $row->Nama_Kelas,
            ])
            ->values();
    }

    public static function jenisIcuTersedia(): \Illuminate\Support\Collection
    {
        return static::bedIcuDenganStatus()
            ->whereNotNull('Nama_Kelas')
            ->unique('kelas_master')
            ->map(fn($row) => [
                'kode' => $row->kelas_master ?? $row->Kode_Kelas,
                'nama' => $row->Nama_Kelas,
            ])
            ->values();
    }
}
