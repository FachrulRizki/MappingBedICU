<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Services\Icu\TransferService;
use Illuminate\Http\RedirectResponse;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService
    ) {}

    public function masukRuangan(int $id): RedirectResponse
    {
        $admision  = $this->transferService->masukRuangan($id);
        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id;

        return back()->with(
            'success',
            "Pasien {$admision->pasien->Nama_Pasien} masuk ke {$namaRuang}."
        );
    }
}
