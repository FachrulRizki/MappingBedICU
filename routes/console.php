<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync status bed ICU setiap 30 detik
Schedule::command('icu:sync-bed')->everyThirtySeconds()->withoutOverlapping(1);
