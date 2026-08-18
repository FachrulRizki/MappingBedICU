<?php

namespace App\Console\Commands;

use App\Services\Icu\SpriErmSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncSpriErm extends Command
{
    protected $signature   = 'icu:sync-spri-erm {--force : Paksa sync meski lock aktif}';
    protected $description = 'Sync permintaan ICU dari ASESMEN_SURAT_PERMINTAAN_RI (ERM/IGD)';

    public function __construct(private readonly SpriErmSyncService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $lockKey = 'icu_spri_erm_sync_running';
        $ttl     = 60;

        if (! $this->option('force') && Cache::has($lockKey)) {
            $this->line('[icu:sync-spri-erm] Sync sedang berjalan, skip.');
            return self::SUCCESS;
        }

        Cache::put($lockKey, true, $ttl);

        try {
            $inserted = $this->service->sync();

            if ($inserted > 0) {
                $this->info("[icu:sync-spri-erm] {$inserted} data baru dari ERM diinsert: " . now()->format('H:i:s'));
            } else {
                $this->line('[icu:sync-spri-erm] Tidak ada data baru: ' . now()->format('H:i:s'));
            }

            Cache::put('icu_spri_erm_last_sync', now()->timestamp, 120);
        } finally {
            Cache::forget($lockKey);
        }

        return self::SUCCESS;
    }
}
