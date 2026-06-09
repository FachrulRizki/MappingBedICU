<?php

namespace App\Models\Concerns;

/**
 * Trait untuk model yang membaca dari DB staging RS (SQL Server).
 *
 * Jika pdo_sqlsrv ada  → sqlsrv_rsus + tabel uppercase (DB RS)
 * Jika pdo_sqlsrv TIDAK ada → mysql + tabel lowercase (MySQL lokal)
 *
 * Model wajib mendefinisikan:
 *   protected string $rsusTable  = 'NAMA_DI_SQLSERVER';
 *   protected string $localTable = 'nama_di_mysql';
 */
trait UsesRsusConnection
{
    /** Apakah driver SQL Server tersedia? */
    public static function rsusAvailable(): bool
    {
        return extension_loaded('pdo_sqlsrv');
    }

    /** Nama koneksi aktif */
    public function getConnectionName(): ?string
    {
        return static::rsusAvailable() ? 'sqlsrv_rsus' : 'mysql';
    }

    /** Nama tabel aktif */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }
        return static::rsusAvailable()
            ? ($this->rsusTable  ?? parent::getTable())
            : ($this->localTable ?? parent::getTable());
    }

    /** Nama koneksi sebagai string — untuk dipakai di validasi exists: */
    public static function connectionName(): string
    {
        return static::rsusAvailable() ? 'sqlsrv_rsus' : 'mysql';
    }

    /** Nama tabel sebagai string — untuk dipakai di validasi exists: */
    public static function tableName(string $rsusTable, string $localTable): string
    {
        return static::rsusAvailable() ? $rsusTable : $localTable;
    }
}
