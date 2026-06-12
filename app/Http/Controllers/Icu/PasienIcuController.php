<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use Inertia\Inertia;
use Inertia\Response;

class PasienIcuController extends Controller
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

        // Kelompokkan per jenis ICU
        $perKelas = $semuaKamar
            ->groupBy('nama_kelas')
            ->map(fn($beds, $kelas) => [
                'nama_kelas' => $kelas,
                'total'      => $beds->count(),
                'kosong'     => $beds->where('Status', 'KOSONG')->count(),
                'terisi'     => $beds->where('Status', 'ISI')->count(),
                'booking'    => $beds->where('Status', 'BOOKING')->count(),
                'beds'       => $beds->values(),
            ])
            ->values();

        return Inertia::render('Icu/InfoBed', [
            'semuaKamar' => $semuaKamar,
            'perKelas'   => $perKelas,
            'summary'    => $summary,
            'flash'      => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
