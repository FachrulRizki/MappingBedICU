<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use App\Services\Icu\AntrianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuIcuController extends Controller
{
    public function __construct(
        private readonly AntrianService $service
    ) {}

    private function actor(): string
    {
        return auth()->user()?->name ?? 'petugas_icu';
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

        return back()->with('success', "Booking {$booking->nama_pasien} ditolak.");
    }

    // -------------------------------------------------------------------------
    // ACTION — SPRI Internal: pending_icu -> bed_verified
    // -------------------------------------------------------------------------

    public function verifikasiInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'Kode_Ruang'    => 'required|string|max:20',
            'kebutuhan_bed' => 'required|string|max:100',
        ]);

        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            return back()->with('error', 'SPRI sudah tidak berstatus Menunggu ICU.');
        }

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $v['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $v['Kode_Ruang'];

        $spri->update([
            'status'           => 'bed_verified',
            'kebutuhan_bed'    => $v['kebutuhan_bed'],
            'allocated_bed_id' => $v['Kode_Ruang'],
            'nama_bed'         => $namaBed,
            'verified_by'      => $this->actor(),
        ]);

        return back()->with('success', "Bed {$namaBed} terverifikasi untuk {$spri->pasien?->Nama_Pasien}.");
    }

    // -------------------------------------------------------------------------
    // ACTION — SPRI Internal: tolak (pending_icu -> ditolak)
    // -------------------------------------------------------------------------

    public function tolakInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_icu') {
            return back()->with('error', 'SPRI sudah tidak berstatus Menunggu ICU.');
        }

        $spri->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'verified_by'  => $this->actor(),
        ]);

        return back()->with('success', "SPRI {$spri->pasien?->Nama_Pasien} ditolak oleh ICU.");
    }
}
