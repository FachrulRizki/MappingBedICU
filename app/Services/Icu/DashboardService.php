<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Driver sqlsrv di Windows mengembalikan array, bukan stdClass.
     * Helper ini membaca property dari array maupun object secara aman.
     */
    private function val(mixed $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row)) return $row[$key] ?? $default;
        return $row->$key ?? $default;
    }

    public function getDashboardData(?User $user = null, array $filters = []): array
    {
        $role           = $user?->role ?? 'guest';
        $isPetugasRuang = $role === 'petugas_ruang';

        $tglDari   = $filters['tgl_dari']   ?? now()->format('Y-m-d');
        $tglSampai = $filters['tgl_sampai'] ?? now()->format('Y-m-d');
        $search    = trim($filters['search'] ?? '');

        // ── Info bed ──────────────────────────────────────────────────────
        $bedData    = MRuangMaster::bedIcuDenganStatus();
        $semuaKamar = $bedData->map(fn($row) => [
            'Kode_Ruang' => $this->val($row, 'Kode_RuangM'),
            'Status'     => $this->val($row, 'Status', 'KOSONG'),
            'nama_ruang' => $this->val($row, 'Nama_RuangM'),
            'kode_kelas' => $this->val($row, 'kelas_master') ?? $this->val($row, 'Kode_Kelas'),
            'nama_kelas' => $this->val($row, 'Nama_Kelas'),
            'No_MR'      => $this->val($row, 'No_MR'),
        ])->values();

        // ── Stats & list aktif ────────────────────────────────────────────
        if ($isPetugasRuang && $user) {
            $actorNames = $this->resolveActorNames($user);

            $statsExternal = ['pending' => 0, 'bed_confirmed' => 0, 'terverifikasi' => 0];
            $statsInternal = [
                'pending_admisi' => IcuSpriInternal::whereIn('NameUser', $actorNames)->where('status', 'pending_admisi')->count(),
                'pending_icu'    => IcuSpriInternal::whereIn('NameUser', $actorNames)->where('status', 'pending_icu')->count(),
                'bed_verified'   => IcuSpriInternal::whereIn('NameUser', $actorNames)->where('status', 'bed_verified')->count(),
            ];

            $extList = collect();
            $intList = IcuSpriInternal::whereIn('NameUser', $actorNames)
                ->whereIn('status', ['pending_admisi', 'pending_icu', 'bed_verified'])
                ->whereBetween('created_at', [$tglDari . ' 00:00:00', $tglSampai . ' 23:59:59'])
                ->when($search, fn($q) => $q->where('No_MR', 'like', "%{$search}%"))
                ->latest()->get();
        } else {
            $statsExternal = [
                'pending'       => IcuBookingExternal::where('status', 'pending_icu')->count(),
                'bed_confirmed' => IcuBookingExternal::where('status', 'bed_confirmed')->count(),
                'terverifikasi' => IcuBookingExternal::where('status', 'admisi_verified')->count(),
            ];
            $statsInternal = [
                'pending_admisi' => IcuSpriInternal::where('status', 'pending_admisi')->count(),
                'pending_icu'    => IcuSpriInternal::where('status', 'pending_icu')->count(),
                'bed_verified'   => IcuSpriInternal::where('status', 'bed_verified')->count(),
            ];

            $extList = IcuBookingExternal::whereIn('status', ['pending_icu', 'bed_confirmed', 'admisi_verified'])
                ->whereBetween('created_at', [$tglDari . ' 00:00:00', $tglSampai . ' 23:59:59'])
                ->when($search, fn($q) => $q->where(fn($qq) =>
                    $qq->where('nama_pasien', 'like', "%{$search}%")
                       ->orWhere('No_MR', 'like', "%{$search}%")
                ))
                ->latest()->get();

            $intList = IcuSpriInternal::whereIn('status', ['pending_admisi', 'pending_icu', 'bed_verified'])
                ->whereBetween('created_at', [$tglDari . ' 00:00:00', $tglSampai . ' 23:59:59'])
                ->when($search, fn($q) => $q->where('No_MR', 'like', "%{$search}%"))
                ->latest()->get();
        }

        // Lookup nama pasien via DB RS
        $noMRs = $intList->pluck('No_MR')->filter()->unique()->values()->toArray();

        $pasienMap = [];
        if (!empty($noMRs)) {
            try {
                $rows = DB::connection('sqlsrv_rsus')
                    ->table('REGISTER_PASIEN')
                    ->select('No_MR', 'Nama_Pasien', 'jenis_kelamin')
                    ->whereIn('No_MR', $noMRs)
                    ->get();

                foreach ($rows as $row) {
                    $key = $this->val($row, 'No_MR');
                    $pasienMap[$key] = $row;
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[DashboardService] Lookup pasien gagal: ' . $e->getMessage());
            }
        }

        // ── Format external (mysql — stdClass aman, tapi pakai val()) ────
        $listExt = $extList->map(fn($b) => [
            'id'             => 'ext_' . $b->id,
            'jalur'          => 'external',
            'nama_pasien'    => $b->nama_pasien,
            'jenis_kelamin'  => $b->jenis_kelamin,
            'No_MR'          => $b->No_MR,
            'no_identitas'   => $b->no_identitas,
            'diagnosa'       => $b->diagnosa,
            'kebutuhan_bed'  => $b->kebutuhan_bed,
            'nama_bed'       => $b->nama_bed,
            'asal_ruang'     => $b->asal_rujukan,
            'Dokter'         => null,
            'nama_karu'      => null,
            'status'         => $b->status,
            'created_at'     => $b->created_at?->format('d/m/Y H:i'),
            'created_at_raw' => $b->created_at?->format('Y-m-d'),
        ]);

        // ── Format internal ───────────────────────────────────────────────
        $listInt = $intList->map(function ($s) use ($pasienMap) {
            $row       = $pasienMap[$s->No_MR] ?? null;
            $statusKey = $s->status === 'pending_icu' ? 'pending_icu_int' : $s->status;

            return [
                'id'             => 'int_' . $s->id,
                'jalur'          => 'internal',
                'nama_pasien'    => $this->val($row, 'Nama_Pasien', '-'),
                'jenis_kelamin'  => $this->val($row, 'jenis_kelamin'),
                'No_MR'          => $s->No_MR,
                'Diagnosis'      => $s->Diagnosis,
                'diagnosa'       => $s->Diagnosis,
                'kebutuhan_bed'  => $s->kebutuhan_bed,
                'nama_bed'       => $s->nama_bed,
                'asal_ruang'     => $s->asal_ruang,
                'Dokter'         => $s->Dokter,
                'nama_karu'      => null,
                'status'         => $statusKey,
                'created_at'     => $s->created_at?->format('d/m/Y H:i'),
                'created_at_raw' => $s->created_at?->format('Y-m-d'),
            ];
        });

        $listAktif = collect($listExt->values()->all())
            ->merge(collect($listInt->values()->all()))
            ->sortByDesc(fn($item) => $item['created_at_raw'] ?? '')
            ->values();

        return [
            'semuaKamar'    => $semuaKamar,
            'statsExternal' => $statsExternal,
            'statsInternal' => $statsInternal,
            'listAktif'     => $listAktif,
            'userRole'      => $role,
            'filters'       => [
                'tgl_dari'   => $tglDari,
                'tgl_sampai' => $tglSampai,
                'search'     => $search,
            ],
        ];
    }

    private function resolveActorNames(User $user): array
    {
        $names = [$user->name];
        if ($user->keycloak_username) $names[] = $user->keycloak_username;
        if ($user->username && $user->username !== $user->name) $names[] = $user->username;
        return array_unique(array_filter($names));
    }
}