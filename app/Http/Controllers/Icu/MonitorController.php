<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Icu/Monitor', [
            'bedData'   => $this->getBedData(),
            'antrian'   => $this->getAntrian(),
            'summary'   => $this->getSummary(),
        ]);
    }

    /**
     * Endpoint JSON untuk polling auto-refresh dari Vue (setiap 30 detik).
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'bedData' => $this->getBedData(),
            'antrian' => $this->getAntrian(),
            'summary' => $this->getSummary(),
            'ts'      => now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s'),
        ]);
    }

    private function getBedData(): array
    {
        $bedData  = MRuangMaster::bedIcuDenganStatus();
        $noMrList = $bedData->pluck('No_MR')->filter()->unique()->values()->toArray();

        $pasienMap = [];
        if (!empty($noMrList)) {
            try {
                \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                    ->table('REGISTER_PASIEN')
                    ->select('No_MR', 'Nama_Pasien', 'jenis_kelamin')
                    ->whereIn('No_MR', $noMrList)
                    ->get()
                    ->each(function ($r) use (&$pasienMap) {
                        $pasienMap[$r->No_MR] = $r;
                    });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[MonitorController] getBedData pasien: ' . $e->getMessage());
            }
        }

        return $bedData
            ->map(fn ($row) => [
                'kode'          => $row->Kode_RuangM,
                'nama'          => $row->Nama_RuangM,
                'kode_kelas'    => $row->kelas_master ?? $row->Kode_Kelas,
                'nama_kelas'    => $row->Nama_Kelas,
                'status'        => $row->Status ?? 'KOSONG',
                'No_MR'         => $row->No_MR,
                'nama_pasien'   => $row->No_MR ? ($pasienMap[$row->No_MR]->Nama_Pasien ?? null) : null,
                'jenis_kelamin' => $row->No_MR ? ($pasienMap[$row->No_MR]->jenis_kelamin ?? null) : null,
            ])
            ->values()
            ->toArray();
    }

    private function getAntrian(): array
    {
        $tz = 'Asia/Jakarta';

        $ext = IcuBookingExternal::whereIn('status', ['pending_icu', 'waiting_list', 'bed_confirmed'])
            ->oldest()->limit(20)->get()
            ->map(fn ($b) => [
                'id'            => 'ext_' . $b->id,
                'sumber'        => 'external',
                'nama_pasien'   => $b->nama_pasien,
                'No_MR'         => $b->No_MR,
                'diagnosa'      => $b->diagnosa,
                'kebutuhan_bed' => $b->kebutuhan_bed,
                'nama_bed'      => $b->nama_bed,
                'jaminan'       => $b->jaminan,
                'status'        => $b->status,
                'status_label'  => $b->statusLabel(),
                'asal'          => $b->asal_rujukan,
                'created_at_fmt'=> $b->created_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'confirmed_at_fmt' => $b->confirmed_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'waiting_estimasi_fmt' => $b->waiting_estimasi?->setTimezone($tz)->format('d/m/Y H:i'),
            ])->toArray();

        $int = IcuSpriInternal::whereIn('status', ['pending_admisi', 'pending_icu', 'waiting_list', 'bed_verified'])
            ->oldest()->limit(20)->get()
            ->map(fn ($s) => [
                'id'            => 'int_' . $s->id,
                'sumber'        => 'internal',
                'nama_pasien'   => $s->pasien?->Nama_Pasien ?? $s->No_MR,
                'No_MR'         => $s->No_MR,
                'diagnosa'      => $s->Diagnosis,
                'kebutuhan_bed' => $s->kebutuhan_bed,
                'nama_bed'      => $s->nama_bed,
                'jaminan'       => null,
                'status'        => $s->status,
                'status_label'  => $s->statusLabel(),
                'asal'          => $s->asal_ruang,
                'created_at_fmt'=> $s->created_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'approved_at_fmt'=> $s->approved_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'verified_at_fmt'=> $s->verified_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'waiting_estimasi_fmt' => $s->waiting_estimasi?->setTimezone($tz)->format('d/m/Y H:i'),
            ])->toArray();

        return collect($ext)->concat($int)
            ->sortBy('created_at_fmt')
            ->values()
            ->toArray();
    }

    private function getSummary(): array
    {
        $bed = MRuangMaster::bedIcuDenganStatus();
        return [
            'total_bed'   => $bed->count(),
            'kosong'      => $bed->where('Status', 'KOSONG')->count(),
            'terisi'      => $bed->where('Status', 'ISI')->count(),
            'booking'     => $bed->where('Status', 'BOOKING')->count(),
            'antrian_ext' => IcuBookingExternal::whereIn('status', ['pending_icu','waiting_list','bed_confirmed'])->count(),
            'antrian_int' => IcuSpriInternal::whereIn('status', ['pending_admisi','pending_icu','waiting_list','bed_verified'])->count(),
        ];
    }
}
