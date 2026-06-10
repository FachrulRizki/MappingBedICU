<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\StatusKamar;
use Illuminate\Validation\ValidationException;


class BookingExternalService
{
    public function buatBooking(array $data): IcuBookingExternal
    {
        return IcuBookingExternal::create([
            'nama_pasien'      => $data['nama_pasien'],
            'jenis_kelamin'    => $data['jenis_kelamin'],
            'no_identitas'     => $data['no_identitas']     ?? null,
            'asal_rujukan'     => $data['asal_rujukan']     ?? null,
            'no_telp_keluarga' => $data['no_telp_keluarga'] ?? null,
            'diagnosa'         => $data['diagnosa'],
            'rencana_tindakan' => $data['rencana_tindakan'],
            'kebutuhan_bed'    => $data['kebutuhan_bed'],
            'jaminan'          => $data['jaminan']          ?? null,
            'catatan_jaminan'  => $data['catatan_jaminan']  ?? null,
            'keterangan'       => $data['keterangan']       ?? null,
            'status'           => 'pending_icu',  // langsung ke ICU, tanpa step admisi tambahan
            'created_by'       => $data['created_by'] ?? null,
        ]);
    }

    public function konfirmasiIcu(int $id, string $kodeRuang, string $kebutuhanBed, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi ICU.']);
        }

        // ICU bebas memilih bed — ICU yang paling tahu kondisi
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

        $booking->update([
            'status'           => 'bed_confirmed',
            'kebutuhan_bed'    => $kebutuhanBed,   // ICU tentukan jenis saat konfirmasi
            'allocated_bed_id' => $kodeRuang,
            'confirmed_by'     => $confirmedBy,
        ]);

        return $booking->fresh(['bed.ruang.kelas']);
    }

    public function catatTanpaBed(int $id, string $catatan, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        $booking->update([
            'keterangan'   => $catatan,
            'confirmed_by' => $confirmedBy,
            // status tetap pending_icu
        ]);

        return $booking->fresh();
    }

    public function tolakIcu(int $id, string $alasan, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::with('bed')->findOrFail($id);

        // Kembalikan bed jika sudah sempat di-booking
        if ($booking->bed && $booking->bed->Status === 'BOOKING') {
            $booking->bed->update(['Status' => 'KOSONG']);
        }

        $booking->update([
            'status'           => 'ditolak',
            'alasan_tolak'     => $alasan,
            'allocated_bed_id' => null,
            'confirmed_by'     => $confirmedBy,
        ]);

        return $booking->fresh();
    }

    public function konfirmasiMasuk(int $id, ?string $noMr = null, ?string $noReg = null): IcuBookingExternal
    {
        $booking = IcuBookingExternal::with('bed')->findOrFail($id);

        if ($booking->status !== 'bed_confirmed') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi masuk.']);
        }

        // Link No_MR jika pasien sudah terdaftar di sistem (opsional)
        $updateData = ['status' => 'di_icu'];
        if ($noMr)  $updateData['No_MR']  = $noMr;
        if ($noReg) $updateData['No_Reg'] = $noReg;

        if ($booking->bed) {
            $booking->bed->update([
                'Status' => 'ISI',
                'No_MR'  => $noMr ?? $booking->No_MR,
            ]);
        }

        $booking->update($updateData);

        return $booking->fresh(['bed.ruang', 'pasien']);
    }

    public function pulangkan(int $id): IcuBookingExternal
    {
        $booking = IcuBookingExternal::with('bed')->findOrFail($id);

        if ($booking->bed) {
            $booking->bed->update(['Status' => 'KOSONG', 'No_MR' => null]);
        }

        $booking->update(['status' => 'pulang', 'allocated_bed_id' => null]);

        return $booking->fresh('pasien');
    }
}
