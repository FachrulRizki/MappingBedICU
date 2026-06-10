<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Services\Icu\PendaftaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendaftaranController extends Controller
{
    public function __construct(
        private readonly PendaftaranService $pendaftaranService
    ) {}

    public function index(): Response
    {
        $with = ['pasien', 'pendaftaran'];
        $list = IcuAdmision::with($with)
            ->where('status', 'daftar')
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'No_Reg'         => $a->No_Reg,
                'No_MR'          => $a->No_MR,
                'nama_pasien'    => $a->pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin'  => $a->pasien?->jenis_kelamin ?? null,
                'status'         => $a->status,
                'created_at'     => $a->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Icu/Pendaftaran', [
            'list'  => $list,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'Nama_Pasien'    => 'required|string|max:200',
            'No_Identitas'   => 'required|string|max:50',
            'KartuBPJS'      => 'nullable|string|max:50',
            'jenis_kelamin'  => 'required|in:L,P',
        ]);

        $result = $this->pendaftaranService->daftarkanPasien(
            namaPasien:    $validated['Nama_Pasien'],
            noIdentitas:   $validated['No_Identitas'],
            kartuBpjs:     $validated['KartuBPJS'] ?? null,
            jenisKelamin:  $validated['jenis_kelamin'],
        );

        return back()->with(
            'success',
            "Pasien {$result['pasien']->Nama_Pasien} berhasil didaftarkan. No. MR: {$result['pasien']->No_MR}"
        );
    }
}