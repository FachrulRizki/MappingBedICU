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

    private function currentUser(): string
    {
        return auth()->user()?->name ?? 'petugas';
    }

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

    // ── Admisi: buat booking request + form jaminan ──────────────────────
    // Langsung masuk pending_icu, admisi tidak menentukan bed

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
            // kebutuhan_bed TIDAK diisi admisi — ICU yang tentukan saat konfirmasi bed
            'jaminan'          => 'required|in:BPJS,Umum,Asuransi,Lainnya',
            'catatan_jaminan'  => 'nullable|string|max:500',
            'keterangan'       => 'nullable|string|max:500',
        ]);

        $booking = $this->service->buatBooking([
            ...$validated,
            'kebutuhan_bed' => null,   // diisi ICU saat konfirmasi bed
            'created_by'    => $this->currentUser(),
        ]);

        return back()->with('success', "Booking untuk {$booking->nama_pasien} berhasil dikirim ke ICU.");
    }

    // ── Petugas ICU: pilih & konfirmasi bed ─────────────────────────────
    // ICU satu-satunya penentu bed, langsung bed_confirmed

    public function konfirmasiIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'Kode_Ruang'    => 'required|exists:status_kamar,Kode_Ruang',
            'kebutuhan_bed' => 'required|exists:m_kelas,Nama_Kelas',   // ICU tentukan jenis
        ]);

        $booking = $this->service->konfirmasiIcu($id, $validated['Kode_Ruang'], $validated['kebutuhan_bed'], $this->currentUser());
        $namaBed = $booking->bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];

        return back()->with('success', "Bed {$namaBed} ({$validated['kebutuhan_bed']}) dikonfirmasi untuk {$booking->nama_pasien}. Pasien siap diantar.");
    }

    /**
     * ICU catat bahwa belum ada bed — pasien tetap di waiting list.
     * Tidak menolak, tidak memblok. Status tetap pending_icu.
     */
    public function catatTanpaBed(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'catatan' => 'nullable|string|max:500',
        ]);

        $this->service->catatTanpaBed($id, $validated['catatan'] ?? 'Belum ada bed tersedia.', $this->currentUser());

        return back()->with('success', 'Pasien tetap di daftar tunggu ICU.');
    }

    public function tolakIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $this->service->tolakIcu($id, $validated['alasan_tolak'], $this->currentUser());

        return back()->with('success', 'Booking ditolak oleh ICU.');
    }

    // ── Petugas ICU: konfirmasi pasien masuk — LANGSUNG di_icu ──────────
    // (skip validasi admisi — sesuai alur baru)

    public function konfirmasiMasuk(Request $request, int $id): RedirectResponse
    {
        // No_MR opsional — diisi jika pasien sudah terdaftar di sistem RS
        $validated = $request->validate([
            'No_MR'  => 'nullable|exists:registrasi_pasien,No_MR',
            'No_Reg' => 'nullable|exists:pendaftaran,No_Reg',
        ]);

        $booking = $this->service->konfirmasiMasuk(
            id:    $id,
            noMr:  $validated['No_MR']  ?? null,
            noReg: $validated['No_Reg'] ?? null,
        );

        $namaBed = $booking->bed?->ruang?->Nama_RuangM ?? '-';

        return back()->with('success', "Pasien {$booking->nama_pasien} masuk ke {$namaBed}. Bed terisi.");
    }

    // ── Petugas ICU: pulangkan ───────────────────────────────────────────

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
            'jaminan'          => $b->jaminan,
            'catatan_jaminan'  => $b->catatan_jaminan,
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
