<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MenuPetugasController extends Controller
{
    private function actor(): string
    {
        return auth()->user()?->name ?? 'petugas_ruang';
    }

    // ── READ ──────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $fNama   = trim($request->query('nama', ''));
        $fTgl    = $request->query('tgl', '');
        $fStatus = $request->query('status', '');

        $q = IcuSpriInternal::query()->where('NameUser', $this->actor());

        if ($fStatus) $q->where('status', $fStatus);

        if ($fNama) {
            $pasienIds = RegistrasiPasien::query()
                ->where('Nama_Pasien', 'like', "%{$fNama}%")
                ->pluck('No_MR')->toArray();

            $q->where(function ($qq) use ($fNama, $pasienIds) {
                $qq->whereIn('No_MR', $pasienIds)
                   ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }

        if ($fTgl) $q->whereDate('created_at', $fTgl);

        $data = $q->latest()->get();

        $pasienMap = RegistrasiPasien::whereIn('No_MR', $data->pluck('No_MR'))
            ->get()->keyBy('No_MR');

        $spriList = $data->map(fn($s) => $this->format($s, $pasienMap));

        $summary = [
            'total'        => $spriList->count(),
            'pending'      => $spriList->filter(fn($i) => in_array($i['status'], ['pending_admisi', 'pending_icu']))->count(),
            'bed_verified' => $spriList->filter(fn($i) => $i['status'] === 'bed_verified')->count(),
            'ditolak'      => $spriList->filter(fn($i) => $i['status'] === 'ditolak')->count(),
        ];

        return Inertia::render('Icu/MenuPetugas', [
            'spriList'    => $spriList,
            'summary'     => $summary,
            'filters'     => compact('fNama', 'fTgl', 'fStatus'),
            'kamarKosong' => MRuangMaster::bedKosong(),
            'masterKelas' => MRuangMaster::jenisIcuTersedia(),
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ── CREATE ────────────────────────────────────────────────────────────

    public function storeSpri(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'      => 'required|string|max:20',
            'No_Reg'     => 'required|string|max:20',
            'Diagnosis'  => 'required|string|max:255',
            'IndikasiRI' => 'required|string|max:255',
            'asal_ruang' => 'nullable|string|max:100',
            'Dokter'     => 'nullable|string|max:100',
            'spesialis'  => 'nullable|string|max:100',
            'Keterangan' => 'nullable|string|max:500',
        ]);

        $spri = IcuSpriInternal::create([
            ...$validated,
            'NameUser' => $this->actor(),
            'status'   => 'pending_admisi',
        ]);

        return back()->with('success', "SPRI untuk No. MR {$spri->No_MR} berhasil dibuat.");
    }

    // ── AJAX — Lookup pasien dari DB RS ───────────────────────────────────

    public function lookupPasien(Request $request): JsonResponse
    {
        $noMr = trim($request->query('No_MR', ''));
        if (! $noMr) {
            return response()->json(['found' => false, 'message' => 'No_MR kosong.']);
        }

        try {
            $pasien = RegistrasiPasien::where('No_MR', $noMr)->first();
        } catch (\Exception $e) {
            return response()->json([
                'found'   => false,
                'message' => 'Tidak dapat terhubung ke database RS. ' .
                    (app()->hasDebugModeEnabled() ? $e->getMessage() : 'Hubungi administrator.'),
            ]);
        }

        if (! $pasien) {
            return response()->json([
                'found'   => false,
                'message' => "Pasien dengan No_MR '{$noMr}' tidak ditemukan.",
            ]);
        }

        $kunjungans = collect();

        try {
            if (RegistrasiPasien::rsusAvailable()) {
                try {
                    $rows = DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                        ->leftJoin(
                            DB::raw('(SELECT No_Reg, MAX(Diagnosis) as Diagnosis
                                      FROM ASESMEN_SURAT_PERMINTAAN_RI GROUP BY No_Reg) as asmt'),
                            'p.No_Reg', '=', 'asmt.No_Reg'
                        )
                        ->where('p.No_MR', $noMr)->orderByDesc('p.Tanggal')
                        ->select([
                            'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                            'p.PermintaanDPJP', 'p.Kode_Dokter',
                            DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                            DB::raw("ISNULL(d.Nama_Dokter, p.PermintaanDPJP) as nama_dokter"),
                            'asmt.Diagnosis',
                        ])->get();
                } catch (\Exception $e) {
                    Log::warning('[lookupPasien] join DOKTER failed: ' . $e->getMessage());
                    $rows = DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        ->where('p.No_MR', $noMr)->orderByDesc('p.Tanggal')
                        ->select([
                            'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                            'p.PermintaanDPJP', 'p.Kode_Dokter',
                            DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                            'p.PermintaanDPJP as nama_dokter',
                            DB::raw("NULL as Diagnosis"),
                        ])->get();
                }
                $kunjungans = $rows->map(fn($r) => [
                    'No_Reg'      => $r->No_Reg,
                    'Dokter'      => trim($r->nama_dokter    ?? $r->PermintaanDPJP ?? ''),
                    'Kode_Dokter' => trim($r->Kode_Dokter   ?? ''),
                    'asal_ruang'  => trim($r->nama_asal_ruang ?? $r->Kode_Ruang ?? ''),
                    'Diagnosis'   => trim($r->Diagnosis      ?? ''),
                ]);
            } else {
                $rows = DB::connection('mysql')
                    ->table('pendaftaran as p')
                    ->leftJoin('m_ruang_master as rm', 'p.Kode_Asal', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)->orderByDesc('p.created_at')
                    ->select([
                        'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Asal',
                        DB::raw("COALESCE(p.PermintaanDPJP,'') as Dokter"),
                        DB::raw("COALESCE(p.Kode_Dokter,'') as Kode_Dokter"),
                        DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal,'') as nama_asal_ruang"),
                        DB::raw("NULL as Diagnosis"),
                    ])->get();
                $kunjungans = $rows->map(fn($r) => [
                    'No_Reg'      => $r->No_Reg,
                    'Dokter'      => $r->Dokter          ?? '',
                    'Kode_Dokter' => $r->Kode_Dokter     ?? '',
                    'asal_ruang'  => $r->nama_asal_ruang ?? '',
                    'Diagnosis'   => '',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[lookupPasien] ' . $e->getMessage());
        }

        $spriTerakhir = IcuSpriInternal::where('No_MR', $noMr)
            ->latest()->first(['Diagnosis', 'IndikasiRI', 'asal_ruang', 'Dokter']);

        return response()->json([
            'found'       => true,
            'No_MR'       => $pasien->No_MR,
            'nama_pasien' => $pasien->Nama_Pasien,
            'kunjungans'  => $kunjungans,
            'prefill'     => $spriTerakhir ? [
                'Diagnosis'  => $spriTerakhir->Diagnosis,
                'IndikasiRI' => $spriTerakhir->IndikasiRI,
                'asal_ruang' => $spriTerakhir->asal_ruang,
                'Dokter'     => $spriTerakhir->Dokter,
            ] : null,
        ]);
    }

    // ── Formatter ─────────────────────────────────────────────────────────

    private function format(IcuSpriInternal $s, $pasienMap = null): array
    {
        $pasien = $pasienMap ? ($pasienMap[$s->No_MR] ?? null) : $s->pasien;
        return [
            'id'             => $s->id,
            'No_MR'          => $s->No_MR,
            'No_Reg'         => $s->No_Reg,
            'nama_pasien'    => $pasien?->Nama_Pasien ?? '-',
            'jenis_kelamin'  => $pasien?->jenis_kelamin ?? null,
            'Diagnosis'      => $s->Diagnosis,
            'IndikasiRI'     => $s->IndikasiRI,
            'kebutuhan_bed'  => $s->kebutuhan_bed,
            'asal_ruang'     => $s->asal_ruang,
            'Dokter'         => $s->Dokter,
            'spesialis'      => $s->spesialis,
            'Keterangan'     => $s->Keterangan,
            'catatan_admisi' => $s->catatan_admisi,
            'nama_bed'       => $s->nama_bed,
            'status'         => $s->status,
            'status_label'   => $s->statusLabel(),
            'alasan_tolak'   => $s->alasan_tolak,
            'approved_by'    => $s->approved_by,
            'verified_by'    => $s->verified_by,
            'created_at'     => $s->created_at?->format('Y-m-d H:i'),
            'created_at_fmt' => $s->created_at?->format('d/m/Y H:i'),
        ];
    }
}
