<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;
use App\Models\MKelas;
use App\Models\StatusKamar;

class DashboardService
{
    /** Eager load standar untuk semua query admision */
    private array $withAdmision = [
        'pasien',
        'pendaftaran',
        'pendaftaran.spriAktif',
        'bed',
        'bed.ruang',
        'bed.ruang.kelas',
    ];

    /**
     * Ambil semua data yang dibutuhkan dashboard, sudah diformat untuk Inertia.
     */
    public function getDashboardData(): array
    {
        $w = $this->withAdmision;

        $tahapDaftar      = IcuAdmision::with($w)->where('status', 'daftar')->get();
        $tahapIgd         = IcuAdmision::with($w)->where('status', 'igd_periksa')->get();
        $tahapSpri        = IcuAdmision::with($w)->where('status', 'spri_dibuat')->get();
        $tahapNungguKamar = IcuAdmision::with($w)->where('status', 'waiting_icu')->get();
        $tahapBooking     = IcuAdmision::with($w)->where('status', 'booking_icu')->get();
        $tahapDiIcu       = IcuAdmision::with($w)->where('status', 'di_icu')->get();

        $semuaKamar  = StatusKamar::with(['ruang.kelas', 'icuAdmision.pasien'])->get();
        $kamarKosong = StatusKamar::with(['ruang.kelas', 'icuAdmision.pasien'])->where('Status', 'KOSONG')->get();
        $masterKelas = MKelas::all();

        return [
            'tahapDaftar'      => $tahapDaftar->map(fn($a) => $this->formatAdmision($a))->values(),
            'tahapIgd'         => $tahapIgd->map(fn($a) => $this->formatAdmision($a))->values(),
            'tahapSpri'        => $tahapSpri->map(fn($a) => $this->formatAdmision($a))->values(),
            'tahapNungguKamar' => $tahapNungguKamar->map(fn($a) => $this->formatAdmision($a))->values(),
            'tahapBooking'     => $tahapBooking->map(fn($a) => $this->formatAdmision($a))->values(),
            'tahapDiIcu'       => $tahapDiIcu->map(fn($a) => $this->formatAdmision($a))->values(),
            'semuaKamar'       => $semuaKamar->map(fn($k) => $this->formatKamar($k))->values(),
            'kamarKosong'      => $kamarKosong->map(fn($k) => $this->formatKamar($k))->values(),
            'masterKelas'      => $masterKelas->map(fn($k) => [
                'kode' => $k->Kode_Kelas,
                'nama' => $k->Nama_Kelas,
            ])->values(),
        ];
    }

    /** Format satu IcuAdmision menjadi array plain untuk Vue */
    public function formatAdmision(IcuAdmision $a): array
    {
        return [
            'id'                => $a->id,
            'No_Reg'            => $a->No_Reg,
            'No_MR'             => $a->No_MR,
            'status'            => $a->status,
            'required_bed_type' => $a->required_bed_type,
            'allocated_bed_id'  => $a->allocated_bed_id,
            'match_status'      => $a->match_status,
            'nama_pasien'       => $a->pasien?->Nama_Pasien ?? '-',
            'diagnosis'         => $a->pendaftaran?->spriAktif?->Diagnosis ?? null,
            'indikasi'          => $a->pendaftaran?->spriAktif?->IndikasiRI ?? null,
            'nama_bed'          => $a->bed?->ruang?->Nama_RuangM ?? $a->allocated_bed_id,
            'kode_kelas_bed'    => $a->bed?->ruang?->Kode_Kelas ?? null,
        ];
    }

    /** Format satu StatusKamar menjadi array plain untuk Vue */
    public function formatKamar(StatusKamar $k): array
    {
        // Ambil data pasien yang sedang menempati bed ini (status di_icu)
        $admision       = $k->icuAdmision;
        $pasien         = $admision?->pasien;
        $jenisKelamin   = $pasien?->jenis_kelamin; // 'L' | 'P' | null

        return [
            'Kode_Ruang'    => $k->Kode_Ruang,
            'Status'        => $k->Status,
            'No_MR'         => $k->No_MR,
            'nama_ruang'    => $k->ruang?->Nama_RuangM ?? $k->Kode_Ruang,
            'kode_kelas'    => $k->ruang?->Kode_Kelas ?? null,
            'nama_kelas'    => $k->ruang?->kelas?->Nama_Kelas ?? null,
            'nama_pasien'   => $pasien?->Nama_Pasien ?? null,
            'jenis_kelamin' => $jenisKelamin,
        ];
    }
}
