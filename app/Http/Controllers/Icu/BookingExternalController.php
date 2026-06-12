<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuBookingExternal;
use App\Models\MKelas;
use App\Models\MRuangMaster;
use App\Models\Pendaftaran;
use App\Models\RegistrasiPasien;
use App\Models\StatusKamar;
use App\Services\Icu\BookingExternalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingExternalController extends Controller
{
    public function __construct(
        private readonly BookingExternalService $service
    ) {}

    private function currentUser(): string
    {
        return auth()->user()?->name ?? 'petugas';
    }

    public function index(): Response
    {
        $bookings = IcuBookingExternal::with('pasien')
            ->latest()
            ->get()
            ->map(fn($b) => $this->format($b));

        $kamarKosong = MRuangMaster::bedKosong();
        $masterKelas = MRuangMaster::jenisIcuTersedia();

        return Inertia::render('Icu/BookingExternal', [
            'bookings'    => $bookings,
            'kamarKosong' => $kamarKosong,
            'masterKelas' => $masterKelas,
            'flash'       => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ── Admisi — buat booking ─────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_pasien'      => 'required|string|max:150',
            'jenis_kelamin'    => 'required|in:L,P',
            'no_identitas'     => 'nullable|string|max:30',
            'asal_rujukan'     => 'nullable|string|max:150',
            'no_telp_keluarga' => 'nullable|string|max:20',
            'diagnosa'         => 'required|string|max:255',
            'rencana_tindakan' => 'required|string|max:255',
            'jaminan'          => 'required|in:BPJS,Umum,Asuransi,Lainnya',
            'catatan_jaminan'  => 'nullable|string|max:500',
            'keterangan'       => 'nullable|string|max:500',
        ]);

        $booking = $this->service->buatBooking([
            ...$validated,
            'created_by' => $this->currentUser(),
        ]);

        return back()->with('success', "Booking untuk {$booking->nama_pasien} berhasil dikirim ke ICU.");
    }

    // ── Petugas ICU — konfirmasi bed ──────────────────────────────────────

    public function konfirmasiIcu(Request $request, int $id): RedirectResponse
    {
        $connKamar = StatusKamar::connectionName() . '.' . StatusKamar::tableName('STATUS_KAMAR', 'status_kamar');
        $connKelas = MKelas::connectionName() . '.' . MKelas::tableName('M_KELAS', 'm_kelas');

        $validated = $request->validate([
            'Kode_Ruang'    => "required|exists:{$connKamar},Kode_Ruang",
            'kebutuhan_bed' => "required|exists:{$connKelas},Nama_Kelas",
        ]);

        $bed     = StatusKamar::with('ruang')->where('Kode_Ruang', $validated['Kode_Ruang'])->first();
        $namaBed = $bed?->ruang?->Nama_RuangM ?? $validated['Kode_Ruang'];

        $booking = $this->service->konfirmasiIcu(
            id:           $id,
            kodeRuang:    $validated['Kode_Ruang'],
            namaBed:      $namaBed,
            kebutuhanBed: $validated['kebutuhan_bed'],
            confirmedBy:  $this->currentUser(),
        );

        return back()->with('success', "Bed {$namaBed} ({$validated['kebutuhan_bed']}) dikonfirmasi untuk {$booking->nama_pasien}. Menunggu verifikasi Admisi.");
    }

    // ── Petugas ICU — tolak ────────────────────────────────────────────────

    public function tolakIcu(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'alasan_tolak' => 'required|string|max:255',
        ]);

        $booking = $this->service->tolakIcu($id, $validated['alasan_tolak'], $this->currentUser());

        return back()->with('success', "Booking {$booking->nama_pasien} ditolak.");
    }

    // ── Admisi — lookup pasien dari DB RS (AJAX) ─────────────────────────

    public function lookupPasienExternal(Request $request): \Illuminate\Http\JsonResponse
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

        // Ambil daftar kunjungan terbaru dari PENDAFTARAN
        $kunjungans = collect();
        try {
            if (RegistrasiPasien::rsusAvailable()) {
                $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv_rsus')
                    ->table('PENDAFTARAN as p')
                    ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)
                    ->orderByDesc('p.Tanggal')
                    ->limit(10)
                    ->select([
                        'p.No_Reg',
                        'p.Kode_Masuk',
                        \Illuminate\Support\Facades\DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_ruang"),
                    ])
                    ->get();
            } else {
                $rows = \Illuminate\Support\Facades\DB::connection('mysql')
                    ->table('pendaftaran as p')
                    ->leftJoin('m_ruang_master as rm', 'p.Kode_Asal', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)
                    ->orderByDesc('p.created_at')
                    ->limit(10)
                    ->select([
                        'p.No_Reg',
                        'p.Kode_Masuk',
                        \Illuminate\Support\Facades\DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal, '') as nama_ruang"),
                    ])
                    ->get();
            }

            $kunjungans = $rows->map(fn($r) => [
                'No_Reg'     => $r->No_Reg,
                'Kode_Masuk' => $r->Kode_Masuk ?? '',
                'nama_ruang' => $r->nama_ruang  ?? '',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[lookupPasienExternal] ' . $e->getMessage());
        }

        return response()->json([
            'found'       => true,
            'No_MR'       => $pasien->No_MR,
            'nama_pasien' => $pasien->Nama_Pasien,
            'kunjungans'  => $kunjungans,
        ]);
    }

    // ── Admisi — verifikasi No_MR setelah pasien tiba ─────────────────────

    public function verifikasiAdmisi(Request $request, int $id): RedirectResponse
    {
        $connPasien = RegistrasiPasien::connectionName() . '.' . RegistrasiPasien::tableName('REGISTER_PASIEN', 'registrasi_pasien');
        $connReg    = Pendaftaran::connectionName() . '.' . Pendaftaran::tableName('PENDAFTARAN', 'pendaftaran');

        $validated = $request->validate([
            'No_MR'  => "required|exists:{$connPasien},No_MR",
            'No_Reg' => "nullable|exists:{$connReg},No_Reg",
        ]);

        $booking = $this->service->verifikasiAdmisi(
            id:         $id,
            noMr:       $validated['No_MR'],
            noReg:      $validated['No_Reg'] ?? null,
            verifiedBy: $this->currentUser(),
        );

        $nama = $booking->pasien?->Nama_Pasien ?? $booking->nama_pasien;

        return back()->with('success', "Pasien {$nama} (No. MR: {$validated['No_MR']}) terverifikasi. Bed {$booking->nama_bed} aktif.");
    }

    // ── Formatter ─────────────────────────────────────────────────────────

    private function format(IcuBookingExternal $b): array
    {
        return [
            'id'               => $b->id,
            'nama_pasien'      => $b->nama_pasien,
            'jenis_kelamin'    => $b->jenis_kelamin,
            'no_identitas'     => $b->no_identitas,
            'asal_rujukan'     => $b->asal_rujukan,
            'no_telp_keluarga' => $b->no_telp_keluarga,
            'diagnosa'         => $b->diagnosa,
            'rencana_tindakan' => $b->rencana_tindakan,
            'kebutuhan_bed'    => $b->kebutuhan_bed,
            'jaminan'          => $b->jaminan,
            'catatan_jaminan'  => $b->catatan_jaminan,
            'keterangan'       => $b->keterangan,
            'No_MR'            => $b->No_MR,
            'No_Reg'           => $b->No_Reg,
            'allocated_bed_id' => $b->allocated_bed_id,
            'nama_bed'         => $b->nama_bed,
            'status'           => $b->status,
            'status_label'     => $b->statusLabel(),
            'status_color'     => $b->statusColor(),
            'alasan_tolak'     => $b->alasan_tolak,
            'created_by'       => $b->created_by,
            'confirmed_by'     => $b->confirmed_by,
            'verified_by'      => $b->verified_by,
            'nama_pasien_mr'   => $b->pasien?->Nama_Pasien,
            'created_at'       => $b->created_at?->format('d/m/Y H:i'),
        ];
    }
}
