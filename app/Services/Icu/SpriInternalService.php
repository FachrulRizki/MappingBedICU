<?php

namespace App\Services\Icu;

use App\Models\IcuSpriInternal;
use App\Models\StatusKamar;
use Illuminate\Validation\ValidationException;

class SpriInternalService
{
    public function buatSpri(array $data): IcuSpriInternal
    {
        return IcuSpriInternal::create([
            'No_MR'         => $data['No_MR'],
            'No_Reg'        => $data['No_Reg'],
            'Diagnosis'     => $data['Diagnosis'],
            'IndikasiRI'    => $data['IndikasiRI'],
            'kebutuhan_bed' => $data['kebutuhan_bed'],
            'asal_ruang'    => $data['asal_ruang']  ?? null,
            'Dokter'        => $data['Dokter']       ?? null,
            'spesialis'     => $data['spesialis']    ?? null,
            'Keterangan'    => $data['Keterangan']   ?? null,
            'NameUser'      => $data['NameUser']     ?? null,
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
            'status'          => 'pending_icu',
            'approved_by'     => $approvedBy,
            'catatan_admisi'  => $catatanAdmisi,  // catatan jaminan / kebutuhan dari admisi
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

    public function bookingBedIcu(int $id, string $kodeRuang, string $kebutuhanBed, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk booking bed.']);
        }

        // ICU bebas memilih bed — tidak ada filter matching kebutuhan
        // ICU yang paling tahu kondisi bed dan kebutuhan pasien
        $bed = StatusKamar::with('ruang.kelas')
            ->where('Kode_Ruang', $kodeRuang)
            ->where('Status', 'KOSONG')
            ->first();

        if (! $bed) {
            throw ValidationException::withMessages([
                'Kode_Ruang' => 'Bed tidak tersedia atau sudah terisi.',
            ]);
        }

        $bed->update(['Status' => 'BOOKING']);

        $spri->update([
            'status'           => 'bed_booked',
            'kebutuhan_bed'    => $kebutuhanBed,   // ICU tentukan jenis saat booking
            'allocated_bed_id' => $kodeRuang,
            'booked_by'        => $bookedBy,
        ]);

        return $spri->fresh(['bed.ruang.kelas']);
    }

    public function catatTanpaBed(int $id, string $catatan, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        $spri->update([
            'Keterangan' => $catatan,
            'booked_by'  => $bookedBy,
            // status tetap pending_icu — menunggu bed tersedia
        ]);

        return $spri->fresh();
    }

    public function tolakIcu(int $id, string $alasan, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::with('bed')->findOrFail($id);

        if ($spri->bed && $spri->bed->Status === 'BOOKING') {
            $spri->bed->update(['Status' => 'KOSONG']);
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
        $spri = IcuSpriInternal::with('bed')->findOrFail($id);

        // Terima dari bed_booked langsung (tidak perlu admisi_verified lagi)
        if ($spri->status !== 'bed_booked') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi masuk.']);
        }

        if ($spri->bed) {
            $spri->bed->update([
                'Status' => 'ISI',
                'No_MR'  => $spri->No_MR,
            ]);
        }

        $spri->update(['status' => 'di_icu']);

        return $spri->fresh(['bed.ruang', 'pasien']);
    }

    public function pulangkan(int $id): IcuSpriInternal
    {
        $spri = IcuSpriInternal::with('bed')->findOrFail($id);

        if ($spri->bed) {
            $spri->bed->update(['Status' => 'KOSONG', 'No_MR' => null]);
        }

        $spri->update(['status' => 'pulang', 'allocated_bed_id' => null]);

        return $spri->fresh('pasien');
    }
}
