<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cek apakah koneksi ke SQL Server RS (sqlsrv_rsus) tersedia.
 *
 * Perilaku dikontrol via .env:
 *   DB_RSUS_ENABLED=true   → paksa aktif (tidak cek koneksi)
 *   DB_RSUS_ENABLED=false  → paksa nonaktif, semua query RS return kosong
 *   DB_RSUS_ENABLED=auto   → cek koneksi aktual (default)
 *
 * Hasil ping di-cache 30 detik agar tidak membebani setiap request.
 */
class RsusConnectionService
{
    public function isAvailable(): bool
    {
        $mode = env('DB_RSUS_ENABLED', 'auto');

        if ($mode === 'false' || $mode === false) {
            return false;
        }

        if ($mode === 'true' || $mode === true) {
            return true;
        }

        // Mode auto — cek koneksi aktual, cache 30 detik
        return Cache::remember('rsus_db_available', 30, fn () => $this->ping());
    }

    private function ping(): bool
    {
        $host = env('DB_RSUS_HOST', '');
        $port = (int) env('DB_RSUS_PORT', 1433);

        if (! $host) {
            return false;
        }

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                return true;
            }
            Log::debug("[RSUS] Ping gagal ({$errno}): {$errstr}");
            return false;
        } catch (\Throwable $e) {
            Log::debug('[RSUS] Ping exception: ' . $e->getMessage());
            return false;
        }
    }
}
