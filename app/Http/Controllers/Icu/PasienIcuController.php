<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Services\Icu\TransferService;
use App\Services\Icu\PulangService;
use App\Services\Icu\BookingExternalService;
use App\Services\Icu\SpriInternalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasienIcuController extends Controller
{
    public function __construct(
        private readonly TransferService       $transferService,
        private readonly PulangService         $pulangService,
        private readonly BookingExternalService $bookingExService,
        private readonly SpriInternalService    $spriIntService,
    ) {}

    /**
     * Halaman pasien aktif di ICU — gabungan dari 3 jalur:
     * 1. IcuAdmision (jalur lama)
     * 2. IcuBookingExternal (jalur external)
     * 3. IcuSpriInternal (jalur internal)
     */
    public function index(): Response
    {
        // ── Jalur lama ────────────────────────────────────────────
        $with = ['pasien', 'bed', 'bed.ruang', 'bed.ruang.kelas'];
        $pasienLama = IcuAdmision::with($with)
            ->where('status', 'di_icu')
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id'                => 'adm_' . $a->id,
                'sumber'            => 'lama',
                'No_Reg'            => $a->No_Reg,
                'No_MR'             => $a->No_MR,
                'nama_pasien'       => $a->pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin'     => $a->pasien?->jenis_kelamin ?? null,
                'required_bed_type' => $a->required_bed_type,
                'allocated_bed_id'  => $a->allocated_bed_id,
                'nama_bed'          => $a->bed?->ruang?->Nama_RuangM ?? $a->allocated_bed_id,
                'nama_kelas'        => $a->bed?->ruang?->kelas?->Nama_Kelas ?? $a->required_bed_type,
                'status'            => $a->status,
                'raw_id'            => $a->id,
                'created_at'        => $a->created_at?->format('d/m/Y H:i'),
            ]);

        // ── Jalur External ────────────────────────────────────────
        $pasienExternal = IcuBookingExternal::with(['bed.ruang.kelas', 'pasien'])
            ->where('status', 'di_icu')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'                => 'ext_' . $b->id,
                'sumber'            => 'external',
                'No_Reg'            => $b->No_Reg,
                'No_MR'             => $b->No_MR,
                'nama_pasien'       => $b->pasien?->Nama_Pasien ?? $b->nama_pasien,
                'jenis_kelamin'     => $b->pasien?->jenis_kelamin ?? $b->jenis_kelamin,
                'required_bed_type' => $b->kebutuhan_bed,
                'allocated_bed_id'  => $b->allocated_bed_id,
                'nama_bed'          => $b->bed?->ruang?->Nama_RuangM ?? $b->allocated_bed_id,
                'nama_kelas'        => $b->bed?->ruang?->kelas?->Nama_Kelas ?? $b->kebutuhan_bed,
                'status'            => $b->status,
                'raw_id'            => $b->id,
                'created_at'        => $b->created_at?->format('d/m/Y H:i'),
            ]);

        // ── Jalur Internal ────────────────────────────────────────
        $pasienInternal = IcuSpriInternal::with(['pasien', 'bed.ruang.kelas'])
            ->where('status', 'di_icu')
            ->latest()
            ->get()
            ->map(fn($s) => [
                'id'                => 'int_' . $s->id,
                'sumber'            => 'internal',
                'No_Reg'            => $s->No_Reg,
                'No_MR'             => $s->No_MR,
                'nama_pasien'       => $s->pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin'     => $s->pasien?->jenis_kelamin ?? null,
                'required_bed_type' => $s->kebutuhan_bed,
                'allocated_bed_id'  => $s->allocated_bed_id,
                'nama_bed'          => $s->bed?->ruang?->Nama_RuangM ?? $s->allocated_bed_id,
                'nama_kelas'        => $s->bed?->ruang?->kelas?->Nama_Kelas ?? $s->kebutuhan_bed,
                'status'            => $s->status,
                'raw_id'            => $s->id,
                'created_at'        => $s->created_at?->format('d/m/Y H:i'),
            ]);

        $pasienIcu = $pasienLama->merge($pasienExternal)->merge($pasienInternal)->values();

        // ── Riwayat (semua jalur, status pulang) ──────────────────
        $riwayatLama = IcuAdmision::with(['pasien'])
            ->where('status', 'pulang')->latest()->limit(10)->get()
            ->map(fn($a) => [
                'id'          => 'adm_' . $a->id,
                'sumber'      => 'lama',
                'nama_pasien' => $a->pasien?->Nama_Pasien ?? '-',
                'No_MR'       => $a->No_MR,
                'No_Reg'      => $a->No_Reg,
                'updated_at'  => $a->updated_at?->format('d/m/Y H:i'),
            ]);

        $riwayatExt = IcuBookingExternal::with(['pasien'])
            ->where('status', 'pulang')->latest()->limit(10)->get()
            ->map(fn($b) => [
                'id'          => 'ext_' . $b->id,
                'sumber'      => 'external',
                'nama_pasien' => $b->pasien?->Nama_Pasien ?? $b->nama_pasien,
                'No_MR'       => $b->No_MR,
                'No_Reg'      => $b->No_Reg,
                'updated_at'  => $b->updated_at?->format('d/m/Y H:i'),
            ]);

        $riwayatInt = IcuSpriInternal::with(['pasien'])
            ->where('status', 'pulang')->latest()->limit(10)->get()
            ->map(fn($s) => [
                'id'          => 'int_' . $s->id,
                'sumber'      => 'internal',
                'nama_pasien' => $s->pasien?->Nama_Pasien ?? '-',
                'No_MR'       => $s->No_MR,
                'No_Reg'      => $s->No_Reg,
                'updated_at'  => $s->updated_at?->format('d/m/Y H:i'),
            ]);

        $riwayat = $riwayatLama->merge($riwayatExt)->merge($riwayatInt)
            ->sortByDesc('updated_at')->take(20)->values();

        return Inertia::render('Icu/PasienIcu', [
            'pasienIcu' => $pasienIcu,
            'riwayat'   => $riwayat,
            'flash'     => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /** Jalur lama — antar pasien ke ruangan */
    public function masukRuangan(int $id): RedirectResponse
    {
        $admision  = $this->transferService->masukRuangan($id);
        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id;

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} masuk ke {$namaRuang}.");
    }

    /** Jalur lama — pulangkan pasien */
    public function pulangkan(int $id): RedirectResponse
    {
        $admision  = $this->pulangService->pulangkanPasien($id);
        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id ?? '-';

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} dipulangkan. {$namaRuang} kembali kosong.");
    }

    /** Jalur External — pulangkan */
    public function pulangkanExternal(int $id): RedirectResponse
    {
        $booking = $this->bookingExService->pulangkan($id);
        $nama    = $booking->pasien?->Nama_Pasien ?? $booking->nama_pasien;

        return back()->with('success', "Pasien {$nama} dipulangkan.");
    }

    /** Jalur Internal — pulangkan */
    public function pulangkanInternal(int $id): RedirectResponse
    {
        $spri = $this->spriIntService->pulangkan($id);
        $nama = $spri->pasien?->Nama_Pasien ?? '-';

        return back()->with('success', "Pasien {$nama} dipulangkan.");
    }
}
