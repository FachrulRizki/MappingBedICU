<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;
use App\Models\MCaraBayar;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use App\Services\ActivityLogService;
use App\Services\Icu\AntrianService;
use App\Services\Icu\BedSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MenuAdmisiController extends Controller
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
        return $user?->name ?? 'admisi';
    }

    public function index(Request $request): Response
    {
        // Sync status bed agar data antrian yang tampil selalu up-to-date
        // (pasien yang sudah masuk ICU hilang dari antrian, pasien keluar masuk laporan)
        $this->bedSync->sync();

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

    public function storeBooking(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_pasien'      => 'required|string|max:150',
            'jenis_kelamin'    => 'required|in:L,P',
            'no_identitas'     => 'nullable|string|max:30',
            'asal_rujukan'     => 'nullable|string|max:150',
            'no_telp_keluarga' => 'nullable|string|max:20',
            'diagnosa'         => 'required|string|max:255',
            'diagnosa_icd'     => 'nullable|string|max:255',
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

        $this->activityLog->bookingBaru($booking->id, $booking->nama_pasien);

        return back()->with('success', "Booking untuk {$booking->nama_pasien} berhasil dikirim ke ICU.");
    }

    public function updateBooking(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'nama_pasien'      => 'required|string|max:150',
            'jenis_kelamin'    => 'required|in:L,P',
            'no_identitas'     => 'nullable|string|max:30',
            'asal_rujukan'     => 'nullable|string|max:150',
            'no_telp_keluarga' => 'nullable|string|max:20',
            'diagnosa'         => 'required|string|max:255',
            'diagnosa_icd'     => 'nullable|string|max:255',
            'rencana_tindakan' => 'required|string|max:255',
            'jaminan'          => 'required|string|max:50',
            'catatan_jaminan'  => 'nullable|string|max:500',
            'keterangan'       => 'nullable|string|max:500',
        ]);

        $booking = IcuBookingExternal::findOrFail($id);

        // Hanya bisa edit jika masih pending
        if (!in_array($booking->status, ['pending_icu'])) {
            return back()->with('error', 'Booking tidak dapat diedit karena sudah diproses.');
        }

        $booking->update($validated);

        $this->activityLog->log(
            'Edit Booking',
            "Edit booking {$booking->nama_pasien}",
            'booking_external',
            $booking->id,
            'IcuBookingExternal'
        );

        return back()->with('success', "Booking {$booking->nama_pasien} berhasil diupdate.");
    }

    public function batalBooking(Request $request, int $id): RedirectResponse
    {
        $booking = IcuBookingExternal::findOrFail($id);

        // Hanya bisa batalkan jika masih pending, waiting_list, atau bed_confirmed
        if (!in_array($booking->status, ['pending_icu', 'waiting_list', 'bed_confirmed'])) {
            return back()->with('error', 'Booking tidak dapat dibatalkan.');
        }

        $v = $request->validate([
            'alasan_batal' => 'nullable|string|max:500',
        ]);

        $namaPasien = $booking->nama_pasien;
        $oldStatus  = $booking->status;

        $booking->update([
            'status'        => 'dibatalkan',
            'alasan_batal'  => $v['alasan_batal'] ?? null,
            'dibatalkan_by' => $this->actor(),
            'dibatalkan_at' => now(),
        ]);

        $this->activityLog->log(
            'Batal Booking',
            "Batalkan booking {$namaPasien} (status sebelumnya: {$oldStatus})"
                . (!empty($v['alasan_batal']) ? " — {$v['alasan_batal']}" : ''),
            'booking_external',
            $booking->id,
            'IcuBookingExternal'
        );

        return back()->with('success', "Booking {$namaPasien} berhasil dibatalkan.");
    }

    public function deleteBooking(int $id): RedirectResponse
    {
        $booking = IcuBookingExternal::findOrFail($id);

        // Hanya bisa hapus jika masih pending atau ditolak/dibatalkan
        if (!in_array($booking->status, ['pending_icu', 'ditolak', 'dibatalkan'])) {
            return back()->with('error', 'Booking tidak dapat dihapus karena sudah diproses.');
        }

        $namaPasien = $booking->nama_pasien;

        $this->activityLog->log(
            'Hapus Booking',
            "Hapus booking {$namaPasien}",
            'booking_external',
            $booking->id,
            'IcuBookingExternal'
        );

        $booking->delete();

        return back()->with('success', "Booking {$namaPasien} berhasil dihapus.");
    }

    public function editInt(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'         => 'required|string|max:20',
            'No_Reg'        => 'required|string|max:20',
            'Diagnosis'     => 'required|string|max:255',
            'Diagnosis_ICD' => 'nullable|string|max:255',
            'IndikasiRI'    => 'required|string|max:255',
            'asal_ruang'    => 'nullable|string|max:100',
            'Dokter'        => 'nullable|string|max:100',
            'spesialis'     => 'nullable|string|max:100',
            'Keterangan'    => 'nullable|string|max:500',
        ]);

        $bu = IcuSpriInternal::findOrFail($id);

        // Hanya bisa edit jika masih pending atau ditolak
        if (!in_array($bu->status, ['pending_admisi', 'pending_icu', 'ditolak'])) {
            return back()->with('error', 'Booking ICU tidak dapat diedit karena sudah diproses lebih lanjut.');
        }

        $bu->update($validated);

        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);

        $this->activityLog->log(
            'Edit Booking Internal',
            "Edit booking internal {$namaPasien} oleh admisi",
            'spri_internal',
            $bu->id,
            'IcuSpriInternal'
        );

        return back()->with('success', "Booking ICU {$namaPasien} berhasil diupdate.");
    }

    public function batalInt(Request $request, int $id): RedirectResponse
    {
        $bu = IcuSpriInternal::findOrFail($id);

        // Hanya bisa batalkan jika belum terlalu jauh diproses
        if (!in_array($bu->status, ['pending_admisi', 'pending_icu', 'waiting_list'])) {
            return back()->with('error', 'Booking ICU tidak dapat dibatalkan.');
        }

        $v = $request->validate([
            'alasan_batal' => 'nullable|string|max:500',
        ]);

        $namaPasien = (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR);
        $oldStatus  = $bu->status;

        $bu->update([
            'status'        => 'dibatalkan',
            'alasan_batal'  => $v['alasan_batal'] ?? null,
            'dibatalkan_by' => $this->actor(),
            'dibatalkan_at' => now(),
        ]);

        $this->activityLog->log(
            'Batal Booking Internal',
            "Batalkan booking internal {$namaPasien} oleh admisi (status sebelumnya: {$oldStatus})"
                . (!empty($v['alasan_batal']) ? " — {$v['alasan_batal']}" : ''),
            'spri_internal',
            $bu->id,
            'IcuSpriInternal'
        );

        return back()->with('success', "Booking ICU {$namaPasien} berhasil dibatalkan.");
    }

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
            'approved_at'    => now(),
        ]);

        $this->activityLog->approveSpri($bu->id, (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR));

        return back()->with('success', "BU {$bu->pasien?->Nama_Pasien} disetujui dan diteruskan ke ICU.");
    }

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

        $this->activityLog->tolakSpriAdmisi($bu->id, (string) ($bu->pasien?->Nama_Pasien ?? $bu->No_MR), $v['alasan_tolak']);

        return back()->with('success', "BU {$bu->pasien?->Nama_Pasien} ditolak oleh Admisi.");
    }

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
            'verified_at' => now(),
        ]);

        $this->activityLog->verifikasiPasien($booking->id, $booking->nama_pasien, $v['No_MR']);

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
            $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                ->table('PENDAFTARAN as p')
                ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                ->where('p.No_MR', $noMr)
                ->orderByDesc('p.Tanggal')
                ->limit(10)
                ->select([
                    'p.No_Reg', 'p.Kode_Masuk',
                    \Illuminate\Support\Facades\DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_ruang"),
                ])->get();

            $kunjungans = $rows->map(fn ($r) => [
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
