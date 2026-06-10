<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\StatusKamar;
use Illuminate\Validation\ValidationException;

class BookingExternalService
{
    public function __construct(
        private readonly BedStatusService $bedStatus
    ) {}

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
            'kebutuhan_bed'    => $data['kebutuhan_bed']    ?? null,
            'jaminan'          => $data['jaminan']          ?? null,
            'catatan_jaminan'  => $data['catatan_jaminan']  ?? null,
            'keterangan'       => $data['keterangan']       ?? null,
            'status'           => 'pending_icu',
            'created_by'       => $data['created_by']       ?? null,
        ]);
    }

    public function konfirmasiIcu(int $id, string $kodeRuang, string $kebutuhanBed, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi ICU.']);
        }

        // Validasi bed masih KOSONG (skip jika tidak bisa query)
        $bed = StatusKamar::where('Kode_Ruang', $kodeRuang)->first();
        if ($bed && strtoupper($bed->Status) !== 'KOSONG') {
            throw ValidationException::withMessages([
                'Kode_Ruang' => 'Bed sudah tidak tersedia.',
            ]);
        }

        // Update status bed — skip jika permission denied (staging read-only)
        $this->bedStatus->setBooking($kodeRuang, $confirmedBy);

        $booking->update([
            'status'           => 'bed_confirmed',
            'kebutuhan_bed'    => $kebutuhanBed,
            'allocated_bed_id' => $kodeRuang,
            'confirmed_by'     => $confirmedBy,
        ]);

        return $booking->fresh();
    }

    public function catatTanpaBed(int $id, string $catatan, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);
        $booking->update(['keterangan' => $catatan, 'confirmed_by' => $confirmedBy]);
        return $booking->fresh();
    }

    public function tolakIcu(int $id, string $alasan, string $confirmedBy): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        // Kembalikan bed ke KOSONG jika sudah di-booking
        if ($booking->allocated_bed_id) {
            $this->bedStatus->setKosong($booking->allocated_bed_id);
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
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'bed_confirmed') {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai untuk konfirmasi masuk.']);
        }

        $updateData = ['status' => 'di_icu'];
        if ($noMr)  $updateData['No_MR']  = $noMr;
        if ($noReg) $updateData['No_Reg'] = $noReg;

        // Update status bed ke ISI — skip jika permission denied
        if ($booking->allocated_bed_id) {
            $this->bedStatus->setTerisi(
                $booking->allocated_bed_id,
                $noMr ?? $booking->No_MR,
            );
        }

        $booking->update($updateData);
        return $booking->fresh();
    }

    public function pulangkan(int $id): IcuBookingExternal
    {
        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->allocated_bed_id) {
            $this->bedStatus->setKosong($booking->allocated_bed_id);
        }

        $booking->update(['status' => 'pulang', 'allocated_bed_id' => null]);
        return $booking->fresh();
    }
}
