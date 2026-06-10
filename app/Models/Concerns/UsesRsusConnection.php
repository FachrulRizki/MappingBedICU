<?php

namespace App\Models\Concerns;

trait UsesRsusConnection
{
    /** Apakah driver SQL Server tersedia? */
    public static function rsusAvailable(): bool
    {
        return extension_loaded('pdo_sqlsrv');
    }

    public function getConnectionName(): ?string
    {
        return static::rsusAvailable() ? 'sqlsrv_rsus' : 'mysql';
    }

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }
        return static::rsusAvailable()
            ? ($this->rsusTable  ?? parent::getTable())
            : ($this->localTable ?? parent::getTable());
    }

    public static function connectionName(): string
    {
        return static::rsusAvailable() ? 'sqlsrv_rsus' : 'mysql';
    }

    public static function tableName(string $rsusTable, string $localTable): string
    {
        return static::rsusAvailable() ? $rsusTable : $localTable;
    }
}
