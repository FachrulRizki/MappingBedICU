<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;

class DashboardService
{
    public function getDashboardData(): array
    {
        $admissions = IcuAdmision::all();

        $grouped = [
            'daftar'      => [], 'igd_periksa' => [], 'spri_dibuat' => [],
            'waiting_icu' => [], 'booking_icu' => [], 'di_icu'      => [],
        ];
        foreach ($admissions as $a) {
            if (isset($grouped[$a->status])) $grouped[$a->status][] = $a;
        }

        $spriDiIcu = IcuSpriInternal::where('status', 'di_icu')
            ->get(['id', 'No_MR', 'No_Reg', 'allocated_bed_id', 'kebutuhan_bed']);
        $extDiIcu  = IcuBookingExternal::where('status', 'di_icu')
            ->get(['id', 'No_MR', 'nama_pasien', 'allocated_bed_id', 'kebutuhan_bed', 'jenis_kelamin']);

        // Kumpulkan semua No_MR untuk lookup nama pasien
        $allNoMR = collect()
            ->merge($admissions->pluck('No_MR'))
            ->merge($spriDiIcu->pluck('No_MR'))
            ->merge($extDiIcu->pluck('No_MR'))
            ->filter()->unique()->values();

        $pasienMap = RegistrasiPasien::whereIn('No_MR', $allNoMR)
            ->get(['No_MR', 'Nama_Pasien', 'jenis_kelamin'])
            ->keyBy('No_MR');

        $bedData    = MRuangMaster::bedIcuDenganStatus();
        $bedMap     = $bedData->keyBy('Kode_RuangM');

        // Peta bed → pasien aktif (dari semua jalur)
        $bedPasienMap = collect();

        // Dari IcuAdmision (jalur lama)
        foreach ($admissions->where('status', 'di_icu') as $a) {
            if ($a->allocated_bed_id) {
                $bedPasienMap->put($a->allocated_bed_id, [
                    'No_MR'         => $a->No_MR,
                    'nama_pasien'   => $pasienMap[$a->No_MR]?->Nama_Pasien ?? '-',
                    'jenis_kelamin' => $pasienMap[$a->No_MR]?->jenis_kelamin ?? null,
                    'jenis_icu'     => $a->required_bed_type,
                    'sumber'        => 'lama',
                ]);
            }
        }

        // Dari IcuSpriInternal (jalur internal)
        foreach ($spriDiIcu as $s) {
            if ($s->allocated_bed_id) {
                $bedPasienMap->put($s->allocated_bed_id, [
                    'No_MR'         => $s->No_MR,
                    'nama_pasien'   => $pasienMap[$s->No_MR]?->Nama_Pasien ?? '-',
                    'jenis_kelamin' => $pasienMap[$s->No_MR]?->jenis_kelamin ?? null,
                    'jenis_icu'     => $s->kebutuhan_bed,
                    'sumber'        => 'internal',
                ]);
            }
        }

        // Dari IcuBookingExternal (jalur external)
        foreach ($extDiIcu as $b) {
            if ($b->allocated_bed_id) {
                $bedPasienMap->put($b->allocated_bed_id, [
                    'No_MR'         => $b->No_MR,
                    'nama_pasien'   => $pasienMap[$b->No_MR]?->Nama_Pasien ?? $b->nama_pasien,
                    'jenis_kelamin' => $pasienMap[$b->No_MR]?->jenis_kelamin ?? $b->jenis_kelamin,
                    'jenis_icu'     => $b->kebutuhan_bed,
                    'sumber'        => 'external',
                ]);
            }
        }

        // Format semua kamar dengan enriched data pasien
        $semuaKamar = $bedData->map(function ($row) use ($bedPasienMap) {
            $pasienData = $bedPasienMap->get($row->Kode_RuangM);
            return [
                'Kode_Ruang'    => $row->Kode_RuangM,
                'Status'        => $row->Status ?? 'KOSONG',
                'nama_ruang'    => $row->Nama_RuangM,
                'kode_kelas'    => $row->kelas_master ?? $row->Kode_Kelas,
                'nama_kelas'    => $row->Nama_Kelas,
                'No_MR'         => $pasienData['No_MR'] ?? ($row->No_MR ?? null),
                'nama_pasien'   => $pasienData['nama_pasien'] ?? null,
                'jenis_kelamin' => $pasienData['jenis_kelamin'] ?? null,
                'jenis_icu'     => $pasienData['jenis_icu'] ?? null,
                'sumber'        => $pasienData['sumber'] ?? null,
            ];
        })->values();

        $kamarKosong = $semuaKamar->where('Status', 'KOSONG')->values();

        $totalDiIcu = $admissions->where('status', 'di_icu')->count()
            + $spriDiIcu->count()
            + $extDiIcu->count();

        // tahapDiIcu untuk dashboard pipeline — jalur lama saja
        // jalur baru ditampilkan di menu masing-masing (SpriInternal, BookingExternal)
        $allNoReg = $admissions->pluck('No_Reg')->filter()->unique()->values();
        $pendaftaranMap = \App\Models\Pendaftaran::whereIn('No_Reg', $allNoReg)
            ->get()->keyBy('No_Reg');

        return [
            // Pipeline jalur lama (IcuAdmision)
            'tahapDaftar'      => $this->formatList($grouped['daftar'],      $pasienMap, $pendaftaranMap),
            'tahapIgd'         => $this->formatList($grouped['igd_periksa'], $pasienMap, $pendaftaranMap),
            'tahapSpri'        => $this->formatList($grouped['spri_dibuat'], $pasienMap, $pendaftaranMap),
            'tahapNungguKamar' => $this->formatList($grouped['waiting_icu'], $pasienMap, $pendaftaranMap),
            'tahapBooking'     => $this->formatList($grouped['booking_icu'], $pasienMap, $pendaftaranMap),
            'tahapDiIcu'       => $this->formatList($grouped['di_icu'],      $pasienMap, $pendaftaranMap),

            // Denah bed
            'semuaKamar'  => $semuaKamar,
            'kamarKosong' => $kamarKosong,

            // Dropdown jenis ICU dari M_KELAS (via join)
            'masterKelas' => MRuangMaster::jenisIcuTersedia(),

            // Stats card
            'statsExternal' => [
                'proses'  => IcuBookingExternal::whereNotIn('status', ['di_icu','ditolak','pulang'])->count(),
                'di_icu'  => $extDiIcu->count(),
            ],
            'statsInternal' => [
                'proses'  => IcuSpriInternal::whereNotIn('status', ['di_icu','ditolak','pulang'])->count(),
                'di_icu'  => $spriDiIcu->count(),
            ],
            'totalDiIcu'    => $totalDiIcu,
        ];
    }

    private function formatList($list, $pasienMap, $pendaftaranMap): \Illuminate\Support\Collection
    {
        return collect($list)->map(function ($a) use ($pasienMap, $pendaftaranMap) {
            $pasien = $pasienMap[$a->No_MR]  ?? null;
            $daftar = $pendaftaranMap[$a->No_Reg] ?? null;
            return [
                'id'            => $a->id,
                'No_Reg'        => $a->No_Reg,
                'No_MR'         => $a->No_MR,
                'status'        => $a->status,
                'nama_pasien'   => $pasien?->Nama_Pasien ?? $a->nama_pasien_ext ?? '-',
                'jenis_kelamin' => $pasien?->jenis_kelamin ?? null,
                'diagnosis'     => $daftar?->Diagnosis   ?? null,
                'indikasi'      => $daftar?->IndikasiRI  ?? null,
            ];
        })->values();
    }
}
