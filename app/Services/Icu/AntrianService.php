<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AntrianService
{
    private const SORT_ALLOWED = ['created_at', 'nama_pasien', 'status'];

    /**
     * Bangun merged collection External + Internal beserta summary.
     */
    public function build(Request $request): array
    {
        $sortBy  = in_array($request->query('sort', 'created_at'), self::SORT_ALLOWED)
                   ? $request->query('sort') : 'created_at';
        $sortDir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $fStatus  = $request->query('status', '');
        $fJenis   = $request->query('jenis', '');
        $fNama    = trim($request->query('nama', ''));
        // Default: hari ini. Jika user kirim range, pakai range tersebut.
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
        $q = IcuBookingExternal::with('pasien');
        if ($fStatus) $q->where('status', $fStatus);
        if ($fNama) {
            $q->where(function ($qq) use ($fNama) {
                $qq->where('nama_pasien', 'like', "%{$fNama}%")
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }
        if ($fTglDari && $fTglAkh) {
            $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);
        }

        return $q->latest()->get()->map(fn ($b) => $this->fmtExt($b));
    }
    

    // private function queryInternal(string $fStatus, string $fNama, string $fTgl): Collection
    // {
    //     $q = IcuSpriInternal::with('pasien');
    //     if ($fStatus) $q->where('status', $fStatus);
    //     if ($fNama) {
    //         $q->where(function ($qq) use ($fNama) {
    //             $qq->whereHas('pasien', fn ($p) => $p->where('Nama_Pasien', 'like', "%{$fNama}%"))
    //                ->orWhere('No_MR', 'like', "%{$fNama}%");
    //         });
    //     }
    //     if ($fTgl) $q->whereDate('created_at', $fTgl);

    //     return $q->latest()->get()->map(fn ($s) => $this->fmtInt($s));
    // }

    private function queryInternal(string $fStatus, string $fNama, string $fTglDari, string $fTglAkh): Collection
    {
        $q = IcuSpriInternal::query();

        if ($fStatus) {
            $q->where('status', $fStatus);
        }

        if ($fNama) {
            $pasienIds = \App\Models\RegistrasiPasien::query()
                ->where('Nama_Pasien', 'like', "%{$fNama}%")
                ->pluck('No_MR')
                ->toArray();
            $q->where(function ($qq) use ($fNama, $pasienIds) {
                $qq->whereIn('No_MR', $pasienIds)
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }

        if ($fTglDari && $fTglAkh) {
            $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);
        }

        $results = $q->latest()->get();
        $noRegs = $results->pluck('No_Reg')->filter()->unique()->values()->toArray();
        $jaminanMap = [];
        if (!empty($noRegs)) {
            try {
                $isRsus = \App\Models\RegistrasiPasien::rsusAvailable();
                $conn    = $isRsus ? 'sqlsrv_rsus' : 'mysql';
                $cbTable = $isRsus ? 'M_CARABAYAR' : 'm_carabayar';
                $pTable  = $isRsus ? 'PENDAFTARAN'  : 'pendaftaran';
                $ketCol  = 'Ket_Bayar';

                $rows = \Illuminate\Support\Facades\DB::connection($conn)
                    ->table("{$pTable} as p")
                    ->leftJoin("{$cbTable} as cb", 'p.Kode_Bayar', '=', 'cb.Kode_Bayar')
                    ->whereIn('p.No_Reg', $noRegs)
                    ->select([
                        'p.No_Reg',
                        \Illuminate\Support\Facades\DB::raw(
                            $isRsus
                                ? "ISNULL(cb.{$ketCol}, p.Kode_Bayar) as ket_bayar"
                                : "COALESCE(cb.{$ketCol}, p.Kode_Bayar) as ket_bayar"
                        ),
                    ])
                    ->get();

                foreach ($rows as $row) {
                    $jaminanMap[$row->No_Reg] = $row->ket_bayar ?? '';
                }
            } catch (\Exception $e) {
                // jaminan tidak wajib, abaikan error koneksi
            }
        }

        return $results->map(function ($s) use ($jaminanMap) {
            return $this->fmtInt($s, $jaminanMap[$s->No_Reg] ?? null);
        });
    }

    private function summary(Collection $data): array
    {
        $pendingStatuses  = ['pending_icu', 'pending_admisi'];
        $confirmedStatuses = ['bed_confirmed', 'bed_verified'];
        $verifiedStatuses  = ['admisi_verified', 'bed_verified'];

        return [
            'total'         => $data->count(),
            'pending'       => $data->filter(fn ($i) => in_array($i['status'] ?? '', $pendingStatuses))->count(),
            'bed_confirmed' => $data->filter(fn ($i) => in_array($i['status'] ?? '', $confirmedStatuses))->count(),
            'verified'      => $data->filter(fn ($i) => in_array($i['status'] ?? '', $verifiedStatuses))->count(),
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
            'created_at'       => $b->created_at?->format('Y-m-d H:i'),
            'created_at_fmt'   => $b->created_at?->format('d/m/Y H:i'),
            'created_by'       => $b->created_by,
            'confirmed_by'     => $b->confirmed_by,
            'verified_by'      => $b->verified_by,
        ];
    }

    public function fmtInt(IcuSpriInternal $s, ?string $jaminan = null): array
    {
        return [
            'id'             => $s->id,
            'sumber'         => 'internal',
            'sumber_label'   => 'BU Internal',
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
            'created_at'     => $s->created_at?->format('Y-m-d H:i'),
            'created_at_fmt' => $s->created_at?->format('d/m/Y H:i'),
            'created_by'     => $s->NameUser ?? $s->NamaUser ?? '-',
            'approved_by'    => $s->approved_by,
            'verified_by'    => $s->verified_by,
        ];
    }

}