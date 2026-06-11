<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;

class TransferService
{
    public function masukRuangan(int $admisionId): IcuAdmision
    {
        $admision = IcuAdmision::with('bed.ruang', 'pasien')->findOrFail($admisionId);

        if ($admision->bed) {
            $admision->bed->update([
                'Status' => 'ISI',
                'No_MR'  => $admision->No_MR,
            ]);
        }

        $admision->update(['status' => 'di_icu']);

        return $admision->fresh(['bed.ruang', 'pasien']);
    }
}
