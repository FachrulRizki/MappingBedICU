<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Services\Icu\SpriInternalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuPetugasController extends Controller
{
    public function __construct(
        private readonly SpriInternalService $service
    ) {}

    private function actor(): string
    {
        return auth()->user()?->name ?? 'petugas_ruang';
    }

    // public function index(Request $request): Response
    // {
    //     $fNama = trim($request->query('nama', ''));
    //     $fTgl  = $request->query('tgl', '');
    //     $fStatus = $request->query('status', '');

    //     $q = IcuSpriInternal::with('pasien')
    //         ->where('NameUser', $this->actor());

    //     if ($fStatus) $q->where('status', $fStatus);
    //     if ($fNama) {
    //         $q->where(function ($qq) use ($fNama) {
    //             $qq->whereHas('pasien', fn ($p) => $p->where('Nama_Pasien', 'like', "%{$fNama}%"))
    //                ->orWhere('No_MR', 'like', "%{$fNama}%");
    //         });
    //     }
    //     if ($fTgl) $q->whereDate('created_at', $fTgl);

    //     $spriList = $q->latest()->get()->map(fn ($s) => $this->format($s));

    //     $summary = [
    //         'total'        => $spriList->count(),
    //         'pending'      => $spriList->filter(fn ($i) => in_array($i['status'], ['pending_admisi', 'pending_icu']))->count(),
    //         'bed_verified' => $spriList->filter(fn ($i) => $i['status'] === 'bed_verified')->count(),
    //         'ditolak'      => $spriList->filter(fn ($i) => $i['status'] === 'ditolak')->count(),
    //     ];

    //     return Inertia::render('Icu/MenuPetugas', [
    //         'spriList'    => $spriList,
    //         'summary'     => $summary,
    //         'filters'     => compact('fNama', 'fTgl', 'fStatus'),
    //         'kamarKosong' => MRuangMaster::bedKosong(),
    //         'masterKelas' => MRuangMaster::jenisIcuTersedia(),
    //         'flash'       => [
    //             'success' => session('success'),
    //             'error'   => session('error'),
    //         ],
    //     ]);
    // }

    public function index(Request $request): Response
    {
        $fNama   = trim($request->query('nama', ''));
        $fTgl    = $request->query('tgl', '');
        $fStatus = $request->query('status', '');

        $q = IcuSpriInternal::query()
            ->where('NameUser', $this->actor());

        if ($fStatus) {
            $q->where('status', $fStatus);
        }

        if ($fNama) {
            // ambil dari SQL Server
            $pasienIds = \App\Models\RegistrasiPasien::query()
                ->where('Nama_Pasien', 'like', "%{$fNama}%")
                ->pluck('No_MR')
                ->toArray();

            $q->where(function ($qq) use ($fNama, $pasienIds) {
                $qq->whereIn('No_MR', $pasienIds)
                ->orWhere('No_MR', 'like', "%{$fNama}%");
            });
        }

        if ($fTgl) {
            $q->whereDate('created_at', $fTgl);
        }

        $data = $q->latest()->get();

        $pasienMap = \App\Models\RegistrasiPasien::whereIn('No_MR', $data->pluck('No_MR'))
            ->get()
            ->keyBy('No_MR');

        $spriList = $data->map(function ($s) use ($pasienMap) {
            return $this->format($s, $pasienMap);
        });

        $summary = [
            'total'        => $spriList->count(),
            'pending'      => $spriList->filter(fn ($i) => in_array($i['status'], ['pending_admisi', 'pending_icu']))->count(),
            'bed_verified' => $spriList->filter(fn ($i) => $i['status'] === 'bed_verified')->count(),
            'ditolak'      => $spriList->filter(fn ($i) => $i['status'] === 'ditolak')->count(),
        ];

        return Inertia::render('Icu/MenuPetugas', [
            'spriList'    => $spriList,
            'summary'     => $summary,
            'filters'     => compact('fNama', 'fTgl', 'fStatus'),
            'kamarKosong' => MRuangMaster::bedKosong(),
            'masterKelas' => MRuangMaster::jenisIcuTersedia(),
        ]);
    }

    public function storeSpri(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'      => 'required|string|max:20',
            'No_Reg'     => 'required|string|max:20',
            'Diagnosis'  => 'required|string|max:255',
            'IndikasiRI' => 'required|string|max:255',
            'asal_ruang' => 'nullable|string|max:100',
            'Dokter'     => 'nullable|string|max:100',
            'spesialis'  => 'nullable|string|max:100',
            'Keterangan' => 'nullable|string|max:500',
        ]);

        $spri = $this->service->buatSpri([
            ...$validated,
            'NameUser' => $this->actor(),
        ]);

        return back()->with('success', "SPRI untuk {$spri->pasien?->Nama_Pasien} (No. MR: {$spri->No_MR}) berhasil dibuat.");
    }

    private function format(IcuSpriInternal $s): array
    {
        return [
            'id'            => $s->id,
            'No_MR'         => $s->No_MR,
            'No_Reg'        => $s->No_Reg,
            // Nama dari relasi pasien (REGISTER_PASIEN / registrasi_pasien)
            'nama_pasien'   => $s->pasien?->Nama_Pasien ?? '-',
            // RegistrasiPasien kolom jenis_kelamin lowercase
            'jenis_kelamin' => $s->pasien?->jenis_kelamin ?? null,
            'Diagnosis'     => $s->Diagnosis,
            'IndikasiRI'    => $s->IndikasiRI,
            'kebutuhan_bed' => $s->kebutuhan_bed,
            'asal_ruang'    => $s->asal_ruang,
            'Dokter'        => $s->Dokter,
            'spesialis'     => $s->spesialis,
            'Keterangan'    => $s->Keterangan,
            'catatan_admisi'=> $s->catatan_admisi,
            'nama_bed'      => $s->nama_bed,
            'status'        => $s->status,
            'status_label'  => $s->statusLabel(),
            'alasan_tolak'  => $s->alasan_tolak,
            'approved_by'   => $s->approved_by,
            'verified_by'   => $s->verified_by,
            'created_at'    => $s->created_at?->format('Y-m-d H:i'),
            'created_at_fmt'=> $s->created_at?->format('d/m/Y H:i'),
        ];
    }
}
