<?php

namespace App\Services\Icu;

use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\StatusKamar;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Log;

class BedSyncService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    /** Panggil masuk + keluar + heal sekaligus */
    public function sync(): void
    {
        $this->syncHealSelesai(); // koreksi data lama yang salah status dulu
        $this->syncMasukIcu();
        $this->syncKeluarIcu();
    }

    /**
     * Apakah berjalan di production.
     * - production : ikuti STATUS_KAMAR sepenuhnya (ISI → masuk_icu, KOSONG → selesai)
     * - local/dev  : skip sync — tidak ada Bed Management aktif, data tidak boleh auto-pindah
     */
    private function isProduction(): bool
    {
        return app()->environment('production');
    }

    public function syncHealSelesai(): void
    {
        if (! $this->isProduction()) {
            return;
        }

        try {
            // Ambil pasien selesai yang masih punya allocated_bed_id
            $selesaiExternal = IcuBookingExternal::where('status', 'selesai')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'nama_pasien', 'nama_bed', 'masuk_at']);

            $selesaiInternal = IcuSpriInternal::where('status', 'selesai')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed', 'masuk_at']);

            if ($selesaiExternal->isEmpty() && $selesaiInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($selesaiExternal->pluck('allocated_bed_id'))
                ->concat($selesaiInternal->pluck('allocated_bed_id'))
                ->unique()->filter()->values()->toArray();

            $statusMap = [];
            try {
                $statusMap = StatusKamar::whereIn('Kode_Ruang', $kodeRuangs)
                    ->pluck('Status', 'Kode_Ruang')
                    ->map(fn ($s) => strtoupper($s ?? ''))
                    ->toArray();
            } catch (\Throwable $e) {
                Log::warning('[BedSyncService::syncHealSelesai] Gagal query StatusKamar: ' . $e->getMessage());
                return;
            }

            foreach ($selesaiExternal as $booking) {
                $statusBed = $statusMap[$booking->allocated_bed_id] ?? null;
                if ($statusBed === 'ISI') {
                    // Bed masih terisi → pasien seharusnya masih di ICU, bukan selesai
                    // Koreksi status tanpa duplikasi log activity
                    $booking->update([
                        'status'    => 'masuk_icu',
                        'keluar_at' => null,
                        'keluar_by' => null,
                        'masuk_at'  => $booking->masuk_at ?? now(),
                        'masuk_by'  => $booking->masuk_by ?? 'system_heal',
                    ]);
                    Log::info("[BedSyncService::syncHealSelesai] Ext #{$booking->id} ({$booking->nama_pasien}) dikoreksi selesai→masuk_icu — bed {$booking->allocated_bed_id} masih ISI.");
                }
            }

            foreach ($selesaiInternal as $bu) {
                $statusBed = $statusMap[$bu->allocated_bed_id] ?? null;
                if ($statusBed === 'ISI') {
                    $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);
                    $bu->update([
                        'status'    => 'masuk_icu',
                        'keluar_at' => null,
                        'keluar_by' => null,
                        'masuk_at'  => $bu->masuk_at ?? now(),
                        'masuk_by'  => $bu->masuk_by ?? 'system_heal',
                    ]);
                    Log::info("[BedSyncService::syncHealSelesai] Int #{$bu->id} ({$namaPasien}) dikoreksi selesai→masuk_icu — bed {$bu->allocated_bed_id} masih ISI.");
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::syncHealSelesai] ' . $e->getMessage());
        }
    }

    public function syncMasukIcu(): void
    {
        // Di lokal tidak ada Bed Management aktif — skip agar data tidak auto-pindah
        if (! $this->isProduction()) {
            return;
        }

        try {
            $activeExternal = IcuBookingExternal::whereIn('status', ['bed_confirmed', 'admisi_verified'])
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'nama_pasien', 'nama_bed', 'confirmed_at', 'verified_at']);

            $activeInternal = IcuSpriInternal::where('status', 'bed_verified')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed', 'verified_at']);

            if ($activeExternal->isEmpty() && $activeInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($activeExternal->pluck('allocated_bed_id'))
                ->concat($activeInternal->pluck('allocated_bed_id'))
                ->unique()->filter()->values()->toArray();

            $statusMap = [];
            try {
                $statusMap = StatusKamar::whereIn('Kode_Ruang', $kodeRuangs)
                    ->pluck('Status', 'Kode_Ruang')
                    ->map(fn ($s) => strtoupper($s ?? ''))
                    ->toArray();
            } catch (\Throwable $e) {
                Log::warning('[BedSyncService::syncMasukIcu] Gagal query StatusKamar: ' . $e->getMessage());
                return;
            }

            foreach ($activeExternal as $booking) {
                $statusBed = $statusMap[$booking->allocated_bed_id] ?? null;

                if ($statusBed === 'ISI') {
                    // Bed Management sudah isi bed → pasien masuk ICU
                    $booking->update([
                        'status'   => 'masuk_icu',
                        'masuk_at' => now(),
                        'masuk_by' => 'system',
                    ]);
                    $this->activityLog->masukIcu(
                        $booking->id,
                        $booking->nama_pasien,
                        $booking->nama_bed ?? $booking->allocated_bed_id,
                        'booking_external',
                        'IcuBookingExternal'
                    );
                }
                // Status KOSONG/BOOKING/null saat masuk: bed belum aktif atau masih diproses
                // — jangan auto-selesai di sini, tunggu pasien benar-benar masuk ICU dulu
            }

            foreach ($activeInternal as $bu) {
                $statusBed = $statusMap[$bu->allocated_bed_id] ?? null;
                $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

                if ($statusBed === 'ISI') {
                    $bu->update([
                        'status'   => 'masuk_icu',
                        'masuk_at' => now(),
                        'masuk_by' => 'system',
                    ]);
                    $this->activityLog->masukIcu(
                        $bu->id,
                        $namaPasien,
                        $bu->nama_bed ?? $bu->allocated_bed_id,
                        'spri_internal',
                        'IcuSpriInternal'
                    );
                }
                // Status KOSONG/BOOKING/null saat masuk: biarkan, jangan auto-selesai
            }
        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::syncMasukIcu] ' . $e->getMessage());
        }
    }

    public function syncKeluarIcu(): void
    {
        // Di lokal tidak ada Bed Management aktif — skip
        if (! $this->isProduction()) {
            return;
        }

        try {
            $masukExternal = IcuBookingExternal::where('status', 'masuk_icu')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'nama_pasien', 'nama_bed', 'masuk_at']);

            $masukInternal = IcuSpriInternal::where('status', 'masuk_icu')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed', 'masuk_at']);

            if ($masukExternal->isEmpty() && $masukInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($masukExternal->pluck('allocated_bed_id'))
                ->concat($masukInternal->pluck('allocated_bed_id'))
                ->unique()->filter()->values()->toArray();

            $statusMap = [];
            try {
                $statusMap = StatusKamar::whereIn('Kode_Ruang', $kodeRuangs)
                    ->pluck('Status', 'Kode_Ruang')
                    ->map(fn ($s) => strtoupper($s ?? ''))
                    ->toArray();
            } catch (\Throwable $e) {
                Log::warning('[BedSyncService::syncKeluarIcu] Gagal query StatusKamar: ' . $e->getMessage());
                return;
            }

            $now = now();

            foreach ($masukExternal as $booking) {
                $statusBed = $statusMap[$booking->allocated_bed_id] ?? null;
                if ($statusBed === 'KOSONG') {
                    // Bed Management release bed → pasien keluar ICU
                    $booking->update([
                        'status'    => 'selesai',
                        'keluar_at' => $now,
                        'keluar_by' => 'system',
                    ]);
                    $this->activityLog->keluarIcu(
                        $booking->id,
                        $booking->nama_pasien,
                        $booking->nama_bed ?? $booking->allocated_bed_id,
                        'booking_external',
                        'IcuBookingExternal'
                    );
                }
            }

            foreach ($masukInternal as $bu) {
                $statusBed = $statusMap[$bu->allocated_bed_id] ?? null;
                if ($statusBed === 'KOSONG') {
                    $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);
                    $bu->update([
                        'status'    => 'selesai',
                        'keluar_at' => $now,
                        'keluar_by' => 'system',
                    ]);
                    $this->activityLog->keluarIcu(
                        $bu->id,
                        $namaPasien,
                        $bu->nama_bed ?? $bu->allocated_bed_id,
                        'spri_internal',
                        'IcuSpriInternal'
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::syncKeluarIcu] ' . $e->getMessage());
        }
    }
}
