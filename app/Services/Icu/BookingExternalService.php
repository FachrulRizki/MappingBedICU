<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\RegistrasiPasien;
use Illuminate\Validation\ValidationException;

class BookingExternalService
{
    /** Admisi — buat booking baru. */
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
            'kebutuhan_bed'    => null,   // ditentukan ICU
            'jaminan'          => $data['jaminan']          ?? null,
            'catatan_jaminan'  => $data['catatan_jaminan']  ?? null,
            'keterangan'       => $data['keterangan']       ?? null,
            'status'           => 'pending_icu',
            'created_by'       => $data['created_by']       ?? null,
        ]);
    }

    public function konfirmasiIcu(
        int    $id,
        string $kodeRuang,
        string $namaBed,
        string $kebutuhanBed,
        string $confirmedBy
    ): IcuBookingExternal {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            throw ValidationException::withMessages([
                'status' => 'Hanya booking dengan status Menunggu ICU yang bisa dikonfirmasi.',
            ]);
        }

        $booking->update([
            'status'           => 'bed_confirmed',
            'kebutuhan_bed'    => $kebutuhanBed,
            'allocated_bed_id' => $kodeRuang,
            'nama_bed'         => $namaBed,
            'confirmed_by'     => $confirmedBy,
        ]);

        return $booking->fresh();
    }

    public function tolakIcu(int $id, string $alasan, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            throw ValidationException::withMessages([
                'status' => 'Hanya booking yang menunggu ICU yang bisa ditolak.',
            ]);
        }

        $booking->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $alasan,
            'confirmed_by' => $confirmedBy,
        ]);

        return $booking->fresh();
    }

    public function verifikasiAdmisi(
        int     $id,
        string  $noMr,
        ?string $noReg,
        string  $verifiedBy
    ): IcuBookingExternal {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'bed_confirmed') {
            throw ValidationException::withMessages([
                'status' => 'Hanya booking yang sudah dikonfirmasi ICU yang bisa diverifikasi.',
            ]);
        }

        // Validasi No_MR exists di DB RS
        $pasien = RegistrasiPasien::where('No_MR', $noMr)->first();
        if (! $pasien) {
            throw ValidationException::withMessages([
                'No_MR' => "No. MR '{$noMr}' tidak ditemukan di sistem.",
            ]);
        }

        $booking->update([
            'status'      => 'admisi_verified',
            'No_MR'       => $noMr,
            'No_Reg'      => $noReg,
            'verified_by' => $verifiedBy,
        ]);

        return $booking->fresh();
    }
}
