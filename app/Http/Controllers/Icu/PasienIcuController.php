<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use App\Services\Icu\TransferService;
use App\Services\Icu\PulangService;
use App\Services\Icu\BookingExternalService;
use App\Services\Icu\SpriInternalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasienIcuController extends Controller
{
    public function __construct(
        private readonly TransferService        $transferService,
        private readonly PulangService          $pulangService,
        private readonly BookingExternalService $bookingExService,
        private readonly SpriInternalService    $spriIntService,
    ) {}

    public function index(): Response
    {
        $bedMap = MRuangMaster::bedIcuDenganStatus()->keyBy('Kode_RuangM');
        $namaRuang = fn($kode) => $bedMap[$kode]?->Nama_RuangM ?? $kode ?? '-';
        $namaKelas = fn($kode) => $bedMap[$kode]?->Nama_Kelas  ?? null;

        $adm  = IcuAdmision::where('status', 'di_icu')->get();
        $spri = IcuSpriInternal::where('status', 'di_icu')->get();
        $ext  = IcuBookingExternal::where('status', 'di_icu')->get();

        $noMRs = collect()
            ->merge($adm->pluck('No_MR'))
            ->merge($spri->pluck('No_MR'))
            ->merge($ext->pluck('No_MR'))
            ->filter()->unique()->values();

        $pasienMap = RegistrasiPasien::whereIn('No_MR', $noMRs)
            ->get(['No_MR', 'Nama_Pasien', 'jenis_kelamin'])
            ->keyBy('No_MR');

        $pasienLama = $adm->map(fn($a) => [
            'id'               => 'adm_' . $a->id,
            'sumber'           => 'lama',
            'raw_id'           => $a->id,
            'No_MR'            => $a->No_MR,
            'No_Reg'           => $a->No_Reg,
            'nama_pasien'      => $pasienMap[$a->No_MR]?->Nama_Pasien ?? '-',
            'jenis_kelamin'    => $pasienMap[$a->No_MR]?->jenis_kelamin ?? null,
            'jenis_icu'        => $a->required_bed_type,
            'allocated_bed_id' => $a->allocated_bed_id,
            'nama_bed'         => $namaRuang($a->allocated_bed_id),
            'nama_kelas'       => $namaKelas($a->allocated_bed_id) ?? $a->required_bed_type,
            'status'           => $a->status,
            'created_at'       => $a->created_at?->format('d/m/Y H:i'),
        ]);

        $pasienInternal = $spri->map(fn($s) => [
            'id'               => 'int_' . $s->id,
            'sumber'           => 'internal',
            'raw_id'           => $s->id,
            'No_MR'            => $s->No_MR,
            'No_Reg'           => $s->No_Reg,
            'nama_pasien'      => $pasienMap[$s->No_MR]?->Nama_Pasien ?? '-',
            'jenis_kelamin'    => $pasienMap[$s->No_MR]?->jenis_kelamin ?? null,
            'jenis_icu'        => $s->kebutuhan_bed,
            'allocated_bed_id' => $s->allocated_bed_id,
            'nama_bed'         => $namaRuang($s->allocated_bed_id),
            'nama_kelas'       => $namaKelas($s->allocated_bed_id) ?? $s->kebutuhan_bed,
            'status'           => $s->status,
            'created_at'       => $s->created_at?->format('d/m/Y H:i'),
        ]);

        $pasienExternal = $ext->map(fn($b) => [
            'id'               => 'ext_' . $b->id,
            'sumber'           => 'external',
            'raw_id'           => $b->id,
            'No_MR'            => $b->No_MR,
            'No_Reg'           => $b->No_Reg,
            'nama_pasien'      => $pasienMap[$b->No_MR]?->Nama_Pasien ?? $b->nama_pasien,
            'jenis_kelamin'    => $pasienMap[$b->No_MR]?->jenis_kelamin ?? $b->jenis_kelamin,
            'jenis_icu'        => $b->kebutuhan_bed,
            'allocated_bed_id' => $b->allocated_bed_id,
            'nama_bed'         => $namaRuang($b->allocated_bed_id),
            'nama_kelas'       => $namaKelas($b->allocated_bed_id) ?? $b->kebutuhan_bed,
            'status'           => $b->status,
            'created_at'       => $b->created_at?->format('d/m/Y H:i'),
        ]);

        $pasienIcu = $pasienLama
            ->merge($pasienInternal)
            ->merge($pasienExternal)
            ->sortByDesc('created_at')
            ->values();

        $riwayatRaw = collect()
            ->merge(
                IcuAdmision::where('status', 'pulang')->latest()->limit(10)->get()
                    ->map(fn($a) => ['id' => 'adm_'.$a->id, 'No_MR' => $a->No_MR, 'No_Reg' => $a->No_Reg,
                        'nama_ext' => null, 'updated_at' => $a->updated_at, 'sumber' => 'lama'])
            )
            ->merge(
                IcuSpriInternal::where('status', 'pulang')->latest()->limit(10)->get()
                    ->map(fn($s) => ['id' => 'int_'.$s->id, 'No_MR' => $s->No_MR, 'No_Reg' => $s->No_Reg,
                        'nama_ext' => null, 'updated_at' => $s->updated_at, 'sumber' => 'internal'])
            )
            ->merge(
                IcuBookingExternal::where('status', 'pulang')->latest()->limit(10)->get()
                    ->map(fn($b) => ['id' => 'ext_'.$b->id, 'No_MR' => $b->No_MR, 'No_Reg' => $b->No_Reg,
                        'nama_ext' => $b->nama_pasien, 'updated_at' => $b->updated_at, 'sumber' => 'external'])
            );

        $noMRsRiwayat = $riwayatRaw->pluck('No_MR')->filter()->unique()->values();
        $pasienRiwayatMap = RegistrasiPasien::whereIn('No_MR', $noMRsRiwayat)
            ->get(['No_MR', 'Nama_Pasien'])->keyBy('No_MR');

        $riwayat = $riwayatRaw
            ->map(fn($r) => [
                'id'          => $r['id'],
                'sumber'      => $r['sumber'],
                'nama_pasien' => $pasienRiwayatMap[$r['No_MR'] ?? '']?->Nama_Pasien
                    ?? $r['nama_ext'] ?? '-',
                'No_MR'       => $r['No_MR'],
                'No_Reg'      => $r['No_Reg'],
                'updated_at'  => $r['updated_at'] instanceof \Carbon\Carbon
                    ? $r['updated_at']->format('d/m/Y H:i')
                    : ($r['updated_at'] ?? '-'),
            ])
            ->sortByDesc('updated_at')
            ->take(20)->values();

        return Inertia::render('Icu/PasienIcu', [
            'pasienIcu' => $pasienIcu,
            'riwayat'   => $riwayat,
            'flash'     => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // Jalur lama

    public function masukRuangan(int $id): RedirectResponse
    {
        $this->transferService->masukRuangan($id);
        return back()->with('success', 'Pasien masuk ke ruangan ICU.');
    }

    public function pulangkan(int $id): RedirectResponse
    {
        $admision = $this->pulangService->pulangkanPasien($id);
        $nama     = $admision->pasien?->Nama_Pasien ?? '-';
        return back()->with('success', "Pasien {$nama} dipulangkan.");
    }

    // Jalur external 

    public function pulangkanExternal(int $id): RedirectResponse
    {
        $booking = $this->bookingExService->pulangkan($id);
        $nama    = $booking->pasien?->Nama_Pasien ?? $booking->nama_pasien;
        return back()->with('success', "Pasien {$nama} dipulangkan.");
    }

    // Jalur internal 

    public function pulangkanInternal(int $id): RedirectResponse
    {
        $spri = $this->spriIntService->pulangkan($id);
        $nama = $spri->pasien?->Nama_Pasien ?? '-';
        return back()->with('success', "Pasien {$nama} dipulangkan.");
    }
}
