<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use App\Services\ActivityLogService;
use App\Services\Icu\BedSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly BedSyncService     $bedSync,
    ) {}

    public function index(): Response
    {
        $this->bedSync->sync();

        return Inertia::render('Icu/Monitor', [
            'bedData' => $this->getBedData(),
            'antrian' => $this->getAntrian(),
            'summary' => $this->getSummary(),
        ]);
    }

    /**
     * Endpoint JSON untuk polling auto-refresh dari Vue (setiap 10 detik).
     * Setiap poll juga menjalankan sync masuk_icu dan keluar_icu.
     */
    public function data(Request $request): JsonResponse
    {
        $this->bedSync->sync();

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
        if (! empty($noMrList)) {
            try {
                DB::connection('sqlsrv_rsus')
                    ->table('REGISTER_PASIEN')
                    ->select('No_MR', 'Nama_Pasien', 'jenis_kelamin')
                    ->whereIn('No_MR', $noMrList)
                    ->get()
                    ->each(function ($r) use (&$pasienMap) {
                        $pasienMap[$r->No_MR] = $r;
                    });
            } catch (\Exception $e) {
                Log::warning('[MonitorController] getBedData pasien: ' . $e->getMessage());
            }
        }

        // Enrichment: untuk bed yang terisi, coba cari booking aktif dari tabel lokal
        // agar bisa menampilkan diagnosa/info tambahan di monitor
        $noMrBookingMap = [];
        if (! empty($noMrList)) {
            try {
                // Ambil booking internal yang masuk_icu berdasarkan No_MR
                IcuSpriInternal::whereIn('No_MR', $noMrList)
                    ->where('status', 'masuk_icu')
                    ->get(['No_MR', 'Diagnosis', 'Dokter', 'asal_ruang', 'nama_bed'])
                    ->each(fn ($s) => $noMrBookingMap[$s->No_MR] = [
                        'diagnosa' => $s->Diagnosis,
                        'dokter'   => $s->Dokter,
                        'asal'     => $s->asal_ruang,
                    ]);
                // External (No_MR sudah terisi setelah verifikasi admisi)
                IcuBookingExternal::whereIn('No_MR', $noMrList)
                    ->where('status', 'masuk_icu')
                    ->get(['No_MR', 'diagnosa', 'asal_rujukan', 'nama_bed'])
                    ->each(fn ($b) => $noMrBookingMap[$b->No_MR] ??= [
                        'diagnosa' => $b->diagnosa,
                        'dokter'   => null,
                        'asal'     => $b->asal_rujukan,
                    ]);
            } catch (\Exception $e) {
                Log::warning('[MonitorController] getBedData booking map: ' . $e->getMessage());
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
                'diagnosa'      => $row->No_MR ? ($noMrBookingMap[$row->No_MR]['diagnosa'] ?? null) : null,
                'dokter'        => $row->No_MR ? ($noMrBookingMap[$row->No_MR]['dokter'] ?? null) : null,
                'asal_ruang'    => $row->No_MR ? ($noMrBookingMap[$row->No_MR]['asal'] ?? null) : null,
            ])
            ->values()
            ->toArray();
    }

    private function getAntrian(): array
    {
        $tz = 'Asia/Jakarta';

        // Antrian aktif external: hanya tampilkan yang belum masuk ICU
        $ext = IcuBookingExternal::whereIn('status', ['pending_icu', 'waiting_list', 'bed_confirmed', 'admisi_verified'])
            ->oldest()->get()
            ->map(fn ($b) => [
                'id'               => 'ext_' . $b->id,
                'sumber'           => 'external',
                'nama_pasien'      => $b->nama_pasien,
                'No_MR'            => $b->No_MR,
                'diagnosa'         => $b->diagnosa,
                'kebutuhan_bed'    => $b->kebutuhan_bed,
                'nama_bed'         => $b->nama_bed,
                'jaminan'          => $b->jaminan,
                'status'           => $b->status,
                'status_label'     => $b->statusLabel(),
                'asal'             => $b->asal_rujukan,
                'created_at_fmt'   => $b->created_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'confirmed_at_fmt' => $b->confirmed_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'waiting_estimasi_fmt' => $b->waiting_estimasi?->setTimezone($tz)->format('d/m/Y H:i'),
            ])->toArray();

        // Antrian aktif internal: hanya tampilkan yang belum masuk ICU
        $int = IcuSpriInternal::whereIn('status', ['pending_admisi', 'pending_icu', 'waiting_list', 'bed_verified'])
            ->oldest()->get()
            ->map(fn ($s) => [
                'id'               => 'int_' . $s->id,
                'sumber'           => 'internal',
                'nama_pasien'      => $s->pasien?->Nama_Pasien ?? $s->No_MR,
                'No_MR'            => $s->No_MR,
                'diagnosa'         => $s->Diagnosis,
                'kebutuhan_bed'    => $s->kebutuhan_bed,
                'nama_bed'         => $s->nama_bed,
                'jaminan'          => null,
                'status'           => $s->status,
                'status_label'     => $s->statusLabel(),
                'asal'             => $s->asal_ruang,
                'created_at_fmt'   => $s->created_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'approved_at_fmt'  => $s->approved_at?->setTimezone($tz)->format('d/m/Y H:i'),
                'verified_at_fmt'  => $s->verified_at?->setTimezone($tz)->format('d/m/Y H:i'),
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
            'antrian_ext' => IcuBookingExternal::whereIn('status', ['pending_icu', 'waiting_list', 'bed_confirmed', 'admisi_verified'])->count(),
            'antrian_int' => IcuSpriInternal::whereIn('status', ['pending_admisi', 'pending_icu', 'waiting_list', 'bed_verified'])->count(),
        ];
    }
}
