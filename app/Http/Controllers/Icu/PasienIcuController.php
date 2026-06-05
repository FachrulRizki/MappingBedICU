<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Services\Icu\TransferService;
use App\Services\Icu\PulangService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasienIcuController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly PulangService   $pulangService,
    ) {}

    /**
     * Halaman pasien aktif di ICU.
     */
    public function index(): Response
    {
        $with = ['pasien', 'bed', 'bed.ruang', 'bed.ruang.kelas'];

        $pasienIcu = IcuAdmision::with($with)
            ->where('status', 'di_icu')
            ->latest()
            ->get()
            ->map(fn($a) => [
                'id'                => $a->id,
                'No_Reg'            => $a->No_Reg,
                'No_MR'             => $a->No_MR,
                'nama_pasien'       => $a->pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin'     => $a->pasien?->jenis_kelamin ?? null,
                'required_bed_type' => $a->required_bed_type,
                'allocated_bed_id'  => $a->allocated_bed_id,
                'nama_bed'          => $a->bed?->ruang?->Nama_RuangM ?? $a->allocated_bed_id,
                'nama_kelas'        => $a->bed?->ruang?->kelas?->Nama_Kelas ?? $a->required_bed_type,
                'status'            => $a->status,
                'created_at'        => $a->created_at?->format('d/m/Y H:i'),
            ]);

        $riwayat = IcuAdmision::with(['pasien'])
            ->where('status', 'pulang')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'No_Reg'       => $a->No_Reg,
                'No_MR'        => $a->No_MR,
                'nama_pasien'  => $a->pasien?->Nama_Pasien ?? '-',
                'status'       => $a->status,
                'updated_at'   => $a->updated_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Icu/PasienIcu', [
            'pasienIcu' => $pasienIcu,
            'riwayat'   => $riwayat,
            'flash'     => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Step 6 — Antar pasien ke ruangan ICU.
     */
    public function masukRuangan(int $id): RedirectResponse
    {
        $admision  = $this->transferService->masukRuangan($id);
        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id;

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} masuk ke {$namaRuang}.");
    }

    /**
     * Step 7 — Pulangkan pasien.
     */
    public function pulangkan(int $id): RedirectResponse
    {
        $admision  = $this->pulangService->pulangkanPasien($id);
        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id ?? '-';

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} dipulangkan. {$namaRuang} kembali kosong.");
    }
}
