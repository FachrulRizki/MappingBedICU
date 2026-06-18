<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboardData(?User $user = null): array
    {
        $role           = $user?->role ?? 'guest';
        $isPetugasRuang = $role === 'petugas_ruang';

        // ── Info bed (semua role lihat bed) ───────────────────────────────
        $bedData    = MRuangMaster::bedIcuDenganStatus();
        $semuaKamar = $bedData->map(fn($row) => [
            'Kode_Ruang' => $row->Kode_RuangM,
            'Status'     => $row->Status ?? 'KOSONG',
            'nama_ruang' => $row->Nama_RuangM,
            'kode_kelas' => $row->kelas_master ?? $row->Kode_Kelas,
            'nama_kelas' => $row->Nama_Kelas,
            'No_MR'      => $row->No_MR ?? null,
        ])->values();

        // ── Stats & list aktif berdasarkan role ────────────────────────────
        if ($isPetugasRuang && $user) {
            $actorNames = $this->resolveActorNames($user);
            $col        = $this->nameUserColumn();

            // Petugas ruang: hanya SPRI milik sendiri, tidak ada booking external
            $statsExternal = ['pending' => 0, 'bed_confirmed' => 0, 'terverifikasi' => 0];
            $statsInternal = [
                'pending_admisi' => IcuSpriInternal::whereIn($col, $actorNames)->where('status', 'pending_admisi')->count(),
                'pending_icu'    => IcuSpriInternal::whereIn($col, $actorNames)->where('status', 'pending_icu')->count(),
                'bed_verified'   => IcuSpriInternal::whereIn($col, $actorNames)->where('status', 'bed_verified')->count(),
            ];

            $extList = collect();
            $intList = IcuSpriInternal::whereIn($col, $actorNames)
                ->whereIn('status', ['pending_admisi', 'pending_icu', 'bed_verified'])
                ->latest()->get();
        } else {
            // Admin, admisi, ICU → lihat semua
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
                ->latest()->get();
            $intList = IcuSpriInternal::whereIn('status', ['pending_admisi', 'pending_icu', 'bed_verified'])
                ->latest()->get();
        }

        // ── Lookup nama pasien internal ────────────────────────────────────
        $noMRs     = $intList->pluck('No_MR')->filter()->unique()->values();
        $pasienMap = RegistrasiPasien::whereIn('No_MR', $noMRs)
            ->get(['No_MR', 'Nama_Pasien', 'jenis_kelamin'])
            ->keyBy('No_MR');

        // ── Format external ────────────────────────────────────────────────
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
            'status'         => $b->status,
            'created_at'     => $b->created_at?->format('d/m/Y H:i'),
            'created_at_raw' => $b->created_at?->format('Y-m-d'),
        ]);

        // ── Format internal ────────────────────────────────────────────────
        $listInt = $intList->map(function ($s) use ($pasienMap) {
            $pasien    = $pasienMap[$s->No_MR] ?? null;
            $statusKey = $s->status === 'pending_icu' ? 'pending_icu_int' : $s->status;
            return [
                'id'             => 'int_' . $s->id,
                'jalur'          => 'internal',
                'nama_pasien'    => $pasien?->Nama_Pasien ?? '-',
                'jenis_kelamin'  => $pasien?->jenis_kelamin,
                'No_MR'          => $s->No_MR,
                'Diagnosis'      => $s->Diagnosis,
                'kebutuhan_bed'  => $s->kebutuhan_bed,
                'nama_bed'       => $s->nama_bed,
                'status'         => $statusKey,
                'created_at'     => $s->created_at?->format('d/m/Y H:i'),
                'created_at_raw' => $s->created_at?->format('Y-m-d'),
            ];
        });

        $listAktif = $listExt->merge($listInt)->sortByDesc('created_at_raw')->values();

        return [
            'semuaKamar'    => $semuaKamar,
            'statsExternal' => $statsExternal,
            'statsInternal' => $statsInternal,
            'listAktif'     => $listAktif,
            'userRole'      => $role,
        ];
    }

    private function resolveActorNames(User $user): array
    {
        $names = [$user->name];
        if ($user->keycloak_username) $names[] = $user->keycloak_username;
        if ($user->username && $user->username !== $user->name) $names[] = $user->username;
        return array_unique(array_filter($names));
    }

    private function nameUserColumn(): string
    {
        static $col = null;
        if ($col !== null) return $col;
        try {
            $cols = DB::connection('mysql')->getSchemaBuilder()->getColumnListing('icu_spri_internal');
            $col  = in_array('NamaUser', $cols) ? 'NamaUser' : 'NameUser';
        } catch (\Exception) {
            $col = 'NameUser';
        }
        return $col;
    }
}
