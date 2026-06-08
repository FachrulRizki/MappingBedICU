<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\MKelas;
use App\Models\StatusKamar;
use App\Services\Icu\BookingExternalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingExternalController extends Controller
{
    public function __construct(
        private readonly BookingExternalService $service
    ) {}

    /** Helper — nama user aktif (belum auth, pakai session/default) */
    private function currentUser(): string
    {
        return auth()->user()?->name ?? 'petugas';
    }

    /** Halaman daftar semua booking external */
    public function index(): Response
    {
        $bookings = IcuBookingExternal::with(['bed.ruang.kelas', 'pasien'])
            ->latest()
            ->get()
            ->map(fn($b) => $this->format($b));

        $kamarKosong = StatusKamar::with('ruang.kelas')
            ->where('Status', 'KOSONG')
            ->get()
            ->map(fn($k) => [
                'Kode_Ruang' => $k->Kode_Ruang,
                'nama_ruang' => $k->ruang?->Nama_RuangM ?? $k->Kode_Ruang,
                'kode_kelas' => $k->ruang?->Kode_Kelas ?? null,
                'nama_kelas' => $k->ruang?->kelas?->Nama_Kelas ?? null,
            ]);

        return Inertia::render('Icu/BookingExternal', [
            'bookings'    => $bookings,
            'kamarKosong' => $kamarKosong,
            'masterKelas' => MKelas::all()->map(fn($k) => ['kode' => $k->Kode_Kelas, 'nama' => $k->Nama_Kelas]),
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /** Admisi buat booking request baru → langsung pending_icu */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_pasien'      => 'required|string|max:150',
            'jenis_kelamin'    => 'required|in:L,P',
            'no_identitas'     => 'nullable|string|max:30',
            'asal_rujukan'     => 'nullable|string|max:150',
            'no_telp_keluarga' => 'nullable|string|max:20',
            'diagnosa'         => 'required|string|max:255',
            'rencana_tindakan' => 'required|string|max:255',
            'kebutuhan_bed'    => 'required|exists:m_kelas,Nama_Kelas',
            'keterangan'       => 'nullable|string|max:500',
        ]);

        IcuBookingExternal::create([
            ...$validated,
            'status'     => 'pending_icu',
            'created_by' => $this->currentUser(),
        ]);

        return back()->with('success', "Booking untuk {$validated['nama_pasien']} berhasil dibuat dan dikirim ke ICU.");
    }

    /** ICU konfirmasi ada bed */
    public function konfirmasiIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'Kode_Ruang' => 'required|exists:status_kamar,Kode_Ruang',
        ]);

        $booking = $this->service->konfirmasiIcu($id, $validated['Kode_Ruang'], $this->currentUser());
        $namaBed = $booking->bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];

        return back()->with('success', "Bed {$namaBed} dikonfirmasi untuk {$booking->nama_pasien}.");
    }

    /** ICU tolak */
    public function tolakIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakIcu($id, $validated['alasan_tolak'], $this->currentUser());

        return back()->with('success', 'Booking ditolak.');
    }

    /** Admisi validasi konfirmasi ICU → pasien dalam perjalanan */
    public function validasiAdmisi(int $id): RedirectResponse
    {
        $booking = $this->service->validasiAdmisi($id, $this->currentUser());

        return back()->with('success', "Booking {$booking->nama_pasien} divalidasi. Pasien dapat diarahkan ke RS.");
    }

    /** Pasien tiba di IGD — Admisi link No_MR */
    public function linkPasienTiba(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'  => 'required|exists:registrasi_pasien,No_MR',
            'No_Reg' => 'required|exists:pendaftaran,No_Reg',
        ]);

        $booking = $this->service->linkPasienTiba($id, $validated['No_MR'], $validated['No_Reg']);

        return back()->with('success', "Pasien {$booking->nama_pasien} tiba — terhubung ke No_MR {$validated['No_MR']}.");
    }

    /** Admisi verifikasi bed setelah pasien tiba */
    public function verifikasiBed(int $id): RedirectResponse
    {
        $booking = $this->service->verifikasiBed($id, $this->currentUser());

        return back()->with('success', "Bed diverifikasi. Pasien {$booking->nama_pasien} siap diantar ke ICU.");
    }

    /** ICU konfirmasi pasien masuk ruangan */
    public function konfirmasiMasuk(int $id): RedirectResponse
    {
        $booking = $this->service->konfirmasiMasuk($id);
        $namaBed = $booking->bed?->ruang?->Nama_RuangM ?? '-';

        return back()->with('success', "Pasien {$booking->nama_pasien} masuk ke {$namaBed}. Bed terisi.");
    }

    /** Pulangkan pasien (dari ICU) */
    public function pulangkan(int $id): RedirectResponse
    {
        $booking = $this->service->pulangkan($id);

        return back()->with('success', "Pasien {$booking->nama_pasien} dipulangkan. Bed kembali kosong.");
    }

    private function format(IcuBookingExternal $b): array
    {
        return [
            'id'               => $b->id,
            'nama_pasien'      => $b->nama_pasien,
            'jenis_kelamin'    => $b->jenis_kelamin,
            'no_identitas'     => $b->no_identitas,
            'asal_rujukan'     => $b->asal_rujukan,
            'no_telp_keluarga' => $b->no_telp_keluarga,
            'diagnosa'         => $b->diagnosa,
            'rencana_tindakan' => $b->rencana_tindakan,
            'kebutuhan_bed'    => $b->kebutuhan_bed,
            'keterangan'       => $b->keterangan,
            'No_MR'            => $b->No_MR,
            'No_Reg'           => $b->No_Reg,
            'allocated_bed_id' => $b->allocated_bed_id,
            'nama_bed'         => $b->bed?->ruang?->Nama_RuangM ?? null,
            'status'           => $b->status,
            'status_label'     => $b->statusLabel(),
            'alasan_tolak'     => $b->alasan_tolak,
            'created_at'       => $b->created_at?->format('d/m/Y H:i'),
        ];
    }
}
