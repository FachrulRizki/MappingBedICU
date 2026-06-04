<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
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
     * Halaman pasien di IGD.
     */
    public function index(): Response
    {
        $with = ['pasien', 'pendaftaran'];
        $list = IcuAdmision::with($with)
            ->where('status', 'igd_periksa')
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'No_Reg'      => $a->No_Reg,
                'No_MR'       => $a->No_MR,
                'nama_pasien' => $a->pasien?->Nama_Pasien ?? '-',
                'status'      => $a->status,
                'created_at'  => $a->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Icu/Igd', [
            'list'  => $list,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Step 2 — Kirim pasien dari pendaftaran ke IGD untuk diperiksa.
     */
    public function kirimIgd(int $id): RedirectResponse
    {
        $admision = $this->igdService->kirimKeIgd($id);

        return back()->with(
            'success',
            "Pasien {$admision->pasien->Nama_Pasien} dikirim ke IGD untuk diperiksa."
        );
    }
}
