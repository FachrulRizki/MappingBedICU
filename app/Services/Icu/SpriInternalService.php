<?php

namespace App\Services\Icu;

use App\Models\IcuSpriInternal;
use App\Models\StatusKamar;
use Illuminate\Validation\ValidationException;

class SpriInternalService
{
    public function __construct(
        private readonly BedStatusService $bedStatus
    ) {}

    public function buatSpri(array $data): IcuSpriInternal
    {
        return IcuSpriInternal::create([
            'No_MR'         => $data['No_MR'],
            'No_Reg'        => $data['No_Reg'],
            'Diagnosis'     => $data['Diagnosis'],
            'IndikasiRI'    => $data['IndikasiRI'],
            'kebutuhan_bed' => $data['kebutuhan_bed'] ?? null,
            'asal_ruang'    => $data['asal_ruang']   ?? null,
            'Dokter'        => $data['Dokter']        ?? null,
            'spesialis'     => $data['spesialis']     ?? null,
            'Keterangan'    => $data['Keterangan']    ?? null,
            'NameUser'      => $data['NameUser']      ?? null,
            'status'        => 'pending_admisi',
        ]);
    }

    public function approveAdmisi(int $id, string $approvedBy, ?string $catatanAdmisi = null): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_admisi') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk approval admisi.']);
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
        $spri->update(['status' => 'ditolak', 'alasan_tolak' => $alasan, 'approved_by' => $approvedBy]);
        return $spri->fresh();
    }

    public function bookingBedIcu(int $id, string $kodeRuang, string $kebutuhanBed, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk booking bed.']);
        }

        // Validasi bed masih KOSONG
        $bed = StatusKamar::where('Kode_Ruang', $kodeRuang)->first();
        if ($bed && strtoupper($bed->Status) !== 'KOSONG') {
            throw ValidationException::withMessages([
                'Kode_Ruang' => 'Bed sudah tidak tersedia.',
            ]);
        }

        // Update status bed ke BOOKING — skip jika permission denied (staging)
        $this->bedStatus->setBooking($kodeRuang, $bookedBy);

        $spri->update([
            'status'           => 'bed_booked',
            'kebutuhan_bed'    => $kebutuhanBed,
            'allocated_bed_id' => $kodeRuang,
            'booked_by'        => $bookedBy,
        ]);

        return $spri->fresh();
    }

    public function catatTanpaBed(int $id, string $catatan, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);
        $spri->update(['Keterangan' => $catatan, 'booked_by' => $bookedBy]);
        return $spri->fresh();
    }

    public function tolakIcu(int $id, string $alasan, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->allocated_bed_id) {
            $this->bedStatus->setKosong($spri->allocated_bed_id);
        }

        $spri->update([
            'status'           => 'ditolak',
            'alasan_tolak'     => $alasan,
            'allocated_bed_id' => null,
            'booked_by'        => $bookedBy,
        ]);

        return $spri->fresh();
    }

    public function konfirmasiMasuk(int $id): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'bed_booked') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi masuk.']);
        }

        // Update bed ke ISI — skip jika permission denied
        if ($spri->allocated_bed_id) {
            $this->bedStatus->setTerisi($spri->allocated_bed_id, $spri->No_MR);
        }

        $spri->update(['status' => 'di_icu']);
        return $spri->fresh();
    }

    public function pulangkan(int $id): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->allocated_bed_id) {
            $this->bedStatus->setKosong($spri->allocated_bed_id);
        }

        $spri->update(['status' => 'pulang', 'allocated_bed_id' => null]);
        return $spri->fresh();
    }
}
