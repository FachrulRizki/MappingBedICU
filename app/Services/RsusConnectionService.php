<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RsusConnectionService
{
    public function isAvailable(): bool
    {
        $mode = config('database.connections.sqlsrv_rsus.enabled', 'auto');

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
        $host = config('database.connections.sqlsrv_rsus.host', '');
        $port = (int) config('database.connections.sqlsrv_rsus.port', 1433);

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
