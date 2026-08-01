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

        // Bangun map: Kode_Ruang -> Status untuk semua bed ICU
        // Dipakai frontend untuk deteksi bed sudah kosong (pasien keluar ICU)
        $statusKamarMap = StatusKamar::all()
            ->pluck('Status', 'Kode_Ruang')
            ->map(fn ($s) => strtoupper($s))
            ->toArray();

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

        if ($booking->allocated_bed_id === $v['Kode_Ruang']) {
            return back()->with('error', 'Bed baru tidak boleh sama dengan bed saat ini.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        if ($bed && $bed->isIsi()) {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        $bedLama = $booking->nama_bed ?? '—';

        // Cukup update rekomendasi bed lokal — TANPA menyentuh STATUS_KAMAR
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

        return redirect()->route('icu.menu_icu')
            ->with('success', "Rekomendasi bed pasien {$booking->nama_pasien} diubah: {$bedLama} → {$namaBed}. Admisi perlu memperbarui di Bed Management.");
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

        if ($bu->allocated_bed_id === $v['Kode_Ruang']) {
            return back()->with('error', 'Bed baru tidak boleh sama dengan bed saat ini.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        if ($bed && $bed->isIsi()) {
            return back()->with('error', "Bed {$namaBed} sudah terisi pasien aktif. Tidak bisa digunakan.");
        }

        $bedLama    = $bu->nama_bed ?? '—';
        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        // Cukup update rekomendasi bed lokal — TANPA menyentuh STATUS_KAMAR
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

        return redirect()->route('icu.menu_icu')
            ->with('success', "Rekomendasi bed pasien {$namaPasien} diubah: {$bedLama} → {$namaBed}. Admisi perlu memperbarui di Bed Management.");
    }

}
