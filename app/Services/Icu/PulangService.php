<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;

class PulangService
{
    public function pulangkanPasien(int $admisionId): IcuAdmision
    {
        $admision = IcuAdmision::with('bed.ruang', 'pasien')->findOrFail($admisionId);

        if ($admision->bed) {
            $admision->bed->update([
                'Status' => 'KOSONG',
                'No_MR'  => null,
            ]);
        }

        $admision->update([
            'status'           => 'pulang',
            'allocated_bed_id' => null,
            'match_status'     => null,
        ]);

        return $admision->fresh('pasien');
    }
}