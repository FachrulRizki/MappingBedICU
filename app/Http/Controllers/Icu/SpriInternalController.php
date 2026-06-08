<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MKelas;
use App\Models\Pendaftaran;
use App\Models\RegistrasiPasien;
use App\Models\StatusKamar;
use App\Services\Icu\SpriInternalService;
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

        $kamarKosong = StatusKamar::with('ruang.kelas')
            ->where('Status', 'KOSONG')
            ->get()
            ->map(fn($k) => [
                'Kode_Ruang' => $k->Kode_Ruang,
                'nama_ruang' => $k->ruang?->Nama_RuangM ?? $k->Kode_Ruang,
                'kode_kelas' => $k->ruang?->Kode_Kelas ?? null,
                'nama_kelas' => $k->ruang?->kelas?->Nama_Kelas ?? null,
            ]);

        return Inertia::render('Icu/SpriInternal', [
            'spriList'    => $spriList,
            'kamarKosong' => $kamarKosong,
            'masterKelas' => MKelas::all()->map(fn($k) => ['kode' => $k->Kode_Kelas, 'nama' => $k->Nama_Kelas]),
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ── Lookup AJAX: cari pasien berdasarkan No_MR ───────────────────────

    public function lookupPasien(Request $request): JsonResponse
    {
        $noMr = trim($request->query('No_MR', ''));
        if (! $noMr) {
            return response()->json(['found' => false, 'message' => 'No_MR kosong.']);
        }

        $pasien = RegistrasiPasien::where('No_MR', $noMr)->first();
        if (! $pasien) {
            return response()->json(['found' => false, 'message' => "Pasien dengan No_MR '{$noMr}' tidak ditemukan."]);
        }

        // Ambil kunjungan aktif milik pasien ini
        $kunjungans = Pendaftaran::where('No_MR', $noMr)
            ->latest()
            ->get(['No_Reg', 'Kode_Masuk', 'PermintaanDPJP', 'created_at'])
            ->map(fn($r) => [
                'No_Reg'       => $r->No_Reg,
                'label'        => $r->No_Reg . ' — ' . ($r->Kode_Masuk ?? '-'),
                'Dokter'       => $r->PermintaanDPJP ?? '',
            ]);

        return response()->json([
            'found'       => true,
            'No_MR'       => $pasien->No_MR,
            'nama_pasien' => $pasien->Nama_Pasien,
            'kunjungans'  => $kunjungans,
        ]);
    }

    // ── Petugas Ruang: buat SPRI ─────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'      => 'required|exists:registrasi_pasien,No_MR',
            'No_Reg'     => 'required|exists:pendaftaran,No_Reg',
            'Diagnosis'  => 'required|string|max:255',
            'IndikasiRI' => 'required|string|max:255',
            // kebutuhan_bed TIDAK diisi di sini — ICU yang tentukan saat booking bed
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

    // ── Admisi: approve + isi catatan (TIDAK menentukan bed) ─────────────

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

    // ── Petugas ICU: pilih bed ───────────────────────────────────────────

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

    /**
     * ICU catat bahwa belum ada bed — pasien tetap di waiting list.
     * Tidak memblok alur, tidak menolak pasien.
     */
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

    // ── Petugas ICU: konfirmasi pasien masuk — LANGSUNG di_icu ──────────
    // (skip verifikasi admisi — sesuai alur baru)

    public function konfirmasiMasuk(int $id): RedirectResponse
    {
        $spri    = $this->service->konfirmasiMasuk($id);
        $nama    = $spri->pasien?->Nama_Pasien ?? '-';
        $namaBed = $spri->bed?->ruang?->Nama_RuangM ?? '-';

        return back()->with('success', "Pasien {$nama} masuk ke {$namaBed}. Bed terisi.");
    }

    // ── Petugas ICU: pulangkan ───────────────────────────────────────────

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
