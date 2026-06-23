<?php

namespace App\Models\Concerns;

use App\Services\RsusConnectionService;

/**
 * Trait untuk model yang membaca data dari DB RS.
 *
 * Mode dikontrol via DB_RSUS_ENABLED di .env:
 *   true / auto+reachable → sqlsrv_rsus  (SQL Server RS live)
 *   false / auto+offline  → mysql        (tabel lokal, data dari seeder)
 *
 * Setiap model wajib mendefinisikan:
 *   protected string $rsusTable  = 'NAMA_TABEL_SQLSERVER';  (prod)
 *   protected string $localTable = 'nama_tabel_mysql';      (dev lokal)
 */
trait UsesRsusConnection
{
    public function getConnectionName(): string
    {
        return static::rsusAvailable() ? 'sqlsrv_rsus' : config('database.default');
    }

    public function getTable(): string
    {
        if (static::rsusAvailable()) {
            return $this->rsusTable ?? parent::getTable();
        }
        return $this->localTable ?? parent::getTable();
    }

    public static function rsusAvailable(): bool
    {
        return app(RsusConnectionService::class)->isAvailable();
    }

    /**
     * Versi static murni — aman dipanggil dari Seeder tanpa instance model.
     */
    public static function rsusIsAvailableStatic(): bool
    {
        return app(RsusConnectionService::class)->isAvailable();
    }

    /**
     * Nama koneksi aktif (untuk dipakai di raw DB::connection() calls).
     */
    public static function activeConnection(): string
    {
        return static::rsusAvailable() ? 'sqlsrv_rsus' : config('database.default');
    }

    /**
     * Helper SQL null-coalesce yang kompatibel antar driver.
     * SQL Server: ISNULL(a, b) | MySQL: COALESCE(a, b)
     */
    public static function sqlNull(string $col, string $fallback): string
    {
        return static::rsusAvailable()
            ? "ISNULL({$col}, {$fallback})"
            : "COALESCE({$col}, {$fallback})";
    }
}
