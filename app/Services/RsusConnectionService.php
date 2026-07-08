<?php

namespace App\Services;

class RsusConnectionService
{
    public function isAvailable(): bool
    {
        return config('database.connections.sqlsrv_rsus.driver', 'mysql') === 'sqlsrv';
    }
}
