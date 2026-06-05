<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;
use App\Models\StatusKamar;
use Illuminate\Validation\ValidationException;

class BedService
{
    /**
     * Alokasikan bed ke pasien:
     * - Validasi bed KOSONG dan Kode_Kelas cocok
     * - Tandai bed → BOOKING
     * - Update admision → booking_icu
     *
     * @throws ValidationException jika bed tidak sesuai syarat
     * @return array{admision: IcuAdmision, bed: StatusKamar}
     */
    public function alokasiBed(int $admisionId, string $kodeRuang): array
    {
        $admision = IcuAdmision::with('pasien')->findOrFail($admisionId);

        $bed = StatusKamar::with('ruang.kelas')
            ->where('Kode_Ruang', $kodeRuang)
            ->where('Status', 'KOSONG')
            ->whereHas('ruang.kelas', fn($q) => $q->where('Nama_Kelas', $admision->required_bed_type))
            ->first();

        if (! $bed) {
            throw ValidationException::withMessages([
                'Kode_Ruang' => 'Bed tidak tersedia atau jenis tidak sesuai kebutuhan pasien.',
            ]);
        }

        $bed->update(['Status' => 'BOOKING']);

        $admision->update([
            'status'           => 'booking_icu',
            'allocated_bed_id' => $bed->Kode_Ruang,
            'match_status'     => 'matched',
        ]);

        return [
            'admision' => $admision->fresh('pasien'),
            'bed'      => $bed,
        ];
    }
}
