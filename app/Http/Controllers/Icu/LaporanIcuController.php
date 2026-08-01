<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\RegistrasiPasien;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LaporanIcuController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->getFilters($request);
        $data    = $this->getData($filters);

        return Inertia::render('Icu/LaporanPasienKeluar', [
            'pasienKeluar' => $data,
            'summary'      => $this->summary($data),
            'filters'      => $filters,
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->getFilters($request);
        $data    = $this->getData($filters);
        $summary = $this->summary($data);

        $pdf = Pdf::loadView('pdf.laporan-pasien-keluar', [
            'data'    => $data,
            'summary' => $summary,
            'filters' => $filters,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-pasien-keluar-icu_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    private function getFilters(Request $request): array
    {
        $today = now()->format('Y-m-d');

        return [
            'tgl_dari'  => $request->query('tgl_dari', $today),
            'tgl_sampai'=> $request->query('tgl_sampai', $today),
            'jenis'     => $request->query('jenis', ''),      // external | internal | ''
            'nama'      => trim($request->query('nama', '')),
        ];
    }

    private function getData(array $f): array
    {
        $dari   = $f['tgl_dari']   . ' 00:00:00';
        $sampai = $f['tgl_sampai'] . ' 23:59:59';

        // ── External: status selesai ──────────────────────────────────────────
        $externals = collect();
        if ($f['jenis'] !== 'internal') {
            $qExt = IcuBookingExternal::where('status', 'selesai')
                ->whereBetween('updated_at', [$dari, $sampai]);
            if ($f['nama']) {
                $qExt->where('nama_pasien', 'like', "%{$f['nama']}%");
            }
            $externals = $qExt->latest('updated_at')->get()->map(fn ($b) => [
                'id'            => $b->id,
                'sumber'        => 'external',
                'sumber_label'  => 'Booking Eksternal',
                'nama_pasien'   => $b->nama_pasien,
                'No_MR'         => $b->No_MR ?? '—',
                'No_Reg'        => $b->No_Reg ?? '—',
                'jenis_kelamin' => $b->jenis_kelamin ?? '—',
                'diagnosa'      => $b->diagnosa ?? '—',
                'nama_bed'      => $b->nama_bed ?? '—',
                'asal'          => $b->asal_rujukan ?? '—',
                'jaminan'       => $b->jaminan ?? '—',
                'masuk_at'      => $b->masuk_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—',
                'keluar_at'     => $b->updated_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—',
                'lama_rawat'    => $b->masuk_at ? $this->lamaRawat($b->masuk_at, $b->updated_at) : '—',
                'created_by'    => $b->created_by ?? '—',
                'confirmed_by'  => $b->confirmed_by ?? '—',
            ]);
        }

        // ── Internal: status selesai ──────────────────────────────────────────
        $internals = collect();
        if ($f['jenis'] !== 'external') {
            $qInt = IcuSpriInternal::where('status', 'selesai')
                ->whereBetween('updated_at', [$dari, $sampai]);
            $results = $qInt->latest('updated_at')->get();

            // Lookup nama pasien
            $noMrList  = $results->pluck('No_MR')->filter()->unique()->values()->toArray();
            $pasienMap = [];
            if (!empty($noMrList)) {
                try {
                    $pasienMap = RegistrasiPasien::whereIn('No_MR', $noMrList)
                        ->get(['No_MR', 'Nama_Pasien', 'Jenis_Kelamin'])
                        ->keyBy('No_MR')->toArray();
                } catch (\Exception $e) {
                    Log::warning('[LaporanIcuController] lookup pasien: ' . $e->getMessage());
                }
            }

            if ($f['nama']) {
                $results = $results->filter(fn ($s) =>
                    str_contains(strtolower($pasienMap[$s->No_MR]['Nama_Pasien'] ?? $s->No_MR), strtolower($f['nama']))
                );
            }

            $internals = $results->map(fn ($s) => [
                'id'            => $s->id,
                'sumber'        => 'internal',
                'sumber_label'  => 'Booking Internal',
                'nama_pasien'   => $pasienMap[$s->No_MR]['Nama_Pasien'] ?? $s->No_MR,
                'No_MR'         => $s->No_MR,
                'No_Reg'        => $s->No_Reg ?? '—',
                'jenis_kelamin' => strtoupper($pasienMap[$s->No_MR]['Jenis_Kelamin'] ?? '—'),
                'diagnosa'      => $s->Diagnosis ?? '—',
                'nama_bed'      => $s->nama_bed ?? '—',
                'asal'          => $s->asal_ruang ?? '—',
                'jaminan'       => '—',
                'masuk_at'      => $s->masuk_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—',
                'keluar_at'     => $s->updated_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—',
                'lama_rawat'    => $s->masuk_at ? $this->lamaRawat($s->masuk_at, $s->updated_at) : '—',
                'created_by'    => $s->NameUser ?? '—',
                'confirmed_by'  => $s->verified_by ?? '—',
            ]);
        }

        return $externals->concat($internals)
            ->sortByDesc('keluar_at')
            ->values()
            ->toArray();
    }

    private function summary(array $data): array
    {
        $col = collect($data);
        return [
            'total'    => $col->count(),
            'external' => $col->where('sumber', 'external')->count(),
            'internal' => $col->where('sumber', 'internal')->count(),
            'laki'     => $col->where('jenis_kelamin', 'L')->count(),
            'perempuan'=> $col->where('jenis_kelamin', 'P')->count(),
        ];
    }

    private function lamaRawat($masuk, $keluar): string
    {
        if (!$masuk || !$keluar) return '—';
        try {
            $diff  = $masuk->diff($keluar);
            $parts = [];
            if ($diff->d > 0) $parts[] = "{$diff->d} hari";
            if ($diff->h > 0) $parts[] = "{$diff->h} jam";
            if ($diff->i > 0) $parts[] = "{$diff->i} menit";
            return $parts ? implode(' ', $parts) : '< 1 menit';
        } catch (\Exception) {
            return '—';
        }
    }
}
