<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;
use App\Models\Spri;

class SpriService
{
    /**
     * Buat / update SPRI untuk admision, lalu pindahkan status ke 'spri_dibuat'.
     *
     * @return array{admision: IcuAdmision, namaKelas: string}
     */
    public function buatSpri(
        int    $admisionId,
        string $diagnosis,
        string $indikasiRI,
        string $requiredBedType,
        string $keterangan = '-',
        string $dokter     = 'Dokter IGD',
        string $spesialis  = '-'
    ): array {
        $admision = IcuAdmision::with('pendaftaran')->findOrFail($admisionId);

        Spri::updateOrCreate(
            ['No_Reg' => $admision->No_Reg],
            [
                'Diagnosis'  => $diagnosis,
                'IndikasiRI' => $indikasiRI,
                'spesialis'  => $spesialis,
                'Dokter'     => $dokter,
                'NameUser'   => 'dr_igd',
                'Perawatan'  => 'ICU',
                'Keterangan' => $keterangan,
                'Status'     => 'draft',
            ]
        );

        $admision->update([
            'status'            => 'spri_dibuat',
            'required_bed_type' => $requiredBedType,
            'match_status'      => 'waiting',
        ]);

        // required_bed_type sekarang sudah berisi Nama_Kelas langsung
        return [
            'admision'  => $admision->fresh(),
            'namaKelas' => $requiredBedType,
        ];
    }

    /**
     * Approve SPRI: update status SPRI → 'approved', admision → 'waiting_icu'.
     */
    public function approveSpri(int $admisionId): IcuAdmision
    {
        $admision = IcuAdmision::with('pendaftaran.spriAktif', 'pasien')->findOrFail($admisionId);

        if ($admision->pendaftaran?->spriAktif) {
            $admision->pendaftaran->spriAktif->update(['Status' => 'approved']);
        }

        $admision->update(['status' => 'waiting_icu']);

        return $admision->fresh('pasien');
    }
}
