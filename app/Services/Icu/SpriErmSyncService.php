<?php

namespace App\Services\Icu;

use App\Models\IcuSpriInternal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpriErmSyncService
{
    public function sync(): int
    {
        $inserted = 0;

        try {
            // Ambil semua No_Reg aktif yang sudah ada di tabel kita
            $existingNoRegs = IcuSpriInternal::pluck('No_Reg')
                ->filter()
                ->map(fn ($v) => trim($v))
                ->unique()
                ->flip() 
                ->toArray();

            // Query ke RSUS: pasien IGD yang ada SPRI dengan IndikasiRI terisi
            $rows = DB::connection('sqlsrv_rsus')
                ->table('ASESMEN_SURAT_PERMINTAAN_RI as spri')
                ->join('PENDAFTARAN as p', 'spri.No_Reg', '=', 'p.No_Reg')
                ->join('REGISTER_PASIEN as rp', 'p.No_MR', '=', 'rp.No_MR')
                ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                ->where('p.Kode_Masuk', '1')          
                ->where('p.Status', '1')               
                ->whereNotNull('spri.IndikasiRI')
                ->where('spri.IndikasiRI', '<>', '')
                ->where('p.Tanggal', '>=', now()->subDays(3)->toDateString()) 
                ->select([
                    DB::raw('MAX(spri.No_Reg)      as No_Reg'),
                    DB::raw('MAX(p.No_MR)          as No_MR'),
                    DB::raw('MAX(spri.Diagnosis)   as Diagnosis'),
                    DB::raw('MAX(spri.IndikasiRI)  as IndikasiRI'),
                    DB::raw('MAX(spri.Spesialis)   as Spesialis'),
                    DB::raw("ISNULL(MAX(NULLIF(LTRIM(RTRIM(d.Nama_Dokter)),'')), MAX(p.PermintaanDPJP)) as Nama_Dokter"),
                ])
                ->groupBy('spri.No_Reg')
                ->get();

            $now = now();

            foreach ($rows as $row) {
                $noReg = trim($row->No_Reg ?? '');
                $noMr  = trim($row->No_MR  ?? '');

                if (! $noReg || ! $noMr) {
                    continue;
                }

                // Skip jika No_Reg sudah ada — deduplication
                if (array_key_exists($noReg, $existingNoRegs)) {
                    continue;
                }

                $namaDokter = $this->formatNamaDokter(trim($row->Nama_Dokter ?? ''));

                IcuSpriInternal::create([
                    'No_MR'      => $noMr,
                    'No_Reg'     => $noReg,
                    'Diagnosis'  => trim($row->Diagnosis ?? '-'),
                    'IndikasiRI' => trim($row->IndikasiRI ?? '-'),
                    'spesialis'  => trim($row->Spesialis ?? ''),
                    'asal_ruang' => 'IGD',
                    'NameUser'   => $namaDokter ?: 'dokter-erm',
                    'status'     => 'pending_icu',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Tambahkan ke set supaya kalau ada duplikat di batch yang sama juga skip
                $existingNoRegs[$noReg] = true;

                $inserted++;

                Log::info("[SpriErmSyncService] Insert No_Reg={$noReg}, No_MR={$noMr}, Dokter={$namaDokter}");
            }
        } catch (\Throwable $e) {
            Log::error('[SpriErmSyncService::sync] ' . $e->getMessage());
        }

        return $inserted;
    }

    private function formatNamaDokter(string $nama): string
    {
        if (empty($nama)) return '';
        return mb_convert_case(mb_strtolower($nama), MB_CASE_TITLE, 'UTF-8');
    }
}
