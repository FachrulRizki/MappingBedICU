<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MCaraBayar;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use App\Services\Icu\AntrianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MenuAdmisiController extends Controller
{
    public function __construct(
        private readonly AntrianService $service
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

        $booking = IcuBookingExternal::create([
            ...$validated,
            'kebutuhan_bed' => null,
            'status'        => 'pending_icu',
            'created_by'    => $this->actor(),
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

        $bu = IcuSpriInternal::findOrFail($id);

        if ($bu->status !== 'pending_admisi') {
            return back()->with('error', 'BU sudah tidak berstatus Menunggu Admisi.');
        }

        $bu->update([
            'status'         => 'pending_icu',
            'catatan_admisi' => $v['catatan_admisi'] ?? null,
            'approved_by'    => $this->actor(),
        ]);

        return back()->with('success', "BU {$bu->pasien?->Nama_Pasien} disetujui dan diteruskan ke ICU.");
    }

    // -------------------------------------------------------------------------
    // ACTION — BU Internal: tolak oleh Admisi (any -> ditolak)
    // -------------------------------------------------------------------------

    public function tolakInt(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        $bu->update([
            'status'       => 'ditolak',
            'alasan_tolak' => $v['alasan_tolak'],
            'approved_by'  => $this->actor(),
        ]);

        return back()->with('success', "BU {$bu->pasien?->Nama_Pasien} ditolak oleh Admisi.");
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

    // ── AJAX — Lookup pasien dari DB RS untuk verifikasi External ─────────

    public function lookupPasienExternal(Request $request): JsonResponse
    {
        $noMr = trim($request->query('No_MR', ''));
        if (! $noMr) {
            return response()->json(['found' => false, 'message' => 'No_MR kosong.']);
        }

        try {
            $pasien = RegistrasiPasien::where('No_MR', $noMr)->first();
        } catch (\Exception $e) {
            return response()->json([
                'found'   => false,
                'message' => 'Tidak dapat terhubung ke database RS. ' .
                    (app()->hasDebugModeEnabled() ? $e->getMessage() : 'Hubungi administrator.'),
            ]);
        }

        if (! $pasien) {
            return response()->json([
                'found'   => false,
                'message' => "Pasien dengan No_MR '{$noMr}' tidak ditemukan.",
            ]);
        }

        $kunjungans = collect();
        try {
            if (RegistrasiPasien::rsusAvailable()) {
                $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                    ->table('PENDAFTARAN as p')
                    ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)->orderByDesc('p.Tanggal')->limit(10)
                    ->select([
                        'p.No_Reg', 'p.Kode_Masuk',
                        \Illuminate\Support\Facades\DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_ruang"),
                    ])->get();
            } else {
                $rows = \Illuminate\Support\Facades\DB::connection('mysql')
                    ->table('pendaftaran as p')
                    ->leftJoin('m_ruang_master as rm', 'p.Kode_Asal', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)->orderByDesc('p.created_at')->limit(10)
                    ->select([
                        'p.No_Reg', 'p.Kode_Masuk',
                        \Illuminate\Support\Facades\DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal, '') as nama_ruang"),
                    ])->get();
            }
            $kunjungans = $rows->map(fn($r) => [
                'No_Reg'     => $r->No_Reg,
                'Kode_Masuk' => $r->Kode_Masuk ?? '',
                'nama_ruang' => $r->nama_ruang  ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error('[lookupPasienExternal] ' . $e->getMessage());
        }

        return response()->json([
            'found'       => true,
            'No_MR'       => $pasien->No_MR,
            'nama_pasien' => $pasien->Nama_Pasien,
            'kunjungans'  => $kunjungans,
        ]);
    }
}
