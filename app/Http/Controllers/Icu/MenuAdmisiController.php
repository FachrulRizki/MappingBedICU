<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MCaraBayar;
use App\Models\MRuangMaster;
use App\Services\Icu\AntrianService;
use App\Services\Icu\BookingExternalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuAdmisiController extends Controller
{
    public function __construct(
        private readonly AntrianService $service,
        private readonly BookingExternalService $bookingService
    ) {}

    private function actor(): string
    {
        return auth()->user()?->name ?? 'admisi';
    }

    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $data = $this->service->build($request);

        return Inertia::render('Icu/MenuAdmisi', [
            'antrian'     => $data['antrian'],
            'summary'     => $data['summary'],
            'filters'     => $data['filters'],
            'caraBayar'   => MCaraBayar::list(),
            'kamarKosong' => MRuangMaster::bedKosong(),
            'masterKelas' => MRuangMaster::jenisIcuTersedia(),
            'flash'       => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // ACTION — Tambah Booking External baru (dari Menu Admisi)
    // -------------------------------------------------------------------------

    public function storeBooking(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_pasien'      => 'required|string|max:150',
            'jenis_kelamin'    => 'required|in:L,P',
            'no_identitas'     => 'nullable|string|max:30',
            'asal_rujukan'     => 'nullable|string|max:150',
            'no_telp_keluarga' => 'nullable|string|max:20',
            'diagnosa'         => 'required|string|max:255',
            'rencana_tindakan' => 'required|string|max:255',
            'jaminan'          => 'required|string|max:50',
            'catatan_jaminan'  => 'nullable|string|max:500',
            'keterangan'       => 'nullable|string|max:500',
        ]);

        $booking = $this->bookingService->buatBooking([
            ...$validated,
            'created_by' => $this->actor(),
        ]);

        return back()->with('success', "Booking untuk {$booking->nama_pasien} berhasil dikirim ke ICU.");
    }

    // -------------------------------------------------------------------------
    // ACTION — SPRI Internal: pending_admisi -> pending_icu (approve)
    // -------------------------------------------------------------------------

    public function approveInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'catatan_admisi' => 'nullable|string|max:500',
        ]);

        $spri = IcuSpriInternal::findOrFail($id);

        if ($spri->status !== 'pending_admisi') {
            return back()->with('error', 'SPRI sudah tidak berstatus Menunggu Admisi.');
        }

        $spri->update([
            'status'         => 'pending_icu',
            'catatan_admisi' => $v['catatan_admisi'] ?? null,
            'approved_by'    => $this->actor(),
        ]);

        return back()->with('success', "SPRI {$spri->pasien?->Nama_Pasien} disetujui dan diteruskan ke ICU.");
    }

    // -------------------------------------------------------------------------
    // ACTION — SPRI Internal: tolak oleh Admisi (any -> ditolak)
    // -------------------------------------------------------------------------

    public function tolakInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $spri = IcuSpriInternal::findOrFail($id);

        $spri->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'approved_by'  => $this->actor(),
        ]);

        return back()->with('success', "SPRI {$spri->pasien?->Nama_Pasien} ditolak oleh Admisi.");
    }

    // -------------------------------------------------------------------------
    // ACTION — Booking External: bed_confirmed -> admisi_verified
    // -------------------------------------------------------------------------

    public function verifikasiExt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'No_MR'  => 'required|string|max:20',
            'No_Reg' => 'nullable|string|max:20',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        if ($booking->status !== 'bed_confirmed') {
            return back()->with('error', 'Booking belum berstatus Bed Dikonfirmasi.');
        }

        $booking->update([
            'status'      => 'admisi_verified',
            'No_MR'       => $v['No_MR'],
            'No_Reg'      => $v['No_Reg'] ?? null,
            'verified_by' => $this->actor(),
        ]);

        return back()->with('success', "Pasien No. MR {$v['No_MR']} terverifikasi. Bed {$booking->nama_bed} aktif.");
    }
}
