<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use App\Services\ActivityLogService;
use App\Services\Icu\AntrianService;
use App\Services\Icu\BedSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MenuIcuController extends Controller
{
    public function __construct(
        private readonly AntrianService     $service,
        private readonly ActivityLogService $activityLog,
        private readonly BedSyncService     $bedSync,
    ) {}

    private function actor(): string
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return $user?->name ?? 'petugas_icu';
    }

    // READ
    public function index(Request $request): Response
    {
        // Sync hanya dijalankan jika scheduler belum jalan (dev/local) atau data sudah stale > 60 detik.
        $this->syncIfNeeded();

        $data = $this->service->build($request);

        // Cache StatusKamarMap 30 detik — tidak perlu query ulang setiap request
        $statusKamarMap = \Illuminate\Support\Facades\Cache::remember('status_kamar_map', 30, function () {
            return \App\Models\StatusKamar::all()
                ->keyBy('Kode_Ruang')
                ->map(fn ($r) => [
                    'status' => strtoupper($r->Status ?? ''),
                    'no_mr'  => trim($r->No_MR ?? ''),
                ])
                ->toArray();
        });

        return Inertia::render('Icu/MenuIcu', [
            'antrian'        => $data['antrian'],
            'summary'        => $data['summary'],
            'filters'        => $data['filters'],
            'kamarKosong'    => MRuangMaster::bedKosong(),
            'kamarTersedia'  => MRuangMaster::bedTersediaUntukKonfirmasi(),
            'masterKelas'    => MRuangMaster::jenisIcuTersedia(),
            'caraBayar'      => \App\Models\MCaraBayar::list(),
            'statusKamarMap' => $statusKamarMap,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Jalankan bedSync hanya jika:
     * - Environment lokal/dev (scheduler tidak jalan), ATAU
     * - Sync terakhir sudah lebih dari 60 detik yang lalu (fallback jika scheduler mati)
     */
    private function syncIfNeeded(): void
    {
        $lastSync = \Illuminate\Support\Facades\Cache::get('icu_bed_last_sync', 0);
        $stale    = (time() - $lastSync) > 60; // lebih dari 60 detik

        // Di production scheduler sudah handle — skip kecuali benar-benar stale
        if (app()->environment('production') && ! $stale) {
            return;
        }

        // Lock: hindari sync bersamaan dari beberapa request sekaligus
        $lock = \Illuminate\Support\Facades\Cache::lock('icu_bed_sync_running', 30);
        if (! $lock->get()) {
            return; // ada proses lain yang sync, lewati
        }

        try {
            $this->bedSync->sync();
            \Illuminate\Support\Facades\Cache::put('icu_bed_last_sync', time(), 120);
        } finally {
            $lock->release();
        }
    }

    // ACTION — Booking External: pending_icu / waiting_list -> bed_confirmed
    public function konfirmasiExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if (! in_array($booking->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'Booking tidak bisa dikonfirmasi dari status ini.');
        }

        // Cek bed ISI (sudah ada pasien fisik) — tidak bisa direkomendasikan
        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        if ($bed && $bed->isIsi()) {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        // Jika bed sudah dipegang pasien lain di tabel lokal → release pasien lama ke pending_icu
        $this->releasePemegangBed($v['Kode_Ruang'], $id, 'external');

        // Catat rekomendasi bed — TANPA menyentuh STATUS_KAMAR
        $booking->update([
            'status'           => 'bed_confirmed',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'confirmed_by'     => $this->actor(),
            'confirmed_at'     => now(),
        ]);

        $this->activityLog->konfirmasibed($booking->id, $booking->nama_pasien, $namaBed);

        return redirect()->route('icu.menu_icu')
            ->with('success', "Bed {$namaBed} ({$v['kebutuhan_bed']}) dikonfirmasi untuk {$booking->nama_pasien}. Admisi akan memproses melalui Bed Management.");
    }

    // ACTION — Booking External: tolak (pending_icu / waiting_list -> ditolak)
    public function tolakExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if (! in_array($booking->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'Booking tidak bisa ditolak dari status ini.');
        }

        $booking->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'confirmed_by' => $this->actor(),
        ]);

        $this->activityLog->tolakBookingIcu($booking->id, $booking->nama_pasien, $v['alasan_tolak']);

        return redirect()->route('icu.menu_icu')
            ->with('success', "Booking {$booking->nama_pasien} ditolak.");
    }

    // ACTION — BU Internal: pending_icu / waiting_list -> bed_verified
    public function verifikasiInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if (! in_array($bu->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'BU tidak bisa diverifikasi dari status ini.');
        }

        // Cek bed ISI (sudah ada pasien fisik) — tidak bisa direkomendasikan
        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        if ($bed && $bed->isIsi()) {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        // Jika bed sudah dipegang pasien lain di tabel lokal → release pasien lama ke pending_icu
        $this->releasePemegangBed($v['Kode_Ruang'], $id, 'internal');

        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        // Catat rekomendasi bed — TANPA menyentuh STATUS_KAMAR
        $bu->update([
            'status'           => 'bed_verified',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'verified_by'      => $this->actor(),
            'verified_at'      => now(),
        ]);

        $this->activityLog->verifikasibed($bu->id, $namaPasien, $namaBed);

        return redirect()->route('icu.menu_icu')
            ->with('success', "Bed {$namaBed} terverifikasi untuk {$namaPasien}. Admisi akan memproses melalui Bed Management.");
    }

    // ACTION — BU Internal: tolak (pending_icu / waiting_list -> ditolak)
    public function tolakInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if (! in_array($bu->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'BU tidak bisa ditolak dari status ini.');
        }

        $bu->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'verified_by'  => $this->actor(),
        ]);

        $this->activityLog->tolakSpriIcu($bu->id, (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR), $v['alasan_tolak']);

        return redirect()->route('icu.menu_icu')
            ->with('success', "BU {$bu->pasien?->Nama_Pasien} ditolak oleh ICU.");
    }

    // ACTION — Booking External: masuk waiting list (pending_icu -> waiting_list)
    public function waitingListExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'waiting_alasan'   => 'required|string|max:500',
            'waiting_estimasi' => 'required|date|after:now',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if (! in_array($booking->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'Booking sudah tidak berstatus Menunggu ICU.');
        }

        $booking->update([
            'status'           => 'waiting_list',
            'waiting_alasan'   => $v['waiting_alasan'],
            'waiting_estimasi' => $v['waiting_estimasi'],
            'waiting_by'       => $this->actor(),
        ]);

        $this->activityLog->log(
            'Waiting List',
            "Masukkan {$booking->nama_pasien} ke waiting list — estimasi: " .
                \Carbon\Carbon::parse($v['waiting_estimasi'])->format('d/m/Y H:i'),
            'booking_external',
            $booking->id,
            'IcuBookingExternal'
        );

        return redirect()->route('icu.menu_icu')
            ->with('success', "{$booking->nama_pasien} masuk Waiting List ICU.");
    }

    // ACTION — BU Internal: masuk waiting list (pending_icu -> waiting_list)
    public function waitingListInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'waiting_alasan'   => 'required|string|max:500',
            'waiting_estimasi' => 'required|date|after:now',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if ($bu->status !== 'pending_icu') {
            return back()->with('error', 'BU sudah tidak berstatus Menunggu ICU.');
        }

        $bu->update([
            'status'           => 'waiting_list',
            'waiting_alasan'   => $v['waiting_alasan'],
            'waiting_estimasi' => $v['waiting_estimasi'],
            'waiting_by'       => $this->actor(),
        ]);

        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        $this->activityLog->log(
            'Waiting List',
            "Masukkan {$namaPasien} ke waiting list — estimasi: " .
                \Carbon\Carbon::parse($v['waiting_estimasi'])->format('d/m/Y H:i'),
            'spri_internal',
            $bu->id,
            'IcuSpriInternal'
        );

        return redirect()->route('icu.menu_icu')
            ->with('success', "{$namaPasien} masuk Waiting List ICU.");
    }

    // ACTION — Booking External: pindah bed (bed_confirmed / admisi_verified / masuk_icu → bed baru)
    public function pindahBedExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
            'pindah_alasan' => 'nullable|string|max:500',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if (! in_array($booking->status, ['bed_confirmed', 'admisi_verified', 'masuk_icu'])) {
            return back()->with('error', 'Pindah bed hanya bisa dilakukan untuk pasien yang sudah dikonfirmasi bednya.');
        }

        if ($booking->allocated_bed_id === $v['Kode_Ruang']) {
            return back()->with('error', 'Bed baru tidak boleh sama dengan bed saat ini.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        // Untuk masuk_icu: bed tujuan boleh ISI jika diisi oleh sistem Bed Management sendiri
        // Untuk non-masuk_icu: bed tujuan harus KOSONG/BOOKING
        if ($booking->status !== 'masuk_icu' && $bed && $bed->isIsi()) {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        // Jika bed sudah dipegang pasien lain di tabel lokal → release pasien lama ke pending_icu
        $this->releasePemegangBed($v['Kode_Ruang'], $id, 'external');

        $bedLama = $booking->nama_bed ?? '—';

        // Untuk masuk_icu: status tetap masuk_icu, tidak di-reset ke bed_confirmed
        $updateData = [
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'pindah_alasan'    => $v['pindah_alasan'],
            'pindah_bed_lama'  => $bedLama,
            'pindah_by'        => $this->actor(),
            'pindah_at'        => now(),
        ];

        if ($booking->status !== 'masuk_icu') {
            $updateData['status']       = 'bed_confirmed';
            $updateData['confirmed_by'] = $this->actor();
            $updateData['confirmed_at'] = now();
        }

        $booking->update($updateData);

        $this->activityLog->pindahBedExt($booking->id, $booking->nama_pasien, $bedLama, $namaBed);

        $msg = $booking->status === 'masuk_icu'
            ? "Bed pasien {$booking->nama_pasien} diupdate: {$bedLama} → {$namaBed}. Perlu diproses di Bed Management."
            : "Rekomendasi bed pasien {$booking->nama_pasien} diubah: {$bedLama} → {$namaBed}. Admisi perlu memperbarui di Bed Management.";

        return redirect()->route('icu.menu_icu')->with('success', $msg);
    }

    // ACTION — BU Internal: pindah bed (bed_verified / masuk_icu → bed baru)
    public function pindahBedInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
            'pindah_alasan' => 'nullable|string|max:500',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if (! in_array($bu->status, ['bed_verified', 'masuk_icu'])) {
            return back()->with('error', 'Pindah bed hanya bisa dilakukan untuk pasien yang sudah diverifikasi bednya.');
        }

        if ($bu->allocated_bed_id === $v['Kode_Ruang']) {
            return back()->with('error', 'Bed baru tidak boleh sama dengan bed saat ini.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        if ($bu->status !== 'masuk_icu' && $bed && $bed->isIsi()) {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        // Jika bed sudah dipegang pasien lain di tabel lokal → release pasien lama ke pending_icu
        $this->releasePemegangBed($v['Kode_Ruang'], $id, 'internal');

        $bedLama    = $bu->nama_bed ?? '—';
        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        $updateData = [
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'pindah_alasan'    => $v['pindah_alasan'],
            'pindah_bed_lama'  => $bedLama,
            'pindah_by'        => $this->actor(),
            'pindah_at'        => now(),
        ];

        // Untuk masuk_icu: status tetap masuk_icu
        if ($bu->status !== 'masuk_icu') {
            $updateData['verified_by'] = $this->actor();
            $updateData['verified_at'] = now();
        }

        $bu->update($updateData);

        $this->activityLog->pindahBedInt($bu->id, $namaPasien, $bedLama, $namaBed);

        $msg = $bu->status === 'masuk_icu'
            ? "Bed pasien {$namaPasien} diupdate: {$bedLama} → {$namaBed}. Perlu diproses di Bed Management."
            : "Rekomendasi bed pasien {$namaPasien} diubah: {$bedLama} → {$namaBed}. Admisi perlu memperbarui di Bed Management.";

        return redirect()->route('icu.menu_icu')->with('success', $msg);
    }

    private function releasePemegangBed(string $kodeRuang, int $excludeId, string $excludeSumber): void
    {
        // Release external pemegang (selain pasien saat ini)
        IcuBookingExternal::whereIn('status', ['bed_confirmed', 'admisi_verified'])
            ->where('allocated_bed_id', $kodeRuang)
            ->when($excludeSumber === 'external', fn ($q) => $q->where('id', '!=', $excludeId))
            ->each(function (IcuBookingExternal $old) {
                $namaLama = $old->nama_pasien;
                $bedLama  = $old->nama_bed ?? $old->allocated_bed_id;
                $old->update([
                    'status'           => 'pending_icu',
                    'allocated_bed_id' => null,
                    'nama_bed'         => null,
                    'confirmed_by'     => null,
                    'confirmed_at'     => null,
                ]);
                \Illuminate\Support\Facades\Log::info("[releasePemegangBed] Ext #{$old->id} ({$namaLama}) di-release dari bed {$bedLama} → pending_icu");
                $this->activityLog->log(
                    'Release Bed',
                    "Bed {$bedLama} diambil alih. {$namaLama} dikembalikan ke antrian Menunggu ICU.",
                    'booking_external',
                    $old->id,
                    'IcuBookingExternal'
                );
            });

        // Release internal pemegang (selain pasien saat ini)
        IcuSpriInternal::where('status', 'bed_verified')
            ->where('allocated_bed_id', $kodeRuang)
            ->when($excludeSumber === 'internal', fn ($q) => $q->where('id', '!=', $excludeId))
            ->each(function (IcuSpriInternal $old) {
                $noMr    = $old->No_MR;
                $bedLama = $old->nama_bed ?? $old->allocated_bed_id;
                $old->update([
                    'status'           => 'pending_icu',
                    'allocated_bed_id' => null,
                    'nama_bed'         => null,
                    'verified_by'      => null,
                    'verified_at'      => null,
                ]);
                \Illuminate\Support\Facades\Log::info("[releasePemegangBed] Int #{$old->id} ({$noMr}) di-release dari bed {$bedLama} → pending_icu");
                $this->activityLog->log(
                    'Release Bed',
                    "Bed {$bedLama} diambil alih. Pasien {$noMr} dikembalikan ke antrian Menunggu ICU.",
                    'spri_internal',
                    $old->id,
                    'IcuSpriInternal'
                );
            });
    }
}
