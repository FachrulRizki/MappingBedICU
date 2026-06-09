<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuAdmision;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use Inertia\Inertia;
use Inertia\Response;

class DenahBedController extends Controller
{
    /**
     * Denah Bed ICU — ambil dari join M_RUANG_MASTER + M_KELAS + STATUS_KAMAR
     * kemudian enriched dengan data pasien yang sedang di ICU.
     */
    public function index(): Response
    {
        // ── 1. Semua bed ICU + status kamar (dari join DB RS) ─────────────
        $bedData = MRuangMaster::bedIcuDenganStatus();

        // ── 2. Pasien aktif di ICU dari semua jalur ──────────────────────
        // Kumpulkan semua allocated_bed_id yang sedang terisi
        $bedTerisi = collect();

        // Jalur lama (IcuAdmision)
        IcuAdmision::where('status', 'di_icu')
            ->whereNotNull('allocated_bed_id')
            ->get(['No_MR', 'allocated_bed_id', 'required_bed_type'])
            ->each(fn($a) => $bedTerisi->put($a->allocated_bed_id, [
                'No_MR'      => $a->No_MR,
                'sumber'     => 'lama',
                'jenis_icu'  => $a->required_bed_type,
            ]));

        // Jalur internal (IcuSpriInternal)
        IcuSpriInternal::where('status', 'di_icu')
            ->whereNotNull('allocated_bed_id')
            ->get(['No_MR', 'allocated_bed_id', 'kebutuhan_bed'])
            ->each(fn($s) => $bedTerisi->put($s->allocated_bed_id, [
                'No_MR'      => $s->No_MR,
                'sumber'     => 'internal',
                'jenis_icu'  => $s->kebutuhan_bed,
            ]));

        // Jalur external (IcuBookingExternal)
        IcuBookingExternal::where('status', 'di_icu')
            ->whereNotNull('allocated_bed_id')
            ->get(['No_MR', 'nama_pasien', 'allocated_bed_id', 'kebutuhan_bed', 'jenis_kelamin'])
            ->each(fn($b) => $bedTerisi->put($b->allocated_bed_id, [
                'No_MR'          => $b->No_MR,
                'nama_pasien_ext'=> $b->nama_pasien,    // pasien external belum tentu punya No_MR
                'jenis_kelamin'  => $b->jenis_kelamin,
                'sumber'         => 'external',
                'jenis_icu'      => $b->kebutuhan_bed,
            ]));

        // ── 3. Ambil nama pasien dari REGISTER_PASIEN ─────────────────────
        $noMRs      = $bedTerisi->pluck('No_MR')->filter()->unique()->values();
        $pasienMap  = RegistrasiPasien::whereIn('No_MR', $noMRs)
            ->get(['No_MR', 'Nama_Pasien', 'jenis_kelamin'])
            ->keyBy('No_MR');

        // ── 4. Format denah bed ───────────────────────────────────────────
        $semuaKamar = $bedData->map(function ($row) use ($bedTerisi, $pasienMap) {
            $admisiData  = $bedTerisi->get($row->Kode_RuangM);
            $pasien      = $admisiData ? ($pasienMap[$admisiData['No_MR'] ?? ''] ?? null) : null;

            // Nama pasien: dari DB RS jika ada, fallback ke nama_pasien_ext (external)
            $namaPasien  = $pasien?->Nama_Pasien
                ?? $admisiData['nama_pasien_ext']
                ?? null;
            $jenisKelamin = $pasien?->jenis_kelamin
                ?? $admisiData['jenis_kelamin']
                ?? null;

            return [
                'Kode_Ruang'    => $row->Kode_RuangM,
                'nama_ruang'    => $row->Nama_RuangM,
                'kode_kelas'    => $row->kelas_master ?? $row->Kode_Kelas,
                'nama_kelas'    => $row->Nama_Kelas,
                'Status'        => $row->Status ?? 'KOSONG',
                'No_MR'         => $admisiData['No_MR'] ?? $row->No_MR ?? null,
                'nama_pasien'   => $namaPasien,
                'jenis_kelamin' => $jenisKelamin,
                'jenis_icu'     => $admisiData['jenis_icu'] ?? null,
                'sumber'        => $admisiData['sumber'] ?? null,
            ];
        })->values();

        // ── 5. Summary ────────────────────────────────────────────────────
        $summary = [
            'total'   => $semuaKamar->count(),
            'kosong'  => $semuaKamar->where('Status', 'KOSONG')->count(),
            'terisi'  => $semuaKamar->where('Status', 'ISI')->count(),
            'booking' => $semuaKamar->where('Status', 'BOOKING')->count(),
        ];

        return Inertia::render('Icu/DenahBed', [
            'semuaKamar' => $semuaKamar,
            'summary'    => $summary,
            'flash'      => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
