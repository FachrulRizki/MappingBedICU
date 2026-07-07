<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\RegistrasiPasien;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AntrianService
{
    private const SORT_ALLOWED = ['created_at', 'nama_pasien', 'status'];

    /**
     * Bangun merged collection External + Internal beserta summary.
     */
    public function build(Request $request): array
    {
        // Default sort: oldest first (created_at ASC) — siapa duluan, prioritas duluan
        $sortBy  = in_array($request->query('sort', 'created_at'), self::SORT_ALLOWED)
                   ? $request->query('sort') : 'created_at';
        $sortDir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $fStatus  = $request->query('status', '');
        $fJenis   = $request->query('jenis', '');
        $fNama    = trim($request->query('nama', ''));
        $today    = now()->format('Y-m-d');
        $fTgl     = $request->query('tgl', '');
        $fTglDari = $request->query('tgl_dari', $fTgl ?: $today);
        $fTglAkh  = $request->query('tgl_sampai', $fTgl ?: $today);

        $externals = $fJenis !== 'internal'
            ? $this->queryExternal($fStatus, $fNama, $fTglDari, $fTglAkh)
            : collect();

        $internals = $fJenis !== 'external'
            ? $this->queryInternal($fStatus, $fNama, $fTglDari, $fTglAkh)
            : collect();

        $merged = collect($externals)->concat($internals)->values();

        // ── Inject dokter kolab ──────────────────────────────────
        $noRegs = $merged->pluck('No_Reg')->filter()->unique()->values()->toArray();
        $dokterKolabMap = $this->fetchDokterKolab($noRegs);

        $merged = $merged->map(function (array $item) use ($dokterKolabMap) {
            $noReg = $item['No_Reg'] ?? null;
            $item['dokter_kolab'] = $noReg ? ($dokterKolabMap[$noReg] ?? []) : [];
            return $item;
        })->values();

        // Sorting: default oldest first (terlama = prioritas tertinggi)
        $merged = $merged->sortBy(
            fn ($item) => strtolower((string) ($item[$sortBy] ?? '')),
            SORT_REGULAR,
            $sortDir === 'desc'
        )->values();

        return [
            'antrian' => $merged,
            'summary' => $this->summary($merged),
            'filters' => [
                'filterStatus' => $fStatus,
                'filterJenis'  => $fJenis,
                'filterNama'   => $fNama,
                'filterTgl'    => $fTgl,
                'filterTglDari'=> $fTglDari,
                'filterTglAkh' => $fTglAkh,
                'sortBy'       => $sortBy,
                'sortDir'      => $sortDir,
            ],
        ];
    }

    private function queryExternal(string $fStatus, string $fNama, string $fTglDari, string $fTglAkh): Collection
    {
        $activeStatuses = ['pending_icu', 'waiting_list', 'bed_confirmed'];
        $q = IcuBookingExternal::with('pasien');

        // Filter status: jika ada filter spesifik, pakai itu; jika tidak, tampilkan semua aktif
        if ($fStatus) {
            $q->where('status', $fStatus);
        } else {
            $q->whereIn('status', $activeStatuses);
        }

        if ($fNama) {
            $q->where(function ($qq) use ($fNama) {
                $qq->where('nama_pasien', 'like', "%{$fNama}%")
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }

        if ($fTglDari && $fTglAkh) {
            $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);
        }

        // Oldest first — siapa booking duluan, prioritas duluan
        return $q->oldest()->get()->map(fn ($b) => $this->fmtExt($b));
    }

    private function queryInternal(string $fStatus, string $fNama, string $fTglDari, string $fTglAkh): Collection
    {
        $activeStatuses = ['pending_admisi', 'pending_icu', 'waiting_list', 'bed_verified'];
        $q = IcuSpriInternal::query();

        if ($fStatus) {
            $q->where('status', $fStatus);
        } else {
            $q->whereIn('status', $activeStatuses);
        }

        if ($fNama) {
            $pasienIds = RegistrasiPasien::where('Nama_Pasien', 'like', "%{$fNama}%")
                ->pluck('No_MR')->toArray();
            $q->where(function ($qq) use ($fNama, $pasienIds) {
                $qq->whereIn('No_MR', $pasienIds)
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }

        if ($fTglDari && $fTglAkh) {
            $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);
        }

        // Oldest first
        $results    = $q->oldest()->get();
        $jaminanMap = $this->buildJaminanMap($results->pluck('No_Reg')->filter()->unique()->values()->toArray());

        return $results->map(fn ($s) => $this->fmtInt($s, $jaminanMap[$s->No_Reg] ?? null));
    }

    private function buildJaminanMap(array $noRegs): array
    {
        if (empty($noRegs)) return [];

        $conn    = RegistrasiPasien::activeConnection();
        $isRsus  = RegistrasiPasien::rsusAvailable();
        $pTable  = $isRsus ? 'PENDAFTARAN'  : 'pendaftaran';
        $cbTable = $isRsus ? 'M_CARABAYAR'  : 'm_carabayar';
        $cbKet   = $isRsus ? 'Ket_Bayar'    : 'KET_BAYAR';
        $cbKode  = $isRsus ? 'Kode_Bayar'   : 'KODE_BAYAR';
        $isnull  = $isRsus ? 'ISNULL' : 'COALESCE';

        try {
            $rows = DB::connection($conn)
                ->table("{$pTable} as p")
                ->leftJoin("{$cbTable} as cb", "p.Kode_Bayar", '=', "cb.{$cbKode}")
                ->whereIn('p.No_Reg', $noRegs)
                ->select([
                    'p.No_Reg',
                    DB::raw("{$isnull}(cb.{$cbKet}, p.Kode_Bayar) as ket_bayar"),
                ])
                ->get();

            return $rows->pluck('ket_bayar', 'No_Reg')->toArray();
        } catch (\Exception) {
            return [];
        }
    }

    private function fetchDokterKolab(array $noRegs): array
    {
        if (empty($noRegs)) return [];

        try {
            $isRsus  = \App\Models\RegistrasiPasien::rsusAvailable();
            $conn    = $isRsus ? 'sqlsrv_rsus' : config('database.default');
            $adkTbl  = $isRsus ? 'ASESMEN_DOKTER_KOLABORASI' : 'asesmen_dokter_kolaborasi';
            $dTbl    = $isRsus ? 'DOKTER' : 'dokter';

            $rows = \Illuminate\Support\Facades\DB::connection($conn)
                ->table("{$adkTbl} as adk")
                ->leftJoin("{$dTbl} as d", 'adk.Dokter', '=', 'd.Kode_Dokter')
                ->where('adk.Ket', '!=', 'Sayhello')
                ->whereIn('adk.No_Reg', $noRegs)
                ->select(['adk.No_Reg', 'd.Nama_Dokter', 'adk.Dokter as Kode_Dokter', 'adk.Ket'])
                ->get();

            // Group by No_Reg → array of dokter names
           $map = [];
            foreach ($rows as $row) {
                if ($row->No_Reg) {
                    $map[$row->No_Reg][] = [
                        'nama' => $row->Nama_Dokter ?? $row->Kode_Dokter,
                        'ket'  => $row->Ket,
                    ];
                }
            }
            return $map;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[fetchDokterKolab] ' . $e->getMessage());
            return [];
        }
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
        return [
            'total'         => $data->count(),
            'pending'       => $data->filter(fn ($i) => in_array($i['status'] ?? '', ['pending_icu', 'pending_admisi']))->count(),
            'waiting_list'  => $data->filter(fn ($i) => ($i['status'] ?? '') === 'waiting_list')->count(),
            'bed_confirmed' => $data->filter(fn ($i) => in_array($i['status'] ?? '', ['bed_confirmed', 'bed_verified']))->count(),
            'verified'      => $data->filter(fn ($i) => in_array($i['status'] ?? '', ['admisi_verified', 'bed_verified']))->count(),
            'ditolak'       => $data->filter(fn ($i) => ($i['status'] ?? '') === 'ditolak')->count(),
            'by_sumber'     => [
                'external' => $data->filter(fn ($i) => ($i['sumber'] ?? '') === 'external')->count(),
                'internal' => $data->filter(fn ($i) => ($i['sumber'] ?? '') === 'internal')->count(),
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
            // waiting list
            'waiting_alasan'   => $b->waiting_alasan,
            'waiting_estimasi' => $b->waiting_estimasi?->format('Y-m-d H:i'),
            'waiting_estimasi_fmt' => $b->waiting_estimasi?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'waiting_by'       => $b->waiting_by,
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
        ];
    }

    public function fmtInt(IcuSpriInternal $s, ?string $jaminan = null): array
    {
        return [
            'id'             => $s->id,
            'sumber'         => 'internal',
            'sumber_label'   => 'Booking Internal',
            'nama_pasien'    => $s->pasien?->Nama_Pasien ?? $s->No_MR,
            'No_MR'          => $s->No_MR,
            'No_Reg'         => $s->No_Reg,
            'jenis_kelamin'  => $s->pasien
                ? strtoupper($s->pasien->Jenis_Kelamin ?? $s->pasien->jenis_kelamin ?? '')
                : null,
            'asal_rujukan'   => $s->asal_ruang,
            'asal_ruang'     => $s->asal_ruang,
            'Dokter'         => $s->Dokter,
            'diagnosa'       => $s->Diagnosis,
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
            // waiting list
            'waiting_alasan'   => $s->waiting_alasan,
            'waiting_estimasi' => $s->waiting_estimasi?->format('Y-m-d H:i'),
            'waiting_estimasi_fmt' => $s->waiting_estimasi?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'waiting_by'       => $s->waiting_by,
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
        ];
    }
}
