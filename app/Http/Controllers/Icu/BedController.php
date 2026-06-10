<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Models\StatusKamar;
use App\Services\Icu\BedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BedController extends Controller
{
    public function __construct(
        private readonly BedService $bedService
    ) {}

    /**
     * Halaman alokasi bed — pasien waiting + booking + denah semua bed.
     */
    public function index(): Response
    {
        $with = ['pasien', 'bed', 'bed.ruang', 'bed.ruang.kelas'];

        $waiting = IcuAdmision::with($with)->where('status', 'waiting_icu')->latest()->get()
            ->map(fn($a) => [
                'id'                => $a->id, 'No_Reg' => $a->No_Reg, 'No_MR' => $a->No_MR,
                'nama_pasien'       => $a->pasien?->Nama_Pasien ?? '-',
                'required_bed_type' => $a->required_bed_type,
                'status'            => $a->status,
            ]);

        $booking = IcuAdmision::with($with)->where('status', 'booking_icu')->latest()->get()
            ->map(fn($a) => [
                'id'                => $a->id, 'No_Reg' => $a->No_Reg, 'No_MR' => $a->No_MR,
                'nama_pasien'       => $a->pasien?->Nama_Pasien ?? '-',
                'required_bed_type' => $a->required_bed_type,
                'allocated_bed_id'  => $a->allocated_bed_id,
                'nama_bed'          => $a->bed?->ruang?->Nama_RuangM ?? $a->allocated_bed_id,
                'status'            => $a->status,
            ]);

        $semuaKamar  = StatusKamar::with(['ruang.kelas', 'icuAdmision.pasien'])->get()
            ->map(fn($k) => [
                'Kode_Ruang'    => $k->Kode_Ruang,
                'Status'        => $k->Status,
                'No_MR'         => $k->No_MR,
                'nama_ruang'    => $k->ruang?->Nama_RuangM ?? $k->Kode_Ruang,
                'kode_kelas'    => $k->ruang?->Kode_Kelas ?? null,
                'nama_kelas'    => $k->ruang?->kelas?->Nama_Kelas ?? null,
                'nama_pasien'   => $k->icuAdmision?->pasien?->Nama_Pasien ?? null,
                'jenis_kelamin' => $k->icuAdmision?->pasien?->jenis_kelamin ?? null,
            ]);

        $kamarKosong = StatusKamar::with('ruang.kelas')->where('Status', 'KOSONG')->get()
            ->map(fn($k) => [
                'Kode_Ruang' => $k->Kode_Ruang,
                'nama_ruang' => $k->ruang?->Nama_RuangM ?? $k->Kode_Ruang,
                'kode_kelas' => $k->ruang?->Kode_Kelas ?? null,
                'nama_kelas' => $k->ruang?->kelas?->Nama_Kelas ?? null,  // ← dibutuhkan untuk matching di Vue
            ]);

        return Inertia::render('Icu/AlokasiBed', [
            'waiting'     => $waiting,
            'booking'     => $booking,
            'semuaKamar'  => $semuaKamar,
            'kamarKosong' => $kamarKosong,
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // mapping bed
    public function alokasi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'Kode_Ruang' => 'required|exists:status_kamar,Kode_Ruang',
        ]);

        $result = $this->bedService->alokasiBed(
            admisionId: $id,
            kodeRuang:  $validated['Kode_Ruang'],
        );

        $namaRuang = $result['bed']->ruang?->Nama_RuangM ?? $result['bed']->Kode_Ruang;

        return back()->with(
            'success',
            "Pasien {$result['admision']->pasien->Nama_Pasien} mendapat kamar {$namaRuang}."
        );
    }
}
