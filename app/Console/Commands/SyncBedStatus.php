<?php

namespace App\Console\Commands;

use App\Services\Icu\BedSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncBedStatus extends Command
{
    protected $signature   = 'icu:sync-bed {--force : Paksa sync meski lock aktif}';
    protected $description = 'Sync status bed ICU dari Bed Management (STATUS_KAMAR)';

    public function __construct(private readonly BedSyncService $bedSync)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $lockKey = 'icu_bed_sync_running';
        $ttl     = 60;

        if (! $this->option('force') && Cache::has($lockKey)) {
            $this->line('[icu:sync-bed] Sync sedang berjalan, skip.');
            return self::SUCCESS;
        }

        Cache::put($lockKey, true, $ttl);

        try {
            $this->bedSync->sync();
            // Tandai waktu terakhir sync berhasil — dipakai controller untuk skip sync manual
            Cache::put('icu_bed_last_sync', now()->timestamp, 120);
            $this->info('[icu:sync-bed] Sync selesai: ' . now()->format('H:i:s'));
        } finally {
            Cache::forget($lockKey);
        }

        return self::SUCCESS;
    }
}
