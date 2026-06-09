<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! extension_loaded('pdo_sqlsrv')) {
            Log::channel('single')->warning(
                '[DB_RSUS] Driver pdo_sqlsrv tidak ditemukan. ' .
                'Model RS (RegistrasiPasien, Pendaftaran, StatusKamar, MKelas, MRuangMaster) ' .
                'menggunakan MySQL lokal sebagai fallback. ' .
                'Install driver SQL Server untuk connect ke 192.168.200.160:1433.'
            );
        }
    }
}
