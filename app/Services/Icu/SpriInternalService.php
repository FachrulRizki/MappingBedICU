<?php

namespace App\Services\Icu;

use App\Models\IcuSpriInternal;
use Illuminate\Validation\ValidationException;

class SpriInternalService
{
    /** Petugas ruang — buat SPRI baru. */
    public function buatSpri(array $data): IcuSpriInternal
    {
        return IcuSpriInternal::create([
            'No_MR'      => $data['No_MR'],
            'No_Reg'     => $data['No_Reg'],
            'Diagnosis'  => $data['Diagnosis'],
            'IndikasiRI' => $data['IndikasiRI'],
            'asal_ruang' => $data['asal_ruang'] ?? null,
            'Dokter'     => $data['Dokter']      ?? null,
            'spesialis'  => $data['spesialis']   ?? null,
            'Keterangan' => $data['Keterangan']  ?? null,
            'NameUser'   => $data['NameUser']     ?? null,
            'status'     => 'pending_admisi',
        ]);
    }

    public function approveAdmisi(int $id, string $approvedBy, ?string $catatanAdmisi = null): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_admisi') {
            throw ValidationException::withMessages([
                'status' => 'Hanya SPRI yang menunggu admisi yang bisa disetujui.',
            ]);
        }

        $spri->update([
            'status'         => 'pending_icu',
            'approved_by'    => $approvedBy,
            'catatan_admisi' => $catatanAdmisi,
        ]);

        return $spri->fresh();
    }

    public function tolakAdmisi(int $id, string $alasan, string $approvedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        $spri->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $alasan,
            'approved_by'  => $approvedBy,
        ]);

        return $spri->fresh();
    }

    public function verifikasiBedIcu(
        int    $id,
        string $kodeRuang,
        string $namaBed,
        string $kebutuhanBed,
        string $verifiedBy
    ): IcuSpriInternal {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            throw ValidationException::withMessages([
                'status' => 'Hanya SPRI yang menunggu ICU yang bisa diverifikasi.',
            ]);
        }

        $spri->update([
            'status'           => 'bed_verified',
            'kebutuhan_bed'    => $kebutuhanBed,
            'allocated_bed_id' => $kodeRuang,
            'nama_bed'         => $namaBed,
            'verified_by'      => $verifiedBy,
        ]);

        return $spri->fresh();
    }

    public function tolakIcu(int $id, string $alasan, string $verifiedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            throw ValidationException::withMessages([
                'status' => 'Hanya SPRI yang menunggu ICU yang bisa ditolak.',
            ]);
        }

        $spri->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $alasan,
            'verified_by'  => $verifiedBy,
        ]);

        return $spri->fresh();
    }
}
