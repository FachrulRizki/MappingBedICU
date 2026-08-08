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

        // Cache 30 detik sebagai array plain (bukan objek Eloquent) agar tidak ada masalah deserialize
        $cached = \Illuminate\Support\Facades\Cache::remember('bed_icu_dengan_status', 30, function () use ($rm, $mk, $sk) {
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
                    ->get()
                    ->map(fn ($r) => (array) $r) // simpan sebagai array plain, bukan stdClass
                    ->toArray();
            } catch (\Exception $e) {
                Log::error('[MRuangMaster::bedIcuDenganStatus] ' . $e->getMessage());
                return [];
            }
        });

        // Kembalikan sebagai Collection of stdClass agar kode konsumen tetap kompatibel
        return collect($cached)->map(fn ($r) => (object) $r);
    }

    public static function bedKosong(): \Illuminate\Support\Collection
    {
        // Simpan sebagai plain array — Collection tidak aman di-serialize ke cache
        $data = \Illuminate\Support\Facades\Cache::remember('bed_kosong_list', 15, function () {
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
                ->values()
                ->toArray(); // simpan plain array
        });

        return collect(is_array($data) ? $data : []);
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

    /**
     * Semua jenis ICU dari tabel (tanpa filter ketersediaan bed).
     * Dipakai untuk dropdown pilihan jenis ICU saat booking.
     */
    public static function jenisIcuSemua(): \Illuminate\Support\Collection
    {
        return static::bedIcuDenganStatus()
            ->whereNotNull('Nama_Kelas')
            ->unique('kelas_master')
            ->map(fn($row) => [
                'kode' => $row->kelas_master ?? $row->Kode_Kelas,
                'nama' => $row->Nama_Kelas,
            ])
            ->sortBy('nama')
            ->values();
    }

    public static function bedTersediaUntukKonfirmasi(): \Illuminate\Support\Collection
    {
        // Simpan sebagai plain array — Collection/Eloquent model tidak aman di-serialize ke cache
        $data = \Illuminate\Support\Facades\Cache::remember('bed_tersedia_konfirmasi', 15, function () {
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
                    $kode        = $row->Kode_RuangM;
                    $rsusStatus  = strtoupper($row->Status);
                    $pemegang    = $pemegangExt->get($kode) ?? $pemegangInt->get($kode);

                    $effectiveStatus    = $pemegang ? 'BOOKING' : $rsusStatus;
                    $namaPasienPemegang = null;
                    $pemegangId         = null;
                    $pemegangSumber     = null;

                    if ($pemegang) {
                        $namaPasienPemegang = $pemegang->nama_pasien ?? $pemegang->No_MR ?? null;
                        $pemegangId         = $pemegang->id;
                        $pemegangSumber     = isset($pemegang->nama_pasien) ? 'external' : 'internal';
                    }

                    return [
                        'Kode_Ruang'      => $kode,
                        'nama_ruang'      => $row->Nama_RuangM,
                        'kode_kelas'      => $row->kelas_master ?? $row->Kode_Kelas,
                        'nama_kelas'      => $row->Nama_Kelas,
                        'status_bed'      => $effectiveStatus,
                        'pasien_pemegang' => $namaPasienPemegang,
                        'pemegang_id'     => $pemegangId,
                        'pemegang_sumber' => $pemegangSumber,
                    ];
                })
                ->values()
                ->toArray(); // simpan plain array
        });

        return collect(is_array($data) ? $data : []);
    }
}
