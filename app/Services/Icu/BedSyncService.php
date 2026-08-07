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
        $this->syncKeluarIcu();
        $this->syncHealSelesai();
        $this->syncMasukIcu();
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

    /**
     * Ambil map STATUS_KAMAR lengkap: Kode_Ruang → ['status'=>..., 'no_mr'=>...]
     * Kolom No_MR di STATUS_KAMAR berisi No_MR pasien yang SAAT INI menempati bed tsb.
     */
    private function buildBedMap(array $kodeRuangs): array
    {
        if (empty($kodeRuangs)) return [];

        try {
            return StatusKamar::whereIn('Kode_Ruang', $kodeRuangs)
                ->get(['Kode_Ruang', 'Status', 'No_MR'])
                ->keyBy('Kode_Ruang')
                ->map(fn ($r) => [
                    'status' => strtoupper($r->Status ?? ''),
                    'no_mr'  => trim($r->No_MR ?? ''),
                ])
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::buildBedMap] ' . $e->getMessage());
            return [];
        }
    }

    public function syncHealSelesai(): void
    {
        if (! $this->isProduction()) {
            return;
        }

        try {
            $selesaiExternal = IcuBookingExternal::where('status', 'selesai')
                ->whereNotNull('allocated_bed_id')
                ->whereNotNull('No_MR')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_pasien', 'nama_bed', 'masuk_at', 'masuk_by']);

            $selesaiInternal = IcuSpriInternal::where('status', 'selesai')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed', 'masuk_at', 'masuk_by']);

            if ($selesaiExternal->isEmpty() && $selesaiInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($selesaiExternal->pluck('allocated_bed_id'))
                ->concat($selesaiInternal->pluck('allocated_bed_id'))
                ->unique()->filter()->values()->toArray();

            $bedMap = $this->buildBedMap($kodeRuangs);

            foreach ($selesaiExternal as $booking) {
                $bed = $bedMap[$booking->allocated_bed_id] ?? null;
                if (! $bed) continue;

                if ($bed['status'] === 'ISI' && $bed['no_mr'] === (string) $booking->No_MR) {
                    // Bed masih diisi pasien yang SAMA → koreksi ke masuk_icu
                    $booking->update([
                        'status'    => 'masuk_icu',
                        'keluar_at' => null,
                        'keluar_by' => null,
                        'masuk_at'  => $booking->masuk_at ?? now(),
                        'masuk_by'  => $booking->masuk_by ?? 'system_heal',
                    ]);
                    Log::info("[BedSyncService::syncHealSelesai] Ext #{$booking->id} ({$booking->nama_pasien}) dikoreksi selesai→masuk_icu — bed {$booking->allocated_bed_id} masih ISI oleh MR yang sama.");
                }
            }

            foreach ($selesaiInternal as $bu) {
                $bed = $bedMap[$bu->allocated_bed_id] ?? null;
                if (! $bed) continue;

                if ($bed['status'] === 'ISI' && $bed['no_mr'] === (string) $bu->No_MR) {
                    $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);
                    $bu->update([
                        'status'    => 'masuk_icu',
                        'keluar_at' => null,
                        'keluar_by' => null,
                        'masuk_at'  => $bu->masuk_at ?? now(),
                        'masuk_by'  => $bu->masuk_by ?? 'system_heal',
                    ]);
                    Log::info("[BedSyncService::syncHealSelesai] Int #{$bu->id} ({$namaPasien}) dikoreksi selesai→masuk_icu — bed {$bu->allocated_bed_id} masih ISI oleh MR yang sama.");
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
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_pasien', 'nama_bed', 'confirmed_at', 'verified_at']);

            $activeInternal = IcuSpriInternal::where('status', 'bed_verified')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed', 'verified_at']);

            if ($activeExternal->isEmpty() && $activeInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($activeExternal->pluck('allocated_bed_id'))
                ->concat($activeInternal->pluck('allocated_bed_id'))
                ->unique()->filter()->values()->toArray();

            $bedMap = $this->buildBedMap($kodeRuangs);

            foreach ($activeExternal as $booking) {
                $bed = $bedMap[$booking->allocated_bed_id] ?? null;
                if (! $bed) continue;

                if ($bed['status'] === 'ISI') {
                    $noMrBed     = $bed['no_mr'];
                    $noMrBooking = trim($booking->No_MR ?? '');

                    // Booking external dengan status bed_confirmed belum punya No_MR yang terverifikasi
                    // admisi. Hanya admisi_verified (sudah punya No_MR) yang boleh masuk ICU via sync.
                    if ($booking->status === 'bed_confirmed') {
                        // Jika No_MR booking kosong → belum diverifikasi admisi, tahan
                        if (! $noMrBooking) {
                            Log::info("[BedSyncService::syncMasukIcu] Ext #{$booking->id} ({$booking->nama_pasien}) HOLD — bed_confirmed belum terverifikasi admisi (No_MR kosong).");
                            continue;
                        }
                        // Jika No_MR booking ada tapi STATUS_KAMAR No_MR berbeda → bed diisi pasien lain
                        if ($noMrBed && $noMrBed !== $noMrBooking) {
                            Log::info("[BedSyncService::syncMasukIcu] Ext #{$booking->id} ({$booking->nama_pasien}) SKIP — bed {$booking->allocated_bed_id} ISI oleh MR lain ({$noMrBed} ≠ {$noMrBooking}).");
                            continue;
                        }
                    } else {
                        // admisi_verified: punya No_MR, lakukan pengecekan penuh
                        // Jika No_MR kosong (seharusnya tidak terjadi) → tahan
                        if (! $noMrBooking) {
                            Log::info("[BedSyncService::syncMasukIcu] Ext #{$booking->id} ({$booking->nama_pasien}) HOLD — admisi_verified tapi No_MR kosong, perlu dicek.");
                            continue;
                        }
                        // Jika STATUS_KAMAR tidak menyimpan No_MR → tidak bisa verifikasi siapa yang di bed
                        // Tahan agar tidak salah set masuk_icu untuk pasien yang bukan di bed ini
                        if (! $noMrBed) {
                            Log::info("[BedSyncService::syncMasukIcu] Ext #{$booking->id} ({$booking->nama_pasien}) HOLD — STATUS_KAMAR bed {$booking->allocated_bed_id} tidak ada No_MR, tidak bisa verifikasi.");
                            continue;
                        }
                        // Jika STATUS_KAMAR punya No_MR berbeda → bed diisi pasien lain
                        if ($noMrBed !== $noMrBooking) {
                            Log::info("[BedSyncService::syncMasukIcu] Ext #{$booking->id} ({$booking->nama_pasien}) SKIP masuk_icu — bed {$booking->allocated_bed_id} ISI oleh MR lain ({$noMrBed} ≠ {$noMrBooking}).");
                            continue;
                        }
                    }

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
                $bed = $bedMap[$bu->allocated_bed_id] ?? null;
                if (! $bed) continue;
                $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

                if ($bed['status'] === 'ISI') {
                    $noMrBed = $bed['no_mr'];
                    $noMrBu  = trim($bu->No_MR ?? '');

                    // Booking internal selalu punya No_MR
                    if (! $noMrBu) {
                        Log::info("[BedSyncService::syncMasukIcu] Int #{$bu->id} ({$namaPasien}) HOLD — No_MR kosong.");
                        continue;
                    }
                    // Jika STATUS_KAMAR tidak menyimpan No_MR → tidak bisa verifikasi
                    if (! $noMrBed) {
                        Log::info("[BedSyncService::syncMasukIcu] Int #{$bu->id} ({$namaPasien}) HOLD — STATUS_KAMAR bed {$bu->allocated_bed_id} tidak ada No_MR, tidak bisa verifikasi.");
                        continue;
                    }
                    // Jika No_MR berbeda → bed diisi pasien lain
                    if ($noMrBed !== $noMrBu) {
                        Log::info("[BedSyncService::syncMasukIcu] Int #{$bu->id} ({$namaPasien}) SKIP masuk_icu — bed {$bu->allocated_bed_id} ISI oleh MR lain ({$noMrBed} ≠ {$noMrBu}).");
                        continue;
                    }

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

    public function syncMasukIcuTanpaBed(): void
    {
        if (! $this->isProduction()) {
            return;
        }

        try {
            // Ambil semua No_MR yang saat ini ada di bed ICU (STATUS = ISI)
            $noMrDiBed = collect();
            try {
                $noMrDiBed = \App\Models\StatusKamar::where('Status', 'ISI')
                    ->whereNotNull('No_MR')
                    ->pluck('No_MR')
                    ->filter()
                    ->map(fn ($v) => trim($v))
                    ->unique()
                    ->values();
            } catch (\Throwable $e) {
                Log::warning('[BedSyncService::syncMasukIcuTanpaBed] Gagal query StatusKamar: ' . $e->getMessage());
                return;
            }

            $now = now();

            // External: masuk_icu tapi No_MR tidak ada di bed ICU manapun
            IcuBookingExternal::where('status', 'masuk_icu')
                ->whereNotNull('No_MR')
                ->whereNotIn('No_MR', $noMrDiBed->toArray())
                ->get(['id', 'No_MR', 'nama_pasien', 'nama_bed', 'allocated_bed_id'])
                ->each(function ($booking) use ($now) {
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
                    Log::info("[BedSyncService::syncMasukIcuTanpaBed] Ext #{$booking->id} ({$booking->nama_pasien}) set selesai — MR {$booking->No_MR} tidak ada di bed ICU manapun.");
                });

            // Internal: masuk_icu tapi No_MR tidak ada di bed ICU manapun
            IcuSpriInternal::where('status', 'masuk_icu')
                ->whereNotNull('No_MR')
                ->whereNotIn('No_MR', $noMrDiBed->toArray())
                ->get(['id', 'No_MR', 'nama_bed', 'allocated_bed_id'])
                ->each(function ($bu) use ($now) {
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
                    Log::info("[BedSyncService::syncMasukIcuTanpaBed] Int #{$bu->id} ({$namaPasien}) set selesai — MR {$bu->No_MR} tidak ada di bed ICU manapun.");
                });

        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::syncMasukIcuTanpaBed] ' . $e->getMessage());
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
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_pasien', 'nama_bed', 'masuk_at']);

            $masukInternal = IcuSpriInternal::where('status', 'masuk_icu')
                ->whereNotNull('allocated_bed_id')
                ->get(['id', 'allocated_bed_id', 'No_MR', 'nama_bed', 'masuk_at']);

            if ($masukExternal->isEmpty() && $masukInternal->isEmpty()) {
                return;
            }

            $kodeRuangs = collect($masukExternal->pluck('allocated_bed_id'))
                ->concat($masukInternal->pluck('allocated_bed_id'))
                ->unique()->filter()->values()->toArray();

            $bedMap = $this->buildBedMap($kodeRuangs);

            $now = now();

            foreach ($masukExternal as $booking) {
                $bed = $bedMap[$booking->allocated_bed_id] ?? null;
                if (! $bed) continue;

                $noMrBed     = $bed['no_mr'];
                $noMrBooking = trim($booking->No_MR ?? '');
                $bedStatus   = $bed['status'];

                $harus_keluar =
                    $bedStatus === 'KOSONG'
                    || ($bedStatus === 'ISI' && $noMrBed && $noMrBooking && $noMrBed !== $noMrBooking);

                if ($harus_keluar) {
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
                    if ($bedStatus === 'ISI') {
                        Log::info("[BedSyncService::syncKeluarIcu] Ext #{$booking->id} ({$booking->nama_pasien}) set selesai — bed {$booking->allocated_bed_id} ISI oleh MR lain ({$noMrBed}).");
                    }
                }
            }

            foreach ($masukInternal as $bu) {
                $bed = $bedMap[$bu->allocated_bed_id] ?? null;
                if (! $bed) continue;

                $noMrBed   = $bed['no_mr'];
                $noMrBu    = trim($bu->No_MR ?? '');
                $bedStatus = $bed['status'];
                $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

                $harus_keluar =
                    $bedStatus === 'KOSONG'
                    || ($bedStatus === 'ISI' && $noMrBed && $noMrBu && $noMrBed !== $noMrBu);

                if ($harus_keluar) {
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
                    if ($bedStatus === 'ISI') {
                        Log::info("[BedSyncService::syncKeluarIcu] Int #{$bu->id} ({$namaPasien}) set selesai — bed {$bu->allocated_bed_id} ISI oleh MR lain ({$noMrBed}).");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BedSyncService::syncKeluarIcu] ' . $e->getMessage());
        }
    }
}
