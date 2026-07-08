<?php

namespace App\Models\Concerns;

trait UsesRsusConnection
{
    public static function isSqlServer(): bool
    {
        $driver = config('database.connections.' . config('database.connections.sqlsrv_rsus.driver', 'none') . '.driver');
        return config('database.default') === 'sqlsrv'
            || config('database.connections.sqlsrv_rsus.driver') === 'sqlsrv';
    }

    public function getConnectionName(): string
    {
        return 'sqlsrv_rsus';
    }

    public function getTable(): string
    {
        $driver = config('database.connections.sqlsrv_rsus.driver', 'mysql');

        if ($driver === 'sqlsrv') {
            return $this->rsusTable ?? parent::getTable();
        }

        return $this->localTable ?? parent::getTable();
    }

    /**
     * Nama koneksi aktif (untuk raw DB::connection() calls).
     */
    public static function activeConnection(): string
    {
        return 'sqlsrv_rsus';
    }

    /**
     * Helper SQL null-coalesce kompatibel antar driver.
     */
    public static function sqlNull(string $col, string $fallback): string
    {
        $driver = config('database.connections.sqlsrv_rsus.driver', 'mysql');
        return $driver === 'sqlsrv'
            ? "ISNULL({$col}, {$fallback})"
            : "COALESCE({$col}, {$fallback})";
    }

    public static function rsusIsSqlServer(): bool
    {
        return config('database.connections.sqlsrv_rsus.driver', 'mysql') === 'sqlsrv';
    }

    public static function rsusAvailable(): bool
    {
        return static::rsusIsSqlServer();
    }

    public static function rsusIsAvailableStatic(): bool
    {
        return static::rsusIsSqlServer();
    }
}
