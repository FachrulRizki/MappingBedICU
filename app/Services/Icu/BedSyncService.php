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

    /** Panggil masuk + keluar sekaligus */
    public function sync(): void
    {
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
                } elseif ($statusBed === 'KOSONG' || $statusBed === null) {
                    // Bed Management release / bed tidak dikenal → langsung selesai
                    $booking->update([
                        'status'    => 'selesai',
                        'keluar_at' => now(),
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
                // Status BOOKING: bed masih di-hold oleh sistem lain, biarkan
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
                } elseif ($statusBed === 'KOSONG' || $statusBed === null) {
                    $bu->update([
                        'status'    => 'selesai',
                        'keluar_at' => now(),
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
                // Status BOOKING: biarkan
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
