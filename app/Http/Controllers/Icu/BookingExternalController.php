<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\MKelas;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use App\Services\Icu\BookingExternalService;use Illuminate\Http\RedirectResponse;
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

        $kamarKosong = MRuangMaster::bedKosong();
        $masterKelas = MRuangMaster::jenisIcuTersedia();

        return Inertia::render('Icu/BookingExternal', [
            'bookings'    => $bookings,
            'kamarKosong' => $kamarKosong,
            'masterKelas' => $masterKelas,
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // Admisi booking

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

    // Petugas ICU
    public function konfirmasiIcu(Request $request, int $id): RedirectResponse
    {
        $connKelas  = MKelas::connectionName() . '.' . MKelas::tableName('M_KELAS', 'm_kelas');
        $connKamar  = StatusKamar::connectionName() . '.' . StatusKamar::tableName('STATUS_KAMAR', 'status_kamar');

        $validated = $request->validate([
            'Kode_Ruang'    => "required|exists:{$connKamar},Kode_Ruang",
            'kebutuhan_bed' => "required|exists:{$connKelas},Nama_Kelas",
        ]);

        $booking = $this->service->konfirmasiIcu($id, $validated['Kode_Ruang'], $validated['kebutuhan_bed'], $this->currentUser());
        $namaBed = $booking->bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];

        return back()->with('success', "Bed {$namaBed} ({$validated['kebutuhan_bed']}) dikonfirmasi untuk {$booking->nama_pasien}. Pasien siap diantar.");
    }

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

    public function konfirmasiMasuk(Request $request, int $id): RedirectResponse
    {
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
