<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MKelas;
use App\Models\MRuangMaster;
use App\Models\Pendaftaran;
use App\Models\RegistrasiPasien;
use App\Models\StatusKamar;
use App\Services\Icu\SpriInternalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SpriInternalController extends Controller
{
    public function __construct(
        private readonly SpriInternalService $service
    ) {}

    public function index(): Response
    {
        $spriList = IcuSpriInternal::with(['pasien', 'pendaftaran'])
            ->latest()
            ->get()
            ->map(fn($s) => $this->format($s));

        $kamarKosong = MRuangMaster::bedKosong();
        $masterKelas = MRuangMaster::jenisIcuTersedia();

        return Inertia::render('Icu/SpriInternal', [
            'spriList'    => $spriList,
            'kamarKosong' => $kamarKosong,
            'masterKelas' => $masterKelas,
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ── Lookup pasien ─────────────────────────────────────────────────────

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
                                      FROM ASESMEN_SURAT_PERMINTAAN_RI
                                      GROUP BY No_Reg) as asmt'),
                            'p.No_Reg', '=', 'asmt.No_Reg'
                        )
                        ->where('p.No_MR', $noMr)
                        ->orderByDesc('p.Tanggal')
                        ->select([
                            'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                            'p.PermintaanDPJP', 'p.Kode_Dokter',
                            DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                            DB::raw("ISNULL(d.Nama_Dokter, p.PermintaanDPJP) as nama_dokter"),
                            'asmt.Diagnosis',
                        ])
                        ->get();
                } catch (\Exception $e) {
                    Log::warning('[lookupPasien] join DOKTER failed: ' . $e->getMessage());
                    $rows = DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        ->leftJoin(
                            DB::raw('(SELECT No_Reg, MAX(Diagnosis) as Diagnosis
                                      FROM ASESMEN_SURAT_PERMINTAAN_RI
                                      GROUP BY No_Reg) as asmt'),
                            'p.No_Reg', '=', 'asmt.No_Reg'
                        )
                        ->where('p.No_MR', $noMr)
                        ->orderByDesc('p.Tanggal')
                        ->select([
                            'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                            'p.PermintaanDPJP', 'p.Kode_Dokter',
                            DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                            'p.PermintaanDPJP as nama_dokter',
                            'asmt.Diagnosis',
                        ])
                        ->get();
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
                    ->where('p.No_MR', $noMr)
                    ->orderByDesc('p.created_at')
                    ->select([
                        'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Asal',
                        DB::raw("COALESCE(p.PermintaanDPJP, '') as Dokter"),
                        DB::raw("COALESCE(p.Kode_Dokter, '') as Kode_Dokter"),
                        DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal, '') as nama_asal_ruang"),
                        DB::raw("NULL as Diagnosis"),
                    ])
                    ->get();

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

    // ── Petugas ruang — buat SPRI ─────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $connPasien = RegistrasiPasien::connectionName() . '.' . RegistrasiPasien::tableName('REGISTER_PASIEN', 'registrasi_pasien');
        $connReg    = Pendaftaran::connectionName() . '.' . Pendaftaran::tableName('PENDAFTARAN', 'pendaftaran');

        $validated = $request->validate([
            'No_MR'      => "required|exists:{$connPasien},No_MR",
            'No_Reg'     => "required|exists:{$connReg},No_Reg",
            'Diagnosis'  => 'required|string|max:255',
            'IndikasiRI' => 'required|string|max:255',
            'asal_ruang' => 'nullable|string|max:100',
            'Dokter'     => 'nullable|string|max:100',
            'spesialis'  => 'nullable|string|max:100',
            'Keterangan' => 'nullable|string|max:500',
        ]);

        $spri = $this->service->buatSpri([
            ...$validated,
            'NameUser' => auth()->user()?->name ?? 'petugas',
        ]);

        return back()->with('success', "SPRI untuk {$spri->pasien?->Nama_Pasien} berhasil dibuat. Menunggu verifikasi Admisi.");
    }

    // ── Admisi — approve + isi catatan jaminan ────────────────────────────

    public function approveAdmisi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'catatan_admisi' => 'nullable|string|max:500',
        ]);

        $spri = $this->service->approveAdmisi(
            id:            $id,
            approvedBy:    auth()->user()?->name ?? 'admisi',
            catatanAdmisi: $validated['catatan_admisi'] ?? null,
        );

        return back()->with('success', "SPRI {$spri->pasien?->Nama_Pasien} disetujui, diteruskan ke Petugas ICU.");
    }

    // ── Admisi — tolak ────────────────────────────────────────────────────

    public function tolakAdmisi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakAdmisi(
            $id,
            $validated['alasan_tolak'],
            auth()->user()?->name ?? 'admisi'
        );

        return back()->with('success', 'Surat Permintaan ditolak.');
    }

    // ── Petugas ICU — verifikasi bed ──────────────────────────────────────

    public function verifikasiBedIcu(Request $request, int $id): RedirectResponse
    {
        $connKamar = StatusKamar::connectionName() . '.' . StatusKamar::tableName('STATUS_KAMAR', 'status_kamar');
        $connKelas = MKelas::connectionName() . '.' . MKelas::tableName('M_KELAS', 'm_kelas');

        $validated = $request->validate([
            'Kode_Ruang'    => "required|exists:{$connKamar},Kode_Ruang",
            'kebutuhan_bed' => "required|exists:{$connKelas},Nama_Kelas",
        ]);

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $validated['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];

        $spri = $this->service->verifikasiBedIcu(
            id:           $id,
            kodeRuang:    $validated['Kode_Ruang'],
            namaBed:      $namaBed,
            kebutuhanBed: $validated['kebutuhan_bed'],
            verifiedBy:   auth()->user()?->name ?? 'icu',
        );

        return back()->with('success', "Bed {$namaBed} ({$validated['kebutuhan_bed']}) terverifikasi untuk {$spri->pasien?->Nama_Pasien}.");
    }

    // ── Petugas ICU — tolak ────────────────────────────────────────────────

    public function tolakIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakIcu(
            $id,
            $validated['alasan_tolak'],
            auth()->user()?->name ?? 'icu'
        );

        return back()->with('success', 'Permintaan bed ditolak oleh ICU.');
    }

    // ── Formatter ─────────────────────────────────────────────────────────

    private function format(IcuSpriInternal $s): array
    {
        return [
            'id'               => $s->id,
            'No_MR'            => $s->No_MR,
            'No_Reg'           => $s->No_Reg,
            'nama_pasien'      => $s->pasien?->Nama_Pasien ?? '-',
            'jenis_kelamin'    => $s->pasien?->jenis_kelamin ?? null,
            'Diagnosis'        => $s->Diagnosis,
            'IndikasiRI'       => $s->IndikasiRI,
            'kebutuhan_bed'    => $s->kebutuhan_bed,
            'asal_ruang'       => $s->asal_ruang,
            'Dokter'           => $s->Dokter,
            'spesialis'        => $s->spesialis,
            'Keterangan'       => $s->Keterangan,
            'catatan_admisi'   => $s->catatan_admisi,
            'allocated_bed_id' => $s->allocated_bed_id,
            'nama_bed'         => $s->nama_bed,
            'status'           => $s->status,
            'status_label'     => $s->statusLabel(),
            'status_color'     => $s->statusColor(),
            'alasan_tolak'     => $s->alasan_tolak,
            'approved_by'      => $s->approved_by,
            'verified_by'      => $s->verified_by,
            'created_at'       => $s->created_at?->format('d/m/Y H:i'),
        ];
    }
}
