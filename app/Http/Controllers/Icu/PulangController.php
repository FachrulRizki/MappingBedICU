<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Services\Icu\PulangService;
use Illuminate\Http\RedirectResponse;

class PulangController extends Controller
{
    public function __construct(
        private readonly PulangService $pulangService
    ) {}

    public function pulangkan(int $id): RedirectResponse
    {
        $admision  = $this->pulangService->pulangkanPasien($id);
        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id ?? '-';

        return back()->with(
            'success',
            "Pasien {$admision->pasien->Nama_Pasien} dipulangkan. {$namaRuang} kembali kosong."
        );
    }
}