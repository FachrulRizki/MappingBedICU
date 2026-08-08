<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\RegistrasiPasien;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AntrianService
{
    private const SORT_ALLOWED = ['created_at', 'nama_pasien', 'status'];

    public function build(Request $request): array
    {
        $sortBy  = in_array($request->query('sort', 'created_at'), self::SORT_ALLOWED)
                   ? $request->query('sort') : 'created_at';
        $sortDir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $fJenis   = $request->query('jenis', '');
        $fNama    = trim($request->query('nama', ''));
        $today    = now()->format('Y-m-d');
        $fTglDari = $request->query('tgl_dari', '');
        $fTglAkh  = $request->query('tgl_sampai', '');

        // ── Antrian — server hanya filter nama & tanggal (untuk external), TIDAK filter status ──
        $externals = $fJenis !== 'internal'
            ? $this->queryExternal($fNama, $fTglDari, $fTglAkh)
            : collect();

        $internals = $fJenis !== 'external'
            ? $this->queryInternal($fNama, $fTglDari, $fTglAkh)
            : collect();

        $merged = collect($externals)->concat($internals)->values();

        // ── Inject dokter kolab ──────────────────────────────────────────────
        $noRegs = $merged->pluck('No_Reg')->filter()->unique()->values()->toArray();
        $dokterKolabMap = $this->fetchDokterKolab($noRegs);

        $merged = $merged->map(function (array $item) use ($dokterKolabMap) {
            $noReg = $item['No_Reg'] ?? null;
            $item['dokter_kolab'] = $noReg ? ($dokterKolabMap[$noReg] ?? []) : [];
            return $item;
        })->values();

        $merged = $merged->sortBy(
            fn ($item) => strtolower((string) ($item[$sortBy] ?? '')),
            SORT_REGULAR,
            $sortDir === 'desc'
        )->values();

        // ── Summary — ikut filter tanggal & jenis jika ada ────────────────────
        $dari   = ($fTglDari && $fTglAkh) ? $fTglDari . ' 00:00:00' : null;
        $sampai = ($fTglDari && $fTglAkh) ? $fTglAkh  . ' 23:59:59' : null;

        $qSummaryExt = IcuBookingExternal::whereIn('status', [
            'pending_icu', 'waiting_list', 'bed_confirmed', 'ditolak', 'admisi_verified', 'dibatalkan', 'masuk_icu', 'selesai',
        ]);
        $qSummaryInt = IcuSpriInternal::whereIn('status', [
            'pending_admisi', 'pending_icu', 'bed_verified', 'waiting_list', 'ditolak', 'dibatalkan', 'masuk_icu', 'selesai',
        ]);

        if ($dari && $sampai) {
            $qSummaryExt->where(fn ($q) => $q->whereBetween('created_at', [$dari, $sampai])
                ->orWhereBetween('confirmed_at', [$dari, $sampai])
                ->orWhereBetween('verified_at',  [$dari, $sampai])
                ->orWhereBetween('updated_at',   [$dari, $sampai]));
            $qSummaryInt->where(fn ($q) => $q->whereBetween('created_at',  [$dari, $sampai])
                ->orWhereBetween('approved_at',  [$dari, $sampai])
                ->orWhereBetween('verified_at',  [$dari, $sampai])
                ->orWhereBetween('updated_at',   [$dari, $sampai]));
        }

        // Filter nama di summary juga agar konsisten dengan tabel
        if ($fNama) {
            $qSummaryExt->where(fn ($q) => $q->where('nama_pasien', 'like', "%{$fNama}%")
                ->orWhere('No_MR', 'like', "%{$fNama}%"));

            $pasienIdsForSummary = RegistrasiPasien::where('Nama_Pasien', 'like', "%{$fNama}%")
                ->pluck('No_MR')->toArray();
            $qSummaryInt->where(fn ($q) => $q->whereIn('No_MR', $pasienIdsForSummary)
                ->orWhere('No_MR', 'like', "%{$fNama}%"));
        }

        // Filter jenis: hanya ambil sumber yang sesuai
        $allExternal = $fJenis !== 'internal'
            ? $qSummaryExt->get()->map(fn ($b) => ['status' => $b->status, 'sumber' => 'external'])
            : collect();
        $allInternal = $fJenis !== 'external'
            ? $qSummaryInt->get()->map(fn ($s) => ['status' => $s->status, 'sumber' => 'internal'])
            : collect();

        $allData = $allExternal->concat($allInternal);

        return [
            'antrian' => $merged,
            'summary' => $this->summary($allData),
            'filters' => [
                'filterStatus' => $request->query('status', ''),
                'filterJenis'  => $fJenis,
                'filterNama'   => $fNama,
                'filterTglDari'=> $fTglDari,
                'filterTglAkh' => $fTglAkh,
                'sortBy'       => $sortBy,
                'sortDir'      => $sortDir,
            ],
        ];
    }

    private function queryExternal(string $fNama, string $fTglDari = '', string $fTglAkh = ''): Collection
    {
        $activeStatuses = ['pending_icu', 'waiting_list', 'bed_confirmed', 'ditolak', 'admisi_verified', 'dibatalkan', 'masuk_icu', 'selesai'];
        $q = IcuBookingExternal::with('pasien')->whereIn('status', $activeStatuses);

        if ($fNama) {
            $q->where(function ($qq) use ($fNama) {
                $qq->where('nama_pasien', 'like', "%{$fNama}%")
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }

        if ($fTglDari && $fTglAkh) {
            $q->where(function ($qq) use ($fTglDari, $fTglAkh) {
                $qq->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59'])
                   ->orWhereBetween('confirmed_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59'])
                   ->orWhereBetween('verified_at',  [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);
            });
        }

        return $q->oldest()->get()->map(fn ($b) => $this->fmtExt($b));
    }

    private function queryInternal(string $fNama, string $fTglDari = '', string $fTglAkh = ''): Collection
    {
        $activeStatuses = ['pending_admisi', 'pending_icu', 'bed_verified', 'waiting_list', 'ditolak', 'dibatalkan', 'masuk_icu', 'selesai'];
        $q = IcuSpriInternal::whereIn('status', $activeStatuses);

        if ($fNama) {
            $pasienIds = RegistrasiPasien::where('Nama_Pasien', 'like', "%{$fNama}%")
                ->pluck('No_MR')->toArray();
            $q->where(function ($qq) use ($fNama, $pasienIds) {
                $qq->whereIn('No_MR', $pasienIds)
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }
        
        if ($fTglDari && $fTglAkh) {
            $q->where(function ($qq) use ($fTglDari, $fTglAkh) {
                $qq->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59'])
                   ->orWhereBetween('approved_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59'])
                   ->orWhereBetween('verified_at',  [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);
            });
        }

        $results    = $q->oldest()->get();
        $noMrs      = $results->pluck('No_MR')->filter()->unique()->values()->toArray();
        $pasienMap  = $this->fetchPasienMap($noMrs);
        $jaminanMap = $this->buildJaminanMap($results->pluck('No_Reg')->filter()->unique()->values()->toArray());

        return $results->map(fn ($s) => $this->fmtInt($s, $jaminanMap[$s->No_Reg] ?? null, $pasienMap[$s->No_MR] ?? null));
    }

    /**
     * Batch-load RegistrasiPasien dari RSUS.
     * Cache per-record (No_MR) 10 menit — data nama pasien jarang berubah.
     * Ini lebih efisien dari cache per-kombinasi: record lama tetap ter-cache meski ada record baru.
     */
    private function fetchPasienMap(array $noMrs): array
    {
        if (empty($noMrs)) return [];

        $result  = [];
        $missing = [];

        foreach ($noMrs as $noMr) {
            $cached = Cache::get("pasien:{$noMr}");
            if ($cached !== null) {
                $result[$noMr] = $cached;
            } else {
                $missing[] = $noMr;
            }
        }

        if (! empty($missing)) {
            try {
                $rows = RegistrasiPasien::whereIn('No_MR', $missing)
                    ->get(['No_MR', 'Nama_Pasien', 'Jenis_Kelamin', 'jenis_kelamin'])
                    ->keyBy('No_MR')
                    ->toArray();

                foreach ($missing as $noMr) {
                    $val = $rows[$noMr] ?? [];
                    Cache::put("pasien:{$noMr}", $val, 600); // 10 menit
                    $result[$noMr] = $val;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[fetchPasienMap] ' . $e->getMessage());
            }
        }

        return $result;
    }

    private function buildJaminanMap(array $noRegs): array
    {
        if (empty($noRegs)) return [];

        $result  = [];
        $missing = [];

        foreach ($noRegs as $noReg) {
            $cached = Cache::get("jaminan:{$noReg}");
            if ($cached !== null) {
                $result[$noReg] = $cached;
            } else {
                $missing[] = $noReg;
            }
        }

        if (! empty($missing)) {
            try {
                $rows = DB::connection('sqlsrv_rsus')
                    ->table('PENDAFTARAN as p')
                    ->leftJoin('M_CARABAYAR as cb', 'p.Kode_Bayar', '=', 'cb.Kode_Bayar')
                    ->whereIn('p.No_Reg', $missing)
                    ->select([
                        'p.No_Reg',
                        DB::raw("ISNULL(cb.Ket_Bayar, p.Kode_Bayar) as ket_bayar"),
                    ])
                    ->get();

                foreach ($missing as $noReg) {
                    $val = $rows->firstWhere('No_Reg', $noReg)?->ket_bayar ?? null;
                    Cache::put("jaminan:{$noReg}", $val ?? '', 300); // 5 menit
                    $result[$noReg] = $val;
                }
            } catch (\Exception) {
                // fallback: isi null agar tidak retry terus
                foreach ($missing as $noReg) {
                    $result[$noReg] = null;
                }
            }
        }

        return $result;
    }

    private function fetchDokterKolab(array $noRegs): array
    {
        if (empty($noRegs)) return [];

        $result  = [];
        $missing = [];

        foreach ($noRegs as $noReg) {
            $cached = Cache::get("dokter_kolab:{$noReg}");
            if ($cached !== null) {
                $result[$noReg] = $cached;
            } else {
                $missing[] = $noReg;
            }
        }

        if (! empty($missing)) {
            try {
                $rows = DB::connection('sqlsrv_rsus')
                    ->table('ASESMEN_DOKTER_KOLABORASI as adk')
                    ->leftJoin('DOKTER as d', 'adk.Dokter', '=', 'd.Kode_Dokter')
                    ->where('adk.Ket', '!=', 'Sayhello')
                    ->whereIn('adk.No_Reg', $missing)
                    ->select(['adk.No_Reg', 'd.Nama_Dokter', 'adk.Dokter as Kode_Dokter', 'adk.Ket'])
                    ->get();

                // Kelompokkan per No_Reg
                $grouped = [];
                foreach ($rows as $row) {
                    if ($row->No_Reg) {
                        $grouped[$row->No_Reg][] = [
                            'nama' => $row->Nama_Dokter ?? $row->Kode_Dokter,
                            'ket'  => $row->Ket,
                        ];
                    }
                }

                foreach ($missing as $noReg) {
                    $val = $grouped[$noReg] ?? [];
                    Cache::put("dokter_kolab:{$noReg}", $val, 300); // 5 menit
                    $result[$noReg] = $val;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[fetchDokterKolab] ' . $e->getMessage());
                foreach ($missing as $noReg) {
                    $result[$noReg] = [];
                }
            }
        }

        return $result;
    }

    private function hitungLamaProses($mulai, $selesai): ?string
    {
        if (!$mulai || !$selesai) {
            return null;
        }

        $diff = $mulai->diff($selesai);

        $hasil = [];

        if ($diff->d > 0) {
            $hasil[] = "{$diff->d} hari";
        }

        if ($diff->h > 0) {
            $hasil[] = "{$diff->h} jam";
        }

        if ($diff->i > 0) {
            $hasil[] = "{$diff->i} menit";
        }

        return empty($hasil) ? "0 menit" : implode(' ', $hasil);
    }

    private function summary(Collection $data): array
    {
        $st = fn ($item) => is_array($item) ? ($item['status'] ?? '') : ($item->status ?? '');
        $sr = fn ($item) => is_array($item) ? ($item['sumber'] ?? '') : ($item->sumber ?? '');

        return [
            'total'          => $data->count(),
            'pending_admisi' => $data->filter(fn ($i) => $st($i) === 'pending_admisi')->count(),
            'pending_icu'    => $data->filter(fn ($i) => $st($i) === 'pending_icu')->count(),
            'pending'        => $data->filter(fn ($i) => in_array($st($i), ['pending_icu', 'pending_admisi']))->count(),
            'waiting_list'   => $data->filter(fn ($i) => $st($i) === 'waiting_list')->count(),
            'bed_confirmed'  => $data->filter(fn ($i) => $st($i) === 'bed_confirmed')->count(),
            'bed_verified'   => $data->filter(fn ($i) => $st($i) === 'bed_verified')->count(),
            'bed_aktif'      => $data->filter(fn ($i) => in_array($st($i), ['bed_confirmed', 'bed_verified']))->count(),
            'admisi_verified'=> $data->filter(fn ($i) => $st($i) === 'admisi_verified')->count(),
            'verified'       => $data->filter(fn ($i) => in_array($st($i), ['admisi_verified', 'bed_verified']))->count(),
            'masuk_icu'      => $data->filter(fn ($i) => $st($i) === 'masuk_icu')->count(),
            'selesai'        => $data->filter(fn ($i) => $st($i) === 'selesai')->count(),
            'ditolak'        => $data->filter(fn ($i) => $st($i) === 'ditolak')->count(),
            'dibatalkan'     => $data->filter(fn ($i) => $st($i) === 'dibatalkan')->count(),
            'by_sumber'      => [
                'external' => $data->filter(fn ($i) => $sr($i) === 'external')->count(),
                'internal' => $data->filter(fn ($i) => $sr($i) === 'internal')->count(),
            ],
        ];
    }

    public function fmtExt(IcuBookingExternal $b): array
    {
        return [
            'id'               => $b->id,
            'sumber'           => 'external',
            'sumber_label'     => 'Booking Ext.',
            'nama_pasien'      => $b->pasien?->Nama_Pasien ?? $b->nama_pasien,
            'nama_pasien_raw'  => $b->nama_pasien,
            'No_MR'            => $b->No_MR,
            'No_Reg'           => $b->No_Reg,
            'jenis_kelamin'    => $b->jenis_kelamin,
            'asal_rujukan'     => $b->asal_rujukan,
            'asal_ruang'       => $b->asal_rujukan,
            'Dokter'           => null,
            'diagnosa'         => $b->diagnosa,
            'diagnosa_icd'     => $b->diagnosa_icd,
            'rencana_tindakan' => $b->rencana_tindakan,
            'kebutuhan_bed'    => $b->kebutuhan_bed,
            'nama_bed'         => $b->nama_bed,
            'jaminan'          => $b->jaminan,
            'catatan_jaminan'  => $b->catatan_jaminan,
            'keterangan'       => $b->keterangan,
            'no_telp_keluarga' => $b->no_telp_keluarga,
            'status'           => $b->status,
            'status_label'     => $b->statusLabel(),
            'alasan_tolak'     => $b->alasan_tolak,
            'alasan_batal'     => $b->alasan_batal,
            'dibatalkan_by'    => $b->dibatalkan_by,
            'dibatalkan_at'    => $b->dibatalkan_at?->format('Y-m-d H:i'),
            'dibatalkan_at_fmt'=> $b->dibatalkan_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            // waiting list
            'waiting_alasan'   => $b->waiting_alasan,
            'waiting_estimasi' => $b->waiting_estimasi?->format('Y-m-d H:i'),
            'waiting_estimasi_fmt' => $b->waiting_estimasi?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'waiting_by'       => $b->waiting_by,
            // pindah bed
            'pindah_alasan'    => $b->pindah_alasan,
            'pindah_bed_lama'  => $b->pindah_bed_lama,
            'pindah_by'        => $b->pindah_by,
            'pindah_at'        => $b->pindah_at?->format('Y-m-d H:i'),
            'pindah_at_fmt'    => $b->pindah_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'created_at'       => $b->created_at?->format('Y-m-d H:i'),
            'created_at_fmt'   => $b->created_at?->format('d/m/Y H:i'),
            'created_by'       => $b->created_by,
            'confirmed_by'     => $b->confirmed_by,
            'confirmed_at'     => $b->confirmed_at?->format('Y-m-d H:i'),
            'confirmed_at_fmt' => $b->confirmed_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'verified_by'      => $b->verified_by,
            'verified_at'      => $b->verified_at?->format('Y-m-d H:i'),
            'verified_at_fmt'  => $b->verified_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'lama_proses'      => $this->hitungLamaProses($b->created_at,$b->verified_at),
            'masuk_at'         => $b->masuk_at?->format('Y-m-d H:i'),
            'masuk_at_fmt'     => $b->masuk_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'keluar_at'        => $b->keluar_at?->format('Y-m-d H:i'),
            'keluar_at_fmt'    => $b->keluar_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'allocated_bed_id' => $b->allocated_bed_id,
        ];
    }

    public function fmtInt(IcuSpriInternal $s, ?string $jaminan = null, ?array $pasien = null): array
    {
        // $pasien bisa array dari cache (fetchPasienMap) atau null — fallback ke relasi Eloquent
        $namaKelamin = $pasien
            ? strtoupper($pasien['Jenis_Kelamin'] ?? $pasien['jenis_kelamin'] ?? '')
            : strtoupper($s->pasien?->Jenis_Kelamin ?? $s->pasien?->jenis_kelamin ?? '');
        $namaPasien = $pasien['Nama_Pasien'] ?? $s->pasien?->Nama_Pasien ?? $s->No_MR;

        return [
            'id'             => $s->id,
            'sumber'         => 'internal',
            'sumber_label'   => 'Booking Internal',
            'nama_pasien'    => $namaPasien,
            'No_MR'          => $s->No_MR,
            'No_Reg'         => $s->No_Reg,
            'jenis_kelamin'  => $namaKelamin,
            'asal_rujukan'   => $s->asal_ruang,
            'asal_ruang'     => $s->asal_ruang,
            'Dokter'         => $s->Dokter,
            'diagnosa'       => $s->Diagnosis,
            'diagnosa_icd'   => $s->Diagnosis_ICD,
            'IndikasiRI'     => $s->IndikasiRI,
            'spesialis'      => $s->spesialis,
            'kebutuhan_bed'  => $s->kebutuhan_bed,
            'nama_bed'       => $s->nama_bed,
            'jaminan'        => $jaminan,
            'catatan_admisi' => $s->catatan_admisi,
            'keterangan'     => $s->Keterangan,
            'status'         => $s->status,
            'status_label'   => $s->statusLabel(),
            'alasan_tolak'   => $s->alasan_tolak,
            'alasan_batal'   => $s->alasan_batal,
            'dibatalkan_by'  => $s->dibatalkan_by,
            'dibatalkan_at'  => $s->dibatalkan_at?->format('Y-m-d H:i'),
            'dibatalkan_at_fmt' => $s->dibatalkan_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            // waiting list
            'waiting_alasan'   => $s->waiting_alasan,
            'waiting_estimasi' => $s->waiting_estimasi?->format('Y-m-d H:i'),
            'waiting_estimasi_fmt' => $s->waiting_estimasi?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'waiting_by'       => $s->waiting_by,
            // pindah bed
            'pindah_alasan'    => $s->pindah_alasan,
            'pindah_bed_lama'  => $s->pindah_bed_lama,
            'pindah_by'        => $s->pindah_by,
            'pindah_at'        => $s->pindah_at?->format('Y-m-d H:i'),
            'pindah_at_fmt'    => $s->pindah_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'created_at'     => $s->created_at?->format('Y-m-d H:i'),
            'created_at_fmt' => $s->created_at?->format('d/m/Y H:i'),
            'created_by'     => $s->NameUser ?? '-',
            'approved_by'    => $s->approved_by,
            'approved_at'    => $s->approved_at?->format('Y-m-d H:i'),
            'approved_at_fmt'=> $s->approved_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'verified_by'    => $s->verified_by,
            'verified_at'    => $s->verified_at?->format('Y-m-d H:i'),
            'verified_at_fmt'=> $s->verified_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'lama_proses'    => $this->hitungLamaProses($s->created_at, $s->verified_at),
            'masuk_at'       => $s->masuk_at?->format('Y-m-d H:i'),
            'masuk_at_fmt'   => $s->masuk_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'keluar_at'      => $s->keluar_at?->format('Y-m-d H:i'),
            'keluar_at_fmt'  => $s->keluar_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'allocated_bed_id' => $s->allocated_bed_id,
        ];
    }
}
