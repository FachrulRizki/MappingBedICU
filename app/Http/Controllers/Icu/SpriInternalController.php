<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MKelas;
use App\Models\StatusKamar;
use App\Models\Pendaftaran;
use App\Services\Icu\SpriInternalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpriInternalController extends Controller
{
    public function __construct(
        private readonly SpriInternalService $service
    ) {}

    /** Halaman daftar semua Surat Permintaan Rawat ICU internal */
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

    /**
     * Petugas ruang asal buat Surat Permintaan Rawat ICU.
     * No_MR & No_Reg sudah ada (dari sistem existing).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'         => 'required|exists:registrasi_pasien,No_MR',
            'No_Reg'        => 'required|exists:pendaftaran,No_Reg',
            'Diagnosis'     => 'required|string|max:255',
            'IndikasiRI'    => 'required|string|max:255',
            'kebutuhan_bed' => 'required|exists:m_kelas,Nama_Kelas',
            'asal_ruang'    => 'nullable|string|max:100',
            'Dokter'        => 'nullable|string|max:100',
            'spesialis'     => 'nullable|string|max:100',
            'Keterangan'    => 'nullable|string|max:500',
        ]);

        $spri = $this->service->buatSpri([
            ...$validated,
            'NameUser' => session('user_name', 'petugas'),
        ]);

        // Ambil nama pasien untuk pesan sukses
        $namaPasien = $spri->pasien?->Nama_Pasien ?? $validated['No_MR'];

        return back()->with('success', "Surat Permintaan Rawat ICU untuk {$namaPasien} berhasil dibuat.");
    }

    /** Admisi approve surat permintaan */
    public function approveAdmisi(int $id): RedirectResponse
    {
        $spri = $this->service->approveAdmisi($id, session('user_name', 'admisi'));
        $nama = $spri->pasien?->Nama_Pasien ?? '-';

        return back()->with('success', "Surat Permintaan {$nama} disetujui. Diteruskan ke ICU untuk booking bed.");
    }

    /** Admisi tolak */
    public function tolakAdmisi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakAdmisi($id, $validated['alasan_tolak'], session('user_name', 'admisi'));

        return back()->with('success', 'Surat Permintaan ditolak.');
    }

    /** ICU booking bed */
    public function bookingBedIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'Kode_Ruang' => 'required|exists:status_kamar,Kode_Ruang',
        ]);

        $spri    = $this->service->bookingBedIcu($id, $validated['Kode_Ruang'], session('user_name', 'icu'));
        $namaBed = $spri->bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];
        $nama    = $spri->pasien?->Nama_Pasien ?? '-';

        return back()->with('success', "Bed {$namaBed} dipesan untuk {$nama}. Menunggu verifikasi Admisi.");
    }

    /** ICU tolak booking */
    public function tolakIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakIcu($id, $validated['alasan_tolak'], session('user_name', 'icu'));

        return back()->with('success', 'Permintaan bed ditolak.');
    }

    /** Admisi verifikasi akhir */
    public function verifikasiAdmisi(int $id): RedirectResponse
    {
        $spri = $this->service->verifikasiAdmisi($id, session('user_name', 'admisi'));
        $nama = $spri->pasien?->Nama_Pasien ?? '-';

        return back()->with('success', "Pasien {$nama} siap diantar ke ICU.");
    }

    /** ICU konfirmasi pasien masuk ruangan */
    public function konfirmasiMasuk(int $id): RedirectResponse
    {
        $spri    = $this->service->konfirmasiMasuk($id);
        $nama    = $spri->pasien?->Nama_Pasien ?? '-';
        $namaBed = $spri->bed?->ruang?->Nama_RuangM ?? '-';

        return back()->with('success', "Pasien {$nama} masuk ke {$namaBed}. Bed terisi.");
    }

    /** Pulangkan pasien dari ICU */
    public function pulangkan(int $id): RedirectResponse
    {
        $spri = $this->service->pulangkan($id);
        $nama = $spri->pasien?->Nama_Pasien ?? '-';

        return back()->with('success', "Pasien {$nama} dipulangkan. Bed kembali kosong.");
    }

    /** Format spri untuk Vue */
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
            'allocated_bed_id' => $s->allocated_bed_id,
            'nama_bed'         => $s->bed?->ruang?->Nama_RuangM ?? null,
            'status'           => $s->status,
            'status_label'     => $s->statusLabel(),
            'alasan_tolak'     => $s->alasan_tolak,
            'created_at'       => $s->created_at?->format('d/m/Y H:i'),
        ];
    }
}
