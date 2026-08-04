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
     * Deteksi pasien yang sudah menempati bed (STATUS_KAMAR = ISI).
     * Status 'bed_confirmed' / 'admisi_verified' / 'bed_verified' → 'masuk_icu'
     */
    public function syncMasukIcu(): void
    {
        try {
            $activeExternal = IcuBookingExternal::whereIn('status', ['bed_confirmed', 'admisi_verified'])
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'nama_pasien', 'nama_bed']);

            $activeInternal = IcuSpriInternal::where('status', 'bed_verified')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed']);

            if ($activeExternal->isEmpty() && $activeInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($activeExternal->pluck('allocated_bed_id'))
                ->concat($activeInternal->pluck('allocated_bed_id'))
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            $statusMap = StatusKamar::whereIn('Kode_Ruang', $kodeRuangs)
                ->pluck('Status', 'Kode_Ruang')
                ->map(fn ($s) => strtoupper($s ?? ''))
                ->toArray();

            foreach ($activeExternal as $booking) {
                $statusBed = $statusMap[$booking->allocated_bed_id] ?? null;
                if ($statusBed === 'ISI') {
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
            }

            foreach ($activeInternal as $bu) {
                $statusBed = $statusMap[$bu->allocated_bed_id] ?? null;
                if ($statusBed === 'ISI') {
                    $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);
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
            }
        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::syncMasukIcu] ' . $e->getMessage());
        }
    }

    /**
     * Deteksi pasien yang sudah keluar dari bed (STATUS_KAMAR = KOSONG).
     * Status 'masuk_icu' → 'selesai' — otomatis masuk Laporan Pasien Keluar.
     */
    public function syncKeluarIcu(): void
    {
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
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            $statusMap = StatusKamar::whereIn('Kode_Ruang', $kodeRuangs)
                ->pluck('Status', 'Kode_Ruang')
                ->map(fn ($s) => strtoupper($s ?? ''))
                ->toArray();

            $now = now();

            foreach ($masukExternal as $booking) {
                $statusBed = $statusMap[$booking->allocated_bed_id] ?? null;
                if ($statusBed === 'KOSONG') {
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
