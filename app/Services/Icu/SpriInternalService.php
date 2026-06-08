<?php

namespace App\Services\Icu;

use App\Models\IcuSpriInternal;
use App\Models\StatusKamar;
use Illuminate\Validation\ValidationException;

class SpriInternalService
{
    /**
     * LANGKAH 1 — Petugas ruang asal buat Surat Permintaan Rawat ICU.
     * Data pasien sudah ada (No_MR, No_Reg dari sistem existing).
     */
    public function buatSpri(array $data): IcuSpriInternal
    {
        return IcuSpriInternal::create([
            'No_MR'            => $data['No_MR'],
            'No_Reg'           => $data['No_Reg'],
            'Diagnosis'        => $data['Diagnosis'],
            'IndikasiRI'       => $data['IndikasiRI'],
            'kebutuhan_bed'    => $data['kebutuhan_bed'],
            'asal_ruang'       => $data['asal_ruang']    ?? null,
            'Dokter'           => $data['Dokter']        ?? null,
            'spesialis'        => $data['spesialis']     ?? null,
            'Keterangan'       => $data['Keterangan']    ?? null,
            'NameUser'         => $data['NameUser']      ?? null,
            'status'           => 'pending_admisi',       // langsung ke admisi
        ]);
    }

    /**
     * LANGKAH 2 — Admisi approve surat permintaan.
     */
    public function approveAdmisi(int $id, string $approvedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_admisi') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk approval admisi.']);
        }

        $spri->update([
            'status'      => 'pending_icu',
            'approved_by' => $approvedBy,
        ]);

        return $spri->fresh();
    }

    /**
     * LANGKAH 2b — Admisi tolak.
     */
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

    /**
     * LANGKAH 3 — ICU validasi dan booking bed.
     */
    public function bookingBedIcu(int $id, string $kodeRuang, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk booking bed.']);
        }

        // Validasi bed tersedia dan sesuai
        $bed = StatusKamar::with('ruang.kelas')
            ->where('Kode_Ruang', $kodeRuang)
            ->where('Status', 'KOSONG')
            ->whereHas('ruang.kelas', fn($q) => $q->where('Nama_Kelas', $spri->kebutuhan_bed))
            ->first();

        if (! $bed) {
            throw ValidationException::withMessages([
                'Kode_Ruang' => 'Bed tidak tersedia atau tidak sesuai kebutuhan.',
            ]);
        }

        $bed->update(['Status' => 'BOOKING']);

        $spri->update([
            'status'           => 'bed_booked',
            'allocated_bed_id' => $kodeRuang,
            'booked_by'        => $bookedBy,
        ]);

        return $spri->fresh(['bed.ruang.kelas']);
    }

    /**
     * LANGKAH 3b — ICU tolak (bed tidak ada).
     */
    public function tolakIcu(int $id, string $alasan, string $bookedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::with('bed')->findOrFail($id);

        // Kembalikan bed ke KOSONG jika sudah di-booking
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

    /**
     * LANGKAH 4 — Admisi verifikasi akhir (pasien siap diantar).
     */
    public function verifikasiAdmisi(int $id, string $verifiedBy): IcuSpriInternal
    {
        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'bed_booked') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk verifikasi admisi.']);
        }

        $spri->update([
            'status'      => 'admisi_verified',
            'verified_by' => $verifiedBy,
        ]);

        return $spri->fresh();
    }

    /**
     * LANGKAH 5 — ICU konfirmasi pasien tiba, bed terisi.
     */
    public function konfirmasiMasuk(int $id): IcuSpriInternal
    {
        $spri = IcuSpriInternal::with('bed')->findOrFail($id);

        if ($spri->status !== 'admisi_verified') {
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

    /**
     * Pulangkan pasien dari ICU — bed kembali kosong.
     */
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
