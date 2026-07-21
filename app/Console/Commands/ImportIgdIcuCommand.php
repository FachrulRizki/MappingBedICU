<?php

namespace App\Console\Commands;

use App\Models\IcuSpriInternal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportIgdIcuCommand extends Command
{
    protected $signature   = 'icu:import-igd {--dry-run : Simulasi tanpa simpan ke DB}';
    protected $description = 'Import pasien IGD dari ASESMEN_SURAT_PERMINTAAN_RI SIMRS yang butuh ICU';

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry-run');

        try {
            $rows = $this->fetchFromSimrs();
        } catch (\Exception $e) {
            $this->error('Gagal query SIMRS: ' . $e->getMessage());
            Log::error('[ImportIgdIcu] Gagal query SIMRS: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($rows->isEmpty()) {
            $this->info('Tidak ada data baru dari SIMRS.');
            return self::SUCCESS;
        }

        // Cek No_Reg yang sudah pernah diimport (via simrs_no_reg)
        $existing = IcuSpriInternal::whereIn('simrs_no_reg', $rows->pluck('No_Reg')->filter()->unique())
            ->pluck('simrs_no_reg')
            ->flip(); // ['NO_REG' => index] — untuk O(1) lookup

        $imported = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            $noReg = trim($row->No_Reg ?? '');

            if (! $noReg || isset($existing[$noReg])) {
                $skipped++;
                continue;
            }

            if ($isDry) {
                $this->line("  [DRY-RUN] Akan import No_Reg={$noReg} | {$row->Nama_Pasien} | {$row->Diagnosis}");
                $imported++;
                continue;
            }

            try {
                IcuSpriInternal::create([
                    'No_MR'             => trim($row->No_MR         ?? ''),
                    'No_Reg'            => $noReg,
                    'Diagnosis'         => trim($row->Diagnosis      ?? '-'),
                    'IndikasiRI'        => trim($row->IndikasiRI     ?? 'Butuh ICU (dari IGD)'),
                    'asal_ruang'        => trim($row->asal_ruang     ?? 'IGD'),
                    'Dokter'            => trim($row->Nama_Dokter    ?? ''),
                    'Keterangan'        => trim($row->Keterangan     ?? ''),
                    'NameUser'          => 'SIMRS/IGD',
                    'status'            => 'pending_admisi',
                    // penanda import SIMRS
                    'simrs_no_reg'      => $noReg,
                    'simrs_source'      => 'igd_asesmen',
                    'simrs_imported_at' => now(),
                ]);

                $imported++;
                Log::info("[ImportIgdIcu] Import No_Reg={$noReg} | {$row->Nama_Pasien}");

            } catch (\Exception $e) {
                Log::warning("[ImportIgdIcu] Gagal simpan No_Reg={$noReg}: " . $e->getMessage());
            }
        }

        $label = $isDry ? '[DRY-RUN] ' : '';
        $this->info("{$label}Selesai — {$imported} diimport, {$skipped} dilewati (sudah ada).");
        return self::SUCCESS;
    }

    private function fetchFromSimrs(): \Illuminate\Support\Collection
    {
        // No_Reg yang sudah ada — dipakai untuk WHERE NOT IN di query
        $existingNoRegs = IcuSpriInternal::whereNotNull('simrs_no_reg')
            ->pluck('simrs_no_reg')
            ->toArray();

        $q = DB::connection('sqlsrv_rsus')
            ->table('ASESMEN_SURAT_PERMINTAAN_RI as a')
            ->join('PENDAFTARAN as p',        'a.No_Reg', '=', 'p.No_Reg')
            ->join('REGISTER_PASIEN as rp',   'p.No_MR',  '=', 'rp.No_MR')
            ->leftJoin('DOKTER as d',          'p.Kode_Dokter', '=', 'd.Kode_Dokter')
            ->leftJoin('M_CARABAYAR as cb',    'p.Kode_Bayar',  '=', 'cb.Kode_Bayar')
            ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang',  '=', 'rm.Kode_RuangM')
            // Hanya pasien IGD (Kode_Masuk = '1') dan Perawatan = 'ICU'
            ->where('a.Perawatan', 'ICU')
            ->where('p.Kode_Masuk', '1')
            // Hanya record aktif / belum pulang
            ->where('p.Status', '1')
            ->where('p.Status_Pulang', 'Belum')
            ->select([
                'a.No_Reg',
                'p.No_MR',
                'rp.Nama_Pasien',
                DB::raw("ISNULL(a.Diagnosis, '') as Diagnosis"),
                DB::raw("ISNULL(a.IndikasiRI, '') as IndikasiRI"),
                DB::raw("ISNULL(a.Keterangan, '') as Keterangan"),
                DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(d.Nama_Dokter)),''), p.PermintaanDPJP) as Nama_Dokter"),
                DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as asal_ruang"),
                DB::raw("ISNULL(cb.Ket_Bayar, p.Kode_Bayar) as jaminan"),
                'a.created_at as simrs_created_at',
            ]);

        // Kalau sudah ada banyak, pakai NOT IN untuk efisiensi
        if (! empty($existingNoRegs)) {
            $q->whereNotIn('a.No_Reg', $existingNoRegs);
        }

        return $q->orderBy('a.No_Reg')->get();
    }
}
