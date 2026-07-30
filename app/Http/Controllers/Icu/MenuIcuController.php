<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use App\Services\ActivityLogService;
use App\Services\Icu\AntrianService;
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
        $data = $this->service->build($request);

        return Inertia::render('Icu/MenuIcu', [
            'antrian'        => $data['antrian'],
            'summary'        => $data['summary'],
            'filters'        => $data['filters'],
            'kamarKosong'    => MRuangMaster::bedKosong(),
            'kamarTersedia'  => MRuangMaster::bedTersediaUntukKonfirmasi(),
            'masterKelas'    => MRuangMaster::jenisIcuTersedia(),
            'caraBayar'      => \App\Models\MCaraBayar::list(),
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Helper terpusat: reset SEMUA pasien yang saat ini memegang bed tertentu
     */
    private function releaseAllHoldersOf(string $kodeRuang, string $namaBed, string $namaPasienBaru, ?int $excludeExtId = null, ?int $excludeIntId = null): array
    {
        $preempted = [];

        // Reset semua External yang memegang bed ini
        $holdersExt = IcuBookingExternal::where('allocated_bed_id', $kodeRuang)
            ->whereIn('status', ['bed_confirmed', 'admisi_verified'])
            ->when($excludeExtId, fn($q) => $q->where('id', '!=', $excludeExtId))
            ->get();

        foreach ($holdersExt as $h) {
            $preempted[] = $h->nama_pasien;
            $h->update([
                'status'           => 'pending_icu',
                'allocated_bed_id' => null,
                'nama_bed'         => null,
                'confirmed_by'     => null,
                'confirmed_at'     => null,
                'pindah_alasan'    => "Bed {$namaBed} diambil alih untuk {$namaPasienBaru} oleh {$this->actor()}",
                'pindah_bed_lama'  => $namaBed,
                'pindah_by'        => $this->actor(),
                'pindah_at'        => now(),
            ]);
            $this->activityLog->log('Preempt Bed', "Bed {$namaBed} diambil alih dari {$h->nama_pasien} untuk {$namaPasienBaru}", 'booking_external', $h->id, 'IcuBookingExternal');
        }

        // Reset semua Internal yang memegang bed ini
        $holdersInt = IcuSpriInternal::where('allocated_bed_id', $kodeRuang)
            ->where('status', 'bed_verified')
            ->when($excludeIntId, fn($q) => $q->where('id', '!=', $excludeIntId))
            ->get();

        foreach ($holdersInt as $h) {
            $namaPasienInt = (string) ($h->pasien?->Nama_Pasien ?? $h->No_MR);
            $preempted[] = $namaPasienInt;
            $h->update([
                'status'           => 'pending_icu',
                'allocated_bed_id' => null,
                'nama_bed'         => null,
                'verified_by'      => null,
                'verified_at'      => null,
                'pindah_alasan'    => "Bed {$namaBed} diambil alih untuk {$namaPasienBaru} oleh {$this->actor()}",
                'pindah_bed_lama'  => $namaBed,
                'pindah_by'        => $this->actor(),
                'pindah_at'        => now(),
            ]);
            $this->activityLog->log('Preempt Bed', "Bed {$namaBed} diambil alih dari {$namaPasienInt} untuk {$namaPasienBaru}", 'spri_internal', $h->id, 'IcuSpriInternal');
        }

        return $preempted;
    }

    // ACTION — Booking External: pending_icu -> bed_confirmed
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

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        // Bed ISI fisik — tidak bisa dipakai
        if ($bed && strtoupper($bed->Status) === 'ISI') {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        // Reset SEMUA pemegang bed ini dari tabel lokal (tanpa syarat status STATUS_KAMAR)
        $preempted = $this->releaseAllHoldersOf($v['Kode_Ruang'], $namaBed, $booking->nama_pasien, $booking->id, null);

        // Force STATUS_KAMAR → BOOKING
        StatusKamar::setBookingForce($v['Kode_Ruang'], $this->actor());

        $booking->update([
            'status'           => 'bed_confirmed',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'confirmed_by'     => $this->actor(),
            'confirmed_at'     => now(),
        ]);

        $this->activityLog->konfirmasibed($booking->id, $booking->nama_pasien, $namaBed);

        $msg = "Bed {$namaBed} ({$v['kebutuhan_bed']}) dikonfirmasi untuk {$booking->nama_pasien}.";
        if ($preempted) {
            $msg .= ' ' . implode(', ', $preempted) . ' dikembalikan ke antrian untuk mendapat bed baru.';
        }

        return redirect()->route('icu.menu_icu')->with('success', $msg);
    }

    // ACTION — Booking External: tolak (pending_icu -> ditolak)
    public function tolakExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if (! in_array($booking->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'Booking tidak bisa ditolak dari status ini.');
        }

        // Release bed jika sudah sempat dialokasikan (misal dari waiting list yang punya bed)
        if ($booking->allocated_bed_id) {
            StatusKamar::releaseBooking($booking->allocated_bed_id);
        }

        $booking->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'confirmed_by' => $this->actor(),
        ]);

        $this->activityLog->tolakBookingIcu($booking->id, $booking->nama_pasien, $v['alasan_tolak']);

        return redirect()->route('icu.menu_icu')->with('success', "Booking {$booking->nama_pasien} ditolak.");
    }

    // ACTION — BU Internal: pending_icu -> bed_verified
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

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        // Bed ISI fisik — tidak bisa dipakai
        if ($bed && strtoupper($bed->Status) === 'ISI') {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        // Reset SEMUA pemegang bed ini dari tabel lokal
        $preempted = $this->releaseAllHoldersOf($v['Kode_Ruang'], $namaBed, $namaPasien, null, $bu->id);

        // Force STATUS_KAMAR → BOOKING
        StatusKamar::setBookingForce($v['Kode_Ruang'], $this->actor());

        $bu->update([
            'status'           => 'bed_verified',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'verified_by'      => $this->actor(),
            'verified_at'      => now(),
        ]);

        $this->activityLog->verifikasibed($bu->id, $namaPasien, $namaBed);

        $msg = "Bed {$namaBed} terverifikasi untuk {$namaPasien}.";
        if ($preempted) {
            $msg .= ' ' . implode(', ', $preempted) . ' dikembalikan ke antrian untuk mendapat bed baru.';
        }

        return redirect()->route('icu.menu_icu')->with('success', $msg);
    }
    
    // ACTION — BU Internal: tolak (pending_icu -> ditolak)
    public function tolakInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if (! in_array($bu->status, ['pending_icu', 'waiting_list'])) {
            return back()->with('error', 'BU tidak bisa ditolak dari status ini.');
        }

        // Release bed jika sudah sempat dialokasikan
        if ($bu->allocated_bed_id) {
            StatusKamar::releaseBooking($bu->allocated_bed_id);
        }

        $bu->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'verified_by'  => $this->actor(),
        ]);

        $this->activityLog->tolakSpriIcu($bu->id, (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR), $v['alasan_tolak']);

        return redirect()->route('icu.menu_icu')->with('success', "BU {$bu->pasien?->Nama_Pasien} ditolak oleh ICU.");
    }

    // ACTION — Booking External: masuk waiting list (pending_icu -> waiting_list)
    public function waitingListExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'waiting_alasan'    => 'required|string|max:500',
            'waiting_estimasi'  => 'required|date|after:now',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        // if ($booking->status !== 'pending_icu') {
        if (!in_array($booking->status, ['pending_icu', 'waiting_list'])) {
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

        return redirect()->route('icu.menu_icu')->with('success', "{$booking->nama_pasien} masuk Waiting List ICU.");
    }

    // ACTION — BU Internal: masuk waiting list (pending_icu -> waiting_list)
    public function waitingListInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'waiting_alasan'    => 'required|string|max:500',
            'waiting_estimasi'  => 'required|date|after:now',
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

        return redirect()->route('icu.menu_icu')->with('success', "{$namaPasien} masuk Waiting List ICU.");
    }

    // ACTION — Booking External: pindah bed (bed_confirmed -> bed baru, status tetap bed_confirmed)
    public function pindahBedExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
            'pindah_alasan' => 'nullable|string|max:500',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if (! in_array($booking->status, ['bed_confirmed', 'admisi_verified'])) {
            return back()->with('error', 'Pindah bed hanya bisa dilakukan untuk pasien yang sudah dikonfirmasi bednya.');
        }

        // Tidak boleh memilih bed yang sama
        if ($booking->allocated_bed_id === $v['Kode_Ruang']) {
            return back()->with('error', 'Bed baru tidak boleh sama dengan bed saat ini.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        // Guard: bed baru harus KOSONG atau BOOKING (bukan ISI)
        if ($bed && strtoupper($bed->Status) === 'ISI') {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        $bedLama    = $booking->nama_bed ?? '—';
        $kodeRuangLama = $booking->allocated_bed_id;

        // Release bed lama → KOSONG (hanya jika masih BOOKING, bukan ISI)
        if ($kodeRuangLama) {
            StatusKamar::releaseBooking($kodeRuangLama);
        }

        // Preempt SEMUA pemegang bed baru dari tabel lokal (tanpa syarat STATUS_KAMAR)
        $this->releaseAllHoldersOf($v['Kode_Ruang'], $namaBed, $booking->nama_pasien, $booking->id, null);

        // Set bed baru → BOOKING (force)
        StatusKamar::setBookingForce($v['Kode_Ruang'], $this->actor());

        $booking->update([
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'pindah_alasan'    => $v['pindah_alasan'],
            'pindah_bed_lama'  => $bedLama,
            'pindah_by'        => $this->actor(),
            'pindah_at'        => now(),
            'status'           => 'bed_confirmed',
            'confirmed_by'     => $this->actor(),
            'confirmed_at'     => now(),
        ]);

        $this->activityLog->pindahBedExt($booking->id, $booking->nama_pasien, $bedLama, $namaBed);

        return redirect()->route('icu.menu_icu')->with('success', "Bed pasien {$booking->nama_pasien} dipindahkan: {$bedLama} → {$namaBed}.");
    }

    // ACTION — BU Internal: pindah bed (bed_verified -> bed baru, status tetap bed_verified)
    public function pindahBedInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
            'pindah_alasan' => 'nullable|string|max:500',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if ($bu->status !== 'bed_verified') {
            return back()->with('error', 'Pindah bed hanya bisa dilakukan untuk pasien yang sudah diverifikasi bednya.');
        }

        // Tidak boleh memilih bed yang sama
        if ($bu->allocated_bed_id === $v['Kode_Ruang']) {
            return back()->with('error', 'Bed baru tidak boleh sama dengan bed saat ini.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        // Guard: bed baru harus KOSONG atau BOOKING (bukan ISI)
        if ($bed && strtoupper($bed->Status) === 'ISI') {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        $bedLama       = $bu->nama_bed ?? '—';
        $kodeRuangLama = $bu->allocated_bed_id;
        $namaPasien    = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        // Release bed lama → KOSONG
        if ($kodeRuangLama) {
            StatusKamar::releaseBooking($kodeRuangLama);
        }

        // Preempt SEMUA pemegang bed baru dari tabel lokal
        $this->releaseAllHoldersOf($v['Kode_Ruang'], $namaBed, $namaPasien, null, $bu->id);

        // Set bed baru → BOOKING (force)
        StatusKamar::setBookingForce($v['Kode_Ruang'], $this->actor());

        $bu->update([
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'pindah_alasan'    => $v['pindah_alasan'],
            'pindah_bed_lama'  => $bedLama,
            'pindah_by'        => $this->actor(),
            'pindah_at'        => now(),
            'verified_by'      => $this->actor(),
            'verified_at'      => now(),
        ]);

        $this->activityLog->pindahBedInt($bu->id, $namaPasien, $bedLama, $namaBed);

        return redirect()->route('icu.menu_icu')->with('success', "Bed pasien {$namaPasien} dipindahkan: {$bedLama} → {$namaBed}.");
    }
}
