<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\MRuangMaster;
use Inertia\Inertia;
use Inertia\Response;

class DenahBedController extends Controller
{
    public function index(): Response
    {
        $bedData = MRuangMaster::bedIcuDenganStatus();

        $semuaKamar = $bedData->map(fn($row) => [
            'Kode_Ruang'  => $row->Kode_RuangM,
            'nama_ruang'  => $row->Nama_RuangM,
            'kode_kelas'  => $row->kelas_master ?? $row->Kode_Kelas,
            'nama_kelas'  => $row->Nama_Kelas,
            'Status'      => $row->Status ?? 'KOSONG',
            'No_MR'       => $row->No_MR ?? null,
        ])->values();

        $summary = [
            'total'   => $semuaKamar->count(),
            'kosong'  => $semuaKamar->where('Status', 'KOSONG')->count(),
            'terisi'  => $semuaKamar->where('Status', 'ISI')->count(),
            'booking' => $semuaKamar->where('Status', 'BOOKING')->count(),
        ];

        return Inertia::render('Icu/DenahBed', [
            'semuaKamar' => $semuaKamar,
            'summary'    => $summary,
            'flash'      => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
