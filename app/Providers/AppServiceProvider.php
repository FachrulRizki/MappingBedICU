<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── SQL Server driver check ────────────────────────────────────────
        if (! extension_loaded('pdo_sqlsrv')) {
            Log::channel('single')->warning(
                '[DB_RSUS] Driver pdo_sqlsrv tidak ditemukan. ' .
                'Model RS (RegistrasiPasien, Pendaftaran, StatusKamar, MKelas, MRuangMaster) ' .
                'menggunakan MySQL lokal sebagai fallback. ' .
                'Install driver SQL Server untuk connect ke 192.168.200.160:1433.'
            );
        }

        // ── Keycloak Socialite Provider (Laravel 11 style) ─────────────────
        // Tidak pakai EventServiceProvider karena Laravel 11 sudah remove default-nya.
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('keycloak', \SocialiteProviders\Keycloak\Provider::class);
        });
    }
}
