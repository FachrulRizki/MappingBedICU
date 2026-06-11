<?php

namespace App\Services\Icu;

use App\Models\StatusKamar;
use Illuminate\Support\Facades\Log;

class BedStatusService
{
    public function setBooking(string $kodeRuang, string $updatedBy = ''): bool
    {
        return $this->updateStatus($kodeRuang, 'BOOKING', null, $updatedBy);
    }

    public function setTerisi(string $kodeRuang, ?string $noMr = null, string $updatedBy = ''): bool
    {
        return $this->updateStatus($kodeRuang, 'ISI', $noMr, $updatedBy);
    }

    public function setKosong(string $kodeRuang): bool
    {
        return $this->updateStatus($kodeRuang, 'KOSONG', null);
    }

    public function isBedKosong(string $kodeRuang): bool
    {
        try {
            $bed = StatusKamar::where('Kode_Ruang', $kodeRuang)->first();
            return $bed && strtoupper($bed->Status) === 'KOSONG';
        } catch (\Exception $e) {
            Log::warning("[BedStatusService] Tidak bisa cek status bed {$kodeRuang}: " . $e->getMessage());
            return true;
        }
    }

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
            Log::warning(
                "[BedStatusService] Tidak bisa update STATUS_KAMAR [{$kodeRuang}] → {$newStatus}. " .
                "Kemungkinan READ-ONLY mode (staging). Error: " . $e->getMessage()
            );
            return false;
        }
    }
}
