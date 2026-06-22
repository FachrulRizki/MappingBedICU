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

    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $data = $this->service->build($request);

        return Inertia::render('Icu/MenuIcu', [
            'antrian'     => $data['antrian'],
            'summary'     => $data['summary'],
            'filters'     => $data['filters'],
            'kamarKosong' => MRuangMaster::bedKosong(),
            'masterKelas' => MRuangMaster::jenisIcuTersedia(),
            'flash'       => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // ACTION — Booking External: pending_icu -> bed_confirmed
    // -------------------------------------------------------------------------

    public function konfirmasiExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            return back()->with('error', 'Booking sudah tidak berstatus Menunggu ICU.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        $booking->update([
            'status'           => 'bed_confirmed',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'confirmed_by'     => $this->actor(),
        ]);

        $this->activityLog->konfirmasibed($booking->id, $booking->nama_pasien, $namaBed);

        return back()->with('success', "Bed {$namaBed} ({$v['kebutuhan_bed']}) dikonfirmasi untuk {$booking->nama_pasien}.");
    }

    // -------------------------------------------------------------------------
    // ACTION — Booking External: tolak (pending_icu -> ditolak)
    // -------------------------------------------------------------------------

    public function tolakExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'pending_icu') {
            return back()->with('error', 'Booking sudah tidak berstatus Menunggu ICU.');
        }

        $booking->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'confirmed_by' => $this->actor(),
        ]);

        $this->activityLog->tolakBookingIcu($booking->id, $booking->nama_pasien, $v['alasan_tolak']);

        return back()->with('success', "Booking {$booking->nama_pasien} ditolak.");
    }

    // -------------------------------------------------------------------------
    // ACTION — BU Internal: pending_icu -> bed_verified
    // -------------------------------------------------------------------------

    public function verifikasiInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if ($bu->status !== 'pending_icu') {
            return back()->with('error', 'BU sudah tidak berstatus Menunggu ICU.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        $bu->update([
            'status'           => 'bed_verified',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'verified_by'      => $this->actor(),
        ]);

        $this->activityLog->verifikasibed($bu->id, (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR), $namaBed);

        return back()->with('success', "Bed {$namaBed} terverifikasi untuk {$bu->pasien?->Nama_Pasien}.");
    }

    // -------------------------------------------------------------------------
    // ACTION — BU Internal: tolak (pending_icu -> ditolak)
    // -------------------------------------------------------------------------

    public function tolakInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        if ($bu->status !== 'pending_icu') {
            return back()->with('error', 'BU sudah tidak berstatus Menunggu ICU.');
        }

        $bu->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'verified_by'  => $this->actor(),
        ]);

        $this->activityLog->tolakSpriIcu($bu->id, (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR), $v['alasan_tolak']);

        return back()->with('success', "BU {$bu->pasien?->Nama_Pasien} ditolak oleh ICU.");
    }
}
