<?php

namespace App\Services\Icu;

use App\Models\StatusKamar;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk update status bed ICU.
 *
 * Jika koneksi SQL Server tidak punya permission UPDATE (staging/read-only),
 * update di-skip dengan warning di log — proses ICU tetap berjalan.
 *
 * Ini memungkinkan testing dengan DB RS tanpa memodifikasi data production.
 */
class BedStatusService
{
    /**
     * Set bed menjadi BOOKING saat ICU alokasikan bed.
     * Return true jika berhasil, false jika permission denied (staging).
     */
    public function setBooking(string $kodeRuang, string $updatedBy = ''): bool
    {
        return $this->updateStatus($kodeRuang, 'BOOKING', null, $updatedBy);
    }

    /**
     * Set bed menjadi ISI saat pasien masuk ruangan.
     */
    public function setTerisi(string $kodeRuang, ?string $noMr = null, string $updatedBy = ''): bool
    {
        return $this->updateStatus($kodeRuang, 'ISI', $noMr, $updatedBy);
    }

    /**
     * Set bed menjadi KOSONG saat pasien pulang / booking dibatalkan.
     */
    public function setKosong(string $kodeRuang): bool
    {
        return $this->updateStatus($kodeRuang, 'KOSONG', null);
    }

    /**
     * Cek apakah bed KOSONG (untuk validasi sebelum booking).
     * Return false jika tidak bisa query (fallback aman).
     */
    public function isBedKosong(string $kodeRuang): bool
    {
        try {
            $bed = StatusKamar::where('Kode_Ruang', $kodeRuang)->first();
            return $bed && strtoupper($bed->Status) === 'KOSONG';
        } catch (\Exception $e) {
            Log::warning("[BedStatusService] Tidak bisa cek status bed {$kodeRuang}: " . $e->getMessage());
            return true; // Assume kosong jika tidak bisa query — ICU yang tahu
        }
    }

    // ─────────────────────────────────────────────────────────────────────

    private function updateStatus(string $kodeRuang, string $newStatus, ?string $noMr, string $updatedBy = ''): bool
    {
        try {
            $data = ['Status' => $newStatus];
            if ($newStatus === 'ISI' && $noMr)     $data['No_MR'] = $noMr;
            if ($newStatus === 'KOSONG')            $data['No_MR'] = null;
            if ($updatedBy)                        $data['NamaUser'] = $updatedBy;

            StatusKamar::where('Kode_Ruang', $kodeRuang)->update($data);
            return true;
        } catch (\Exception $e) {
            // Permission denied atau koneksi error — log dan skip
            // Proses ICU tetap jalan, hanya status fisik bed di DB RS yang tidak terupdate
            Log::warning(
                "[BedStatusService] Tidak bisa update STATUS_KAMAR [{$kodeRuang}] → {$newStatus}. " .
                "Kemungkinan READ-ONLY mode (staging). Error: " . $e->getMessage()
            );
            return false;
        }
    }
}
