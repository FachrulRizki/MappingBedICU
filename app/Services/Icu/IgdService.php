<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;

class IgdService
{
    public function kirimKeIgd(int $admisionId): IcuAdmision
    {
        $admision = IcuAdmision::with('pasien')->findOrFail($admisionId);

        $admision->update(['status' => 'igd_periksa']);

        return $admision->fresh('pasien');
    }
}
