<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\StatusKamar;
use Inertia\Inertia;
use Inertia\Response;

class DenahBedController extends Controller
{
    /**
     * Halaman Denah Bed ICU — real-time view semua bed.
     */
    public function index(): Response
    {
        $semuaKamar = StatusKamar::with(['ruang.kelas', 'icuAdmision.pasien'])->get()
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

        return Inertia::render('Icu/DenahBed', [
            'semuaKamar' => $semuaKamar,
            'flash'      => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
