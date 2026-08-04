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
                ->table("{$sk} as sk")
                ->join("{$rm} as rm", 'sk.Kode_Ruang', '=', 'rm.Kode_RuangM')
                ->join("{$mk} as mk", 'rm.Kode_Kelas', '=', 'mk.Kode_Kelas')
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
        // Ambil semua kode bed yang sudah di-booking di tabel lokal (double-check)
        $bedSudahDialokasi = collect();
        try {
            $extBeds = \App\Models\IcuBookingExternal::whereIn('status', ['bed_confirmed', 'admisi_verified'])
                ->whereNotNull('allocated_bed_id')
                ->pluck('allocated_bed_id');
            $intBeds = \App\Models\IcuSpriInternal::where('status', 'bed_verified')
                ->whereNotNull('allocated_bed_id')
                ->pluck('allocated_bed_id');
            $bedSudahDialokasi = $extBeds->merge($intBeds)->filter()->unique();
        } catch (\Exception $e) {
            Log::warning('[MRuangMaster::bedKosong] Gagal cek alokasi lokal: ' . $e->getMessage());
        }

        return static::bedIcuDenganStatus()
            ->where('Status', 'KOSONG')
            ->filter(fn($row) => ! $bedSudahDialokasi->contains($row->Kode_RuangM))
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
        $semua = static::bedIcuDenganStatus();

        // Kumpulkan kode kelas yang punya bed KOSONG atau BOOKING (untuk konfirmasi ICU)
        $kelasAdaBed = $semua
            ->whereIn('Status', ['KOSONG', 'BOOKING'])
            ->pluck('kelas_master')
            ->merge($semua->whereIn('Status', ['KOSONG', 'BOOKING'])->pluck('Kode_Kelas'))
            ->filter()
            ->unique()
            ->values();

        return $semua
            ->whereNotNull('Nama_Kelas')
            ->unique('kelas_master')
            ->filter(fn($row) => $kelasAdaBed->contains($row->kelas_master ?? $row->Kode_Kelas))
            ->map(fn($row) => [
                'kode' => $row->kelas_master ?? $row->Kode_Kelas,
                'nama' => $row->Nama_Kelas,
            ])
            ->values();
    }

    public static function bedTersediaUntukKonfirmasi(): \Illuminate\Support\Collection
    {
        // Kumpulkan semua bed yang sudah di-alokasi di tabel lokal (app booking, bukan RSUS)
        $pemegangExt = \App\Models\IcuBookingExternal::whereIn('status', ['bed_confirmed', 'admisi_verified'])
            ->whereNotNull('allocated_bed_id')
            ->get(['allocated_bed_id', 'nama_pasien', 'id'])
            ->keyBy('allocated_bed_id');

        $pemegangInt = \App\Models\IcuSpriInternal::where('status', 'bed_verified')
            ->whereNotNull('allocated_bed_id')
            ->get(['allocated_bed_id', 'No_MR', 'id'])
            ->keyBy('allocated_bed_id');

        return static::bedIcuDenganStatus()
            ->whereIn('Status', ['KOSONG', 'BOOKING'])
            ->map(function ($row) use ($pemegangExt, $pemegangInt) {
                $kode      = $row->Kode_RuangM;
                $rsusStatus = strtoupper($row->Status);

                // Cek apakah ada pasien di tabel lokal yang memegang bed ini
                $pemegang = $pemegangExt->get($kode) ?? $pemegangInt->get($kode);

                // Jika bed sudah di-alokasi lokal → tampilkan sebagai "BOOKING" (bisa dipreempt)
                // meski RSUS masih bilang KOSONG — ini cegah duplikasi
                $effectiveStatus = $pemegang ? 'BOOKING' : $rsusStatus;

                $namaPasienPemegang = null;
                $pemegangId         = null;
                $pemegangSumber     = null;
                if ($pemegang) {
                    $namaPasienPemegang = $pemegang->nama_pasien ?? $pemegang->No_MR ?? null;
                    $pemegangId         = $pemegang->id;
                    $pemegangSumber     = isset($pemegang->nama_pasien) ? 'external' : 'internal';
                }

                return [
                    'Kode_Ruang'         => $kode,
                    'nama_ruang'         => $row->Nama_RuangM,
                    'kode_kelas'         => $row->kelas_master ?? $row->Kode_Kelas,
                    'nama_kelas'         => $row->Nama_Kelas,
                    'status_bed'         => $effectiveStatus,  // 'KOSONG' | 'BOOKING'
                    'pasien_pemegang'    => $namaPasienPemegang,
                    'pemegang_id'        => $pemegangId,
                    'pemegang_sumber'    => $pemegangSumber,
                ];
            })
            ->values();
    }
}
