<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\StatusKamar;
use App\Models\MKelas;
use Illuminate\Validation\ValidationException;

class BookingExternalService
{
    /**
     * LANGKAH 1 — Admisi buat booking request.
     * Pasien belum terdaftar, belum punya No_MR.
     */
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
            'keterangan'       => $data['keterangan']       ?? null,
            'status'           => 'booking_request',
            'created_by'       => $data['created_by']       ?? null,
        ]);
    }

    /**
     * LANGKAH 2 — ICU konfirmasi ada bed.
     * ICU memilih bed yang akan dialokasikan.
     */
    public function konfirmasiIcu(int $id, string $kodeRuang, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi ICU.']);
        }

        // Validasi bed tersedia dan sesuai kebutuhan
        $bed = StatusKamar::with('ruang.kelas')
            ->where('Kode_Ruang', $kodeRuang)
            ->where('Status', 'KOSONG')
            ->whereHas('ruang.kelas', fn($q) => $q->where('Nama_Kelas', $booking->kebutuhan_bed))
            ->first();

        if (! $bed) {
            throw ValidationException::withMessages([
                'Kode_Ruang' => 'Bed tidak tersedia atau tidak sesuai kebutuhan.',
            ]);
        }

        // Tandai bed sebagai BOOKING
        $bed->update(['Status' => 'BOOKING']);

        $booking->update([
            'status'           => 'bed_confirmed',
            'allocated_bed_id' => $kodeRuang,
            'confirmed_by'     => $confirmedBy,
        ]);

        return $booking->fresh(['bed.ruang.kelas']);
    }

    /**
     * LANGKAH 2b — ICU tolak (bed tidak ada / kriteria tidak sesuai).
     */
    public function tolakIcu(int $id, string $alasan, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        $booking->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $alasan,
            'confirmed_by' => $confirmedBy,
        ]);

        return $booking->fresh();
    }

    /**
     * LANGKAH 3 — Admisi validasi konfirmasi ICU.
     * Pasien dalam perjalanan ke RS.
     */
    public function validasiAdmisi(int $id, string $validatedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'bed_confirmed') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk validasi admisi.']);
        }

        $booking->update([
            'status'       => 'admisi_validated',
            'validated_by' => $validatedBy,
        ]);

        return $booking->fresh();
    }

    /**
     * LANGKAH 4 — Pasien tiba di IGD, link ke No_MR yang baru dibuat.
     * No_MR dan No_Reg dari sistem existing (aplikasi pendaftaran lain).
     */
    public function linkPasienTiba(int $id, string $noMr, string $noReg): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'admisi_validated') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk link pasien.']);
        }

        $booking->update([
            'No_MR'  => $noMr,
            'No_Reg' => $noReg,
            'status' => 'pasien_tiba',
        ]);

        return $booking->fresh(['pasien', 'pendaftaran']);
    }

    /**
     * LANGKAH 5 — Admisi verifikasi bed (pasien sudah tiba, siap diantar).
     */
    public function verifikasiBed(int $id, string $validatedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pasien_tiba') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk verifikasi bed.']);
        }

        $booking->update([
            'status'       => 'bed_verified',
            'validated_by' => $validatedBy,
        ]);

        return $booking->fresh();
    }

    /**
     * LANGKAH 6 — Petugas antar pasien ke bed, ICU konfirmasi masuk.
     * Bed status berubah ISI.
     */
    public function konfirmasiMasuk(int $id): IcuBookingExternal
    {
        $booking = IcuBookingExternal::with('bed')->findOrFail($id);

        if ($booking->status !== 'bed_verified') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi masuk.']);
        }

        // Update bed → ISI
        if ($booking->bed) {
            $booking->bed->update([
                'Status' => 'ISI',
                'No_MR'  => $booking->No_MR,
            ]);
        }

        $booking->update(['status' => 'di_icu']);

        return $booking->fresh(['bed.ruang', 'pasien']);
    }

    /**
     * Kembalikan ke status pending_icu (dari booking_request).
     */
    public function kirimKeIcu(int $id): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);
        $booking->update(['status' => 'pending_icu']);
        return $booking->fresh();
    }

    /**
     * Pulangkan pasien dari ICU — bed kembali kosong.
     */
    public function pulangkan(int $id): IcuBookingExternal
    {
        $booking = IcuBookingExternal::with('bed')->findOrFail($id);

        if ($booking->bed) {
            $booking->bed->update(['Status' => 'KOSONG', 'No_MR' => null]);
        }

        $booking->update(['status' => 'pulang']);

        return $booking->fresh('pasien');
    }
}
