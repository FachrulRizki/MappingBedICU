<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DenahBedController extends Controller
{
    public function index(): Response
    {
        $bedData = MRuangMaster::bedIcuDenganStatus();

        // Kumpulkan No_MR yang ada di bed untuk lookup nama pasien
        $noMrList = $bedData->pluck('No_MR')->filter()->unique()->values()->toArray();

        // Lookup nama pasien dari RegistrasiPasien
        $pasienMap = [];
        if (!empty($noMrList)) {
            try {
                $pasienMap = RegistrasiPasien::whereIn('No_MR', $noMrList)
                    ->get(['No_MR', 'Nama_Pasien'])
                    ->keyBy('No_MR')
                    ->map(fn($p) => $p->Nama_Pasien)
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('[DenahBedController] Gagal lookup pasien: ' . $e->getMessage());
            }
        }

        // Lookup diagnosa & asal_ruang dari icu_bu_internal (bed_verified) atau icu_booking_external (admisi_verified)
        $diagnosisMap  = [];
        $asalRuangMap  = [];
        if (!empty($noMrList)) {
            try {
                // Prioritas: BU Internal yang sudah bed_verified
                IcuSpriInternal::whereIn('No_MR', $noMrList)
                    ->whereIn('status', ['pending_icu', 'bed_verified'])
                    ->latest()
                    ->get(['No_MR', 'Diagnosis', 'asal_ruang'])
                    ->each(function ($s) use (&$diagnosisMap, &$asalRuangMap) {
                        if (!isset($diagnosisMap[$s->No_MR])) {
                            $diagnosisMap[$s->No_MR] = $s->Diagnosis;
                            $asalRuangMap[$s->No_MR] = $s->asal_ruang;
                        }
                    });

                // Fallback: Booking External yang sudah admisi_verified
                IcuBookingExternal::whereIn('No_MR', $noMrList)
                    ->whereIn('status', ['bed_confirmed', 'admisi_verified'])
                    ->latest()
                    ->get(['No_MR', 'diagnosa', 'asal_rujukan'])
                    ->each(function ($b) use (&$diagnosisMap, &$asalRuangMap) {
                        if (!isset($diagnosisMap[$b->No_MR])) {
                            $diagnosisMap[$b->No_MR] = $b->diagnosa;
                            $asalRuangMap[$b->No_MR] = $b->asal_rujukan;
                        }
                    });
            } catch (\Exception $e) {
                Log::warning('[DenahBedController] Gagal lookup diagnosa: ' . $e->getMessage());
            }
        }

        // Lookup jenis_kelamin dari REGISTER_PASIEN
        $jenisKelaminMap = [];
        if (!empty($noMrList)) {
            try {
                \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                    ->table('REGISTER_PASIEN')
                    ->select('No_MR', 'jenis_kelamin')
                    ->whereIn('No_MR', $noMrList)
                    ->get()
                    ->each(function ($r) use (&$jenisKelaminMap) {
                        $jenisKelaminMap[$r->No_MR] = $r->jenis_kelamin ?? null;
                    });
            } catch (\Exception $e) {
                Log::warning('[DenahBedController] Gagal lookup jenis_kelamin: ' . $e->getMessage());
            }
        }

        $semuaKamar = $bedData->map(fn($row) => [
            'Kode_Ruang'    => $row->Kode_RuangM,
            'nama_ruang'    => $row->Nama_RuangM,
            'kode_kelas'    => $row->kelas_master ?? $row->Kode_Kelas,
            'nama_kelas'    => $row->Nama_Kelas,
            'Status'        => $row->Status ?? 'KOSONG',
            'No_MR'         => $row->No_MR ?? null,
            'nama_pasien'   => $row->No_MR ? ($pasienMap[$row->No_MR] ?? null) : null,
            'jenis_kelamin' => $row->No_MR ? ($jenisKelaminMap[$row->No_MR] ?? null) : null,
            'diagnosa'      => $row->No_MR ? ($diagnosisMap[$row->No_MR] ?? null) : null,
            'asal_ruang'    => $row->No_MR ? ($asalRuangMap[$row->No_MR] ?? null) : null,
        ])->values();

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
