<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MKelas;
use App\Models\MRuangMaster;
use App\Models\Pendaftaran;
use App\Models\RegistrasiPasien;
use App\Models\StatusKamar;use App\Services\Icu\SpriInternalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpriInternalController extends Controller
{
    public function __construct(
        private readonly SpriInternalService $service
    ) {}

    public function index(): Response
    {
        $spriList = IcuSpriInternal::with(['pasien', 'pendaftaran', 'bed.ruang.kelas'])
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

    // Lookup AJAX: cari pasien + data klinis

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

        try {
            if (RegistrasiPasien::rsusAvailable()) {
                try {
                    $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        // Kode_Ruang di PENDAFTARAN → M_RUANG_MASTER.Kode_RuangM → Nama_RuangM
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        // Kode_Dokter → DOKTER.Kode_Dokter → Nama_Dokter
                        ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                        // Diagnosis dari ASESMEN_SURAT_PERMINTAAN_RI per No_Reg
                        ->leftJoin(
                            \Illuminate\Support\Facades\DB::raw(
                                '(SELECT No_Reg, MAX(Diagnosis) as Diagnosis
                                  FROM ASESMEN_SURAT_PERMINTAAN_RI
                                  GROUP BY No_Reg) as asmt'
                            ),
                            'p.No_Reg', '=', 'asmt.No_Reg'
                        )
                        ->where('p.No_MR', $noMr)
                        ->orderByDesc('p.Tanggal')
                        ->select([
                            'p.No_Reg',
                            'p.Kode_Masuk',
                            'p.Kode_Ruang',
                            'p.PermintaanDPJP',
                            'p.Kode_Dokter',
                            \Illuminate\Support\Facades\DB::raw(
                                "ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"
                            ),
                            \Illuminate\Support\Facades\DB::raw(
                                "ISNULL(d.Nama_Dokter, p.PermintaanDPJP) as nama_dokter"
                            ),
                            'asmt.Diagnosis',
                        ])
                        ->get();
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('[lookupPasien] join DOKTER failed: ' . $e->getMessage());
                    $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        ->leftJoin(
                            \Illuminate\Support\Facades\DB::raw(
                                '(SELECT No_Reg, MAX(Diagnosis) as Diagnosis
                                  FROM ASESMEN_SURAT_PERMINTAAN_RI
                                  GROUP BY No_Reg) as asmt'
                            ),
                            'p.No_Reg', '=', 'asmt.No_Reg'
                        )
                        ->where('p.No_MR', $noMr)
                        ->orderByDesc('p.Tanggal')
                        ->select([
                            'p.No_Reg',
                            'p.Kode_Masuk',
                            'p.Kode_Ruang',
                            'p.PermintaanDPJP',
                            'p.Kode_Dokter',
                            \Illuminate\Support\Facades\DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                            'p.PermintaanDPJP as nama_dokter',
                            'asmt.Diagnosis',
                        ])
                        ->get();
                }

                $kunjungans = $rows->map(fn($r) => [
                    'No_Reg'      => $r->No_Reg,
                    'label'       => $r->No_Reg . ' — ' . ($r->Kode_Masuk ?? '-'),
                    'Dokter'      => trim($r->nama_dokter    ?? $r->PermintaanDPJP ?? ''),
                    'Kode_Dokter' => trim($r->Kode_Dokter   ?? ''),
                    'asal_ruang'  => trim($r->nama_asal_ruang ?? $r->Kode_Ruang ?? ''),
                    'Diagnosis'   => trim($r->Diagnosis     ?? ''),
                ]);

            } else {
                // ── MySQL lokal fallback ───────────────────────────────────
                $rows = \Illuminate\Support\Facades\DB::connection('mysql')
                    ->table('pendaftaran as p')
                    ->leftJoin('m_ruang_master as rm', 'p.Kode_Asal', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)
                    ->orderByDesc('p.created_at')
                    ->select([
                        'p.No_Reg',
                        'p.Kode_Masuk',
                        'p.Kode_Asal',
                        \Illuminate\Support\Facades\DB::raw("COALESCE(p.PermintaanDPJP, '') as Dokter"),
                        \Illuminate\Support\Facades\DB::raw("COALESCE(p.Kode_Dokter, '') as Kode_Dokter"),
                        \Illuminate\Support\Facades\DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal, '') as nama_asal_ruang"),
                        \Illuminate\Support\Facades\DB::raw("NULL as Diagnosis"),
                    ])
                    ->get();

                $kunjungans = $rows->map(fn($r) => [
                    'No_Reg'      => $r->No_Reg,
                    'label'       => $r->No_Reg . ' — ' . ($r->Kode_Masuk ?? '-'),
                    'Dokter'      => $r->Dokter          ?? '',
                    'Kode_Dokter' => $r->Kode_Dokter     ?? '',
                    'asal_ruang'  => $r->nama_asal_ruang ?? '',
                    'Diagnosis'   => '',
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[lookupPasien] ' . $e->getMessage());
            $kunjungans = collect();
        }

        // Prefill dari SPRI terakhir pasien ini (MySQL lokal)
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

    // Buat SPRI
    public function store(Request $request): RedirectResponse
    {
        // Gunakan connection dan tabel yang sesuai (sqlsrv_rsus atau mysql lokal)
        $connPasien = RegistrasiPasien::connectionName() . '.' . RegistrasiPasien::tableName('REGISTER_PASIEN', 'registrasi_pasien');
        $connReg    = Pendaftaran::connectionName()      . '.' . Pendaftaran::tableName('PENDAFTARAN', 'pendaftaran');

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
            'kebutuhan_bed' => null,   // diisi ICU saat booking bed
            'NameUser'      => auth()->user()?->name ?? 'petugas',
        ]);

        return back()->with('success', "SPRI untuk {$spri->pasien?->Nama_Pasien} berhasil dibuat.");
    }

    // Admisi

    public function approveAdmisi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'catatan_admisi' => 'nullable|string|max:500',
        ]);

        $spri = $this->service->approveAdmisi(
            id:             $id,
            approvedBy:     auth()->user()?->name ?? 'admisi',
            catatanAdmisi:  $validated['catatan_admisi'] ?? null,
        );

        return back()->with('success', "SPRI {$spri->pasien?->Nama_Pasien} disetujui. Diteruskan ke Petugas ICU.");
    }

    public function tolakAdmisi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakAdmisi($id, $validated['alasan_tolak'], auth()->user()?->name ?? 'admisi');

        return back()->with('success', 'Surat Permintaan ditolak.');
    }

    // Petugas ICU

    public function bookingBedIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'Kode_Ruang'    => 'required|exists:status_kamar,Kode_Ruang',
            'kebutuhan_bed' => 'required|exists:m_kelas,Nama_Kelas',   // ICU tentukan jenis bed
        ]);

        $spri    = $this->service->bookingBedIcu($id, $validated['Kode_Ruang'], $validated['kebutuhan_bed'], auth()->user()?->name ?? 'icu');
        $namaBed = $spri->bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];
        $nama    = $spri->pasien?->Nama_Pasien ?? '-';

        return back()->with('success', "Bed {$namaBed} ({$validated['kebutuhan_bed']}) dipesan untuk {$nama}. Pasien siap diantar.");
    }

    public function catatTanpaBed(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'catatan' => 'nullable|string|max:500',
        ]);

        $this->service->catatTanpaBed($id, $validated['catatan'] ?? 'Belum ada bed tersedia.', auth()->user()?->name ?? 'icu');

        return back()->with('success', 'Pasien tetap di daftar tunggu ICU.');
    }

    public function tolakIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakIcu($id, $validated['alasan_tolak'], auth()->user()?->name ?? 'icu');

        return back()->with('success', 'Permintaan bed ditolak oleh ICU.');
    }

    public function konfirmasiMasuk(int $id): RedirectResponse
    {
        $spri    = $this->service->konfirmasiMasuk($id);
        $nama    = $spri->pasien?->Nama_Pasien ?? '-';
        $namaBed = $spri->bed?->ruang?->Nama_RuangM ?? '-';

        return back()->with('success', "Pasien {$nama} masuk ke {$namaBed}. Bed terisi.");
    }

    // Petugas ICU

    public function pulangkan(int $id): RedirectResponse
    {
        $spri = $this->service->pulangkan($id);

        return back()->with('success', "Pasien {$spri->pasien?->Nama_Pasien} dipulangkan. Bed kembali kosong.");
    }

    private function format(IcuSpriInternal $s): array
    {
        return [
            'id'               => $s->id,
            'No_MR'            => $s->No_MR,
            'No_Reg'           => $s->No_Reg,
            'nama_pasien'      => $s->pasien?->Nama_Pasien ?? '-',
            'jenis_kelamin'    => $s->pasien?->jenis_kelamin ?? null,   // dari registrasi_pasien
            'Diagnosis'        => $s->Diagnosis,
            'IndikasiRI'       => $s->IndikasiRI,
            'kebutuhan_bed'    => $s->kebutuhan_bed,
            'asal_ruang'       => $s->asal_ruang,
            'Dokter'           => $s->Dokter,
            'spesialis'        => $s->spesialis,
            'Keterangan'       => $s->Keterangan,
            'catatan_admisi'   => $s->catatan_admisi,
            'allocated_bed_id' => $s->allocated_bed_id,
            'nama_bed'         => $s->bed?->ruang?->Nama_RuangM ?? null,
            'status'           => $s->status,
            'status_label'     => $s->statusLabel(),
            'alasan_tolak'     => $s->alasan_tolak,
            'created_at'       => $s->created_at?->format('d/m/Y H:i'),
        ];
    }
}
