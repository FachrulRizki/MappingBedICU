<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Models\MKelas;
use App\Services\Icu\SpriService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpriController extends Controller
{
    public function __construct(
        private readonly SpriService $spriService
    ) {}

    public function index(): Response
    {
        $with = ['pasien', 'pendaftaran', 'pendaftaran.spriAktif'];

        $butuhSpri = IcuAdmision::with($with)->where('status', 'igd_periksa')->latest()->get()
            ->map(fn($a) => [
                'id'          => $a->id, 'No_Reg' => $a->No_Reg, 'No_MR' => $a->No_MR,
                'nama_pasien' => $a->pasien?->Nama_Pasien ?? '-',
                'status'      => $a->status,
                'created_at'  => $a->created_at?->format('d/m/Y H:i'),
            ]);

        $menungguApproval = IcuAdmision::with($with)->where('status', 'spri_dibuat')->latest()->get()
            ->map(fn($a) => [
                'id'                => $a->id, 'No_Reg' => $a->No_Reg, 'No_MR' => $a->No_MR,
                'nama_pasien'       => $a->pasien?->Nama_Pasien ?? '-',
                'status'            => $a->status,
                'required_bed_type' => $a->required_bed_type,
                'diagnosis'         => $a->pendaftaran?->spriAktif?->Diagnosis ?? '-',
                'indikasi'          => $a->pendaftaran?->spriAktif?->IndikasiRI ?? '-',
                'created_at'        => $a->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Icu/Spri', [
            'butuhSpri'        => $butuhSpri,
            'menungguApproval' => $menungguApproval,
            'masterKelas'      => MKelas::all()->map(fn($k) => ['kode' => $k->Kode_Kelas, 'nama' => $k->Nama_Kelas]),
            'flash'            => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'Diagnosis'         => 'required|string|max:255',
            'IndikasiRI'        => 'required|string|max:255',
            'required_bed_type' => 'required|exists:m_kelas,Nama_Kelas',
            'Keterangan'        => 'nullable|string|max:255',
            'Dokter'            => 'nullable|string|max:100',
            'spesialis'         => 'nullable|string|max:100',
        ]);

        $result = $this->spriService->buatSpri(
            admisionId:      $id,
            diagnosis:       $validated['Diagnosis'],
            indikasiRI:      $validated['IndikasiRI'],
            requiredBedType: $validated['required_bed_type'],
            keterangan:      $validated['Keterangan']  ?? '-',
            dokter:          $validated['Dokter']      ?? 'Dokter IGD',
            spesialis:       $validated['spesialis']   ?? '-',
        );

        return back()->with(
            'success',
            "SPRI dibuat. Kebutuhan bed: {$result['namaKelas']}"
        );
    }

    public function approve(int $id): RedirectResponse
    {
        $admision = $this->spriService->approveSpri($id);

        return back()->with(
            'success',
            "SPRI disetujui. Pasien {$admision->pasien->Nama_Pasien} masuk daftar tunggu ICU."
        );
    }
}
