<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Models\MKelas;
use App\Services\Icu\IgdService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IgdController extends Controller
{
    public function __construct(
        private readonly IgdService $igdService
    ) {}

    /**
     * Halaman IGD — 2 bagian:
     * 1. Pasien status 'daftar'  → bisa dikirim ke IGD
     * 2. Pasien status 'igd_periksa' → sedang di IGD, bisa dibuatkan SPRI
     */
    public function index(): Response
    {
        $with = ['pasien', 'pendaftaran'];

        // Pasien yang baru daftar, siap dikirim ke IGD
        $antrianDaftar = IcuAdmision::with($with)
            ->where('status', 'daftar')
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'No_Reg'        => $a->No_Reg,
                'No_MR'         => $a->No_MR,
                'nama_pasien'   => $a->pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin' => $a->pasien?->jenis_kelamin ?? null,
                'status'        => $a->status,
                'created_at'    => $a->created_at?->format('d/m/Y H:i'),
            ]);

        // Pasien yang sudah masuk IGD, menunggu SPRI dibuat
        $diIgd = IcuAdmision::with($with)
            ->where('status', 'igd_periksa')
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'No_Reg'        => $a->No_Reg,
                'No_MR'         => $a->No_MR,
                'nama_pasien'   => $a->pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin' => $a->pasien?->jenis_kelamin ?? null,
                'status'        => $a->status,
                'created_at'    => $a->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Icu/Igd', [
            'antrianDaftar' => $antrianDaftar,
            'diIgd'         => $diIgd,
            'masterKelas'   => MKelas::all()->map(fn($k) => [
                'kode' => $k->Kode_Kelas,
                'nama' => $k->Nama_Kelas,
            ]),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Step 2 — Kirim pasien dari pendaftaran ke IGD.
     */
    public function kirimIgd(int $id): RedirectResponse
    {
        $admision = $this->igdService->kirimKeIgd($id);

        return redirect()->route('icu.igd')->with(
            'success',
            "Pasien {$admision->pasien->Nama_Pasien} dikirim ke IGD untuk diperiksa."
        );
    }
}
