<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MenuYanmedController extends Controller
{
    public function index(Request $request): Response
    {
        $fJenis   = $request->query('jenis', '');
        $fStatus  = $request->query('status', '');
        $fAsal    = trim($request->query('asal', ''));
        $fNama    = trim($request->query('nama', ''));
        $fTglDari = $request->query('tgl_dari', now()->format('Y-m-d'));
        $fTglAkh  = $request->query('tgl_sampai', now()->format('Y-m-d'));

        // ── Booking External ──────────────────────────────────────────────────
        $qExt = IcuBookingExternal::with('pasien')
            ->whereNotIn('status', ['ditolak', 'dibatalkan']);

        if ($fStatus) {
            $qExt->where('status', $fStatus);
        }
        if ($fNama) {
            $qExt->where(function ($q) use ($fNama) {
                $q->where('nama_pasien', 'like', "%{$fNama}%")
                  ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }
        if ($fAsal) {
            $qExt->where('asal_rujukan', 'like', "%{$fAsal}%");
        }

        // Filter tanggal — data aktif (bed_confirmed/admisi_verified) selalu tampil
        $qExt->where(function ($q) use ($fTglDari, $fTglAkh) {
            $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59'])
              ->orWhereIn('status', ['pending_icu', 'waiting_list', 'bed_confirmed', 'admisi_verified']);
        });

        $externals = $qExt->oldest()->get();

        // ── SPRI Internal ─────────────────────────────────────────────────────
        $qInt = IcuSpriInternal::whereNotIn('status', ['ditolak', 'dibatalkan']);

        if ($fStatus) {
            $qInt->where('status', $fStatus);
        }
        if ($fAsal) {
            $qInt->where('asal_ruang', 'like', "%{$fAsal}%");
        }

        $qInt->where(function ($q) use ($fTglDari, $fTglAkh) {
            $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59'])
              ->orWhereIn('status', ['pending_admisi', 'pending_icu', 'waiting_list', 'bed_verified']);
        });

        $internals = $qInt->oldest()->get();

        // ── Lookup nama pasien Internal dari DB RS ────────────────────────────
        $noMrList = $internals->pluck('No_MR')->filter()->unique()->values()->toArray();
        $pasienMap = [];
        if (!empty($noMrList)) {
            try {
                $pasienMap = RegistrasiPasien::whereIn('No_MR', $noMrList)
                    ->get(['No_MR', 'Nama_Pasien', 'Jenis_Kelamin'])
                    ->keyBy('No_MR')
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('[MenuYanmedController] lookup pasien: ' . $e->getMessage());
            }
        }

        // ── Lookup dokter kolab ───────────────────────────────────────────────
        $allNoRegs = $externals->pluck('No_Reg')
            ->merge($internals->pluck('No_Reg'))
            ->filter()->unique()->values()->toArray();
        $dokterKolabMap = $this->fetchDokterKolab($allNoRegs);

        // ── Format External ───────────────────────────────────────────────────
        $fmtExt = $externals->map(function ($b) use ($dokterKolabMap) {
            return [
                'id'            => $b->id,
                'sumber'        => 'external',
                'sumber_label'  => 'Booking Eksternal',
                'nama_pasien'   => $b->pasien?->Nama_Pasien ?? $b->nama_pasien,
                'No_MR'         => $b->No_MR,
                'No_Reg'        => $b->No_Reg,
                'jenis_kelamin' => $b->jenis_kelamin,
                'asal'          => $b->asal_rujukan,
                'diagnosa'      => $b->diagnosa,
                'diagnosa_icd'  => $b->diagnosa_icd,
                'indikasi'      => $b->rencana_tindakan,
                'kebutuhan_bed' => $b->kebutuhan_bed,
                'nama_bed'      => $b->nama_bed,
                'jaminan'       => $b->jaminan,
                'status'        => $b->status,
                'status_label'  => $b->statusLabel(),
                'dokter_kolab'  => $dokterKolabMap[$b->No_Reg] ?? [],
                'created_at_fmt'=> $b->created_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
                'confirmed_at_fmt' => $b->confirmed_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
                'verified_at_fmt'  => $b->verified_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ];
        });

        // ── Format Internal ───────────────────────────────────────────────────
        $fmtInt = $internals->map(function ($s) use ($pasienMap, $dokterKolabMap) {
            $pasien = $pasienMap[$s->No_MR] ?? null;
            return [
                'id'            => $s->id,
                'sumber'        => 'internal',
                'sumber_label'  => 'Booking Internal',
                'nama_pasien'   => $pasien['Nama_Pasien'] ?? $s->No_MR,
                'No_MR'         => $s->No_MR,
                'No_Reg'        => $s->No_Reg,
                'jenis_kelamin' => strtoupper($pasien['Jenis_Kelamin'] ?? ''),
                'asal'          => $s->asal_ruang,
                'diagnosa'      => $s->Diagnosis,
                'diagnosa_icd'  => $s->Diagnosis_ICD,
                'indikasi'      => $s->IndikasiRI,
                'kebutuhan_bed' => $s->kebutuhan_bed,
                'nama_bed'      => $s->nama_bed,
                'jaminan'       => null,
                'dokter'        => $s->Dokter,
                'status'        => $s->status,
                'status_label'  => $s->statusLabel(),
                'dokter_kolab'  => $dokterKolabMap[$s->No_Reg] ?? [],
                'created_at_fmt'=> $s->created_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
                'approved_at_fmt' => $s->approved_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
                'verified_at_fmt' => $s->verified_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ];
        });

        // Nama pasien filter untuk internal setelah ada nama
        if ($fNama) {
            $fmtInt = $fmtInt->filter(function ($item) use ($fNama) {
                return str_contains(strtolower($item['nama_pasien']), strtolower($fNama))
                    || str_contains(strtolower($item['No_MR'] ?? ''), strtolower($fNama));
            })->values();
        }

        // ── Merge & filter jenis ──────────────────────────────────────────────
        $semua = match ($fJenis) {
            'external' => $fmtExt->values(),
            'internal' => $fmtInt->values(),
            default    => $fmtExt->concat($fmtInt)->sortBy('created_at_fmt')->values(),
        };

        // ── Summary ───────────────────────────────────────────────────────────
        $all        = $fmtExt->concat($fmtInt);
        $bedData    = MRuangMaster::bedIcuDenganStatus();

        $summary = [
            'total'          => $semua->count(),
            'total_ext'      => $fmtExt->count(),
            'total_int'      => $fmtInt->count(),
            'menunggu'       => $semua->filter(fn($i) => in_array($i['status'], ['pending_icu', 'pending_admisi', 'waiting_list']))->count(),
            'terkonfirmasi'  => $semua->filter(fn($i) => in_array($i['status'], ['bed_confirmed', 'bed_verified', 'admisi_verified']))->count(),
            'total_bed'      => $bedData->count(),
            'bed_kosong'     => $bedData->where('Status', 'KOSONG')->count(),
            'bed_terisi'     => $bedData->where('Status', 'ISI')->count(),
            'bed_booking'    => $bedData->where('Status', 'BOOKING')->count(),
            // Breakdown asal — top 5
            'by_asal'        => $all->groupBy('asal')
                ->map(fn($g, $key) => ['asal' => $key ?: 'Tidak diketahui', 'jumlah' => $g->count()])
                ->sortByDesc('jumlah')
                ->values()
                ->take(10),
        ];

        return Inertia::render('Icu/MenuYanmed', [
            'pasien'  => $semua->values(),
            'summary' => $summary,
            'filters' => [
                'jenis'      => $fJenis,
                'status'     => $fStatus,
                'asal'       => $fAsal,
                'nama'       => $fNama,
                'tgl_dari'   => $fTglDari,
                'tgl_sampai' => $fTglAkh,
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    private function fetchDokterKolab(array $noRegs): array
    {
        if (empty($noRegs)) return [];
        try {
            $rows = DB::connection('sqlsrv_rsus')
                ->table('ASESMEN_DOKTER_KOLABORASI as adk')
                ->leftJoin('DOKTER as d', 'adk.Dokter', '=', 'd.Kode_Dokter')
                ->where('adk.Ket', '!=', 'Sayhello')
                ->whereIn('adk.No_Reg', $noRegs)
                ->select(['adk.No_Reg', 'd.Nama_Dokter', 'adk.Dokter as Kode_Dokter', 'adk.Ket'])
                ->get();

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
            Log::warning('[MenuYanmedController::fetchDokterKolab] ' . $e->getMessage());
            return [];
        }
    }
}
