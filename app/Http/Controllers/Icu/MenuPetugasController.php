<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MCaraBayar;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MenuPetugasController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    private function actor(): string
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return $user?->name ?? 'petugas_ruang';
    }

    private function actorNames(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) return ['petugas_ruang'];
        $names = [$user->name];
        if ($user->keycloak_username) $names[] = $user->keycloak_username;
        if ($user->username && $user->username !== $user->name) $names[] = $user->username;
        return array_unique(array_filter($names));
    }

    private function userWardIds(): array
    {
        /** @var \App\Models\User|null $u */
        $u = Auth::user();
        return $u?->getWardIdsArray() ?? [];
    }

    public function index(Request $request): Response
    {
        $fNama    = trim($request->query('nama', ''));
        $today    = now()->format('Y-m-d');
        $fTgl     = $request->query('tgl', '');
        $fTglDari = $request->query('tgl_dari', $fTgl ?: $today);
        $fTglAkh  = $request->query('tgl_sampai', $fTgl ?: $today);
        $fStatus  = $request->query('status', '');
        $fJaminan = trim($request->query('jaminan', ''));
        $sortBy   = $request->query('sort', 'created_at');
        $sortDir  = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $dbSortAllowed = ['created_at', 'status'];

        $q = IcuSpriInternal::query()->whereIn('NameUser', $this->actorNames());

        if ($fStatus) $q->where('status', $fStatus);

        if ($fNama) {
            $ids = RegistrasiPasien::where('Nama_Pasien', 'like', "%{$fNama}%")->pluck('No_MR')->toArray();
            $q->where(fn ($qq) => $qq->whereIn('No_MR', $ids)->orWhere('No_MR', 'like', "%{$fNama}%"));
        }

        $q->whereBetween('created_at', [$fTglDari . ' 00:00:00', $fTglAkh . ' 23:59:59']);

        if (in_array($sortBy, $dbSortAllowed)) {
            $q->orderBy($sortBy, $sortDir);
        } else {
            $q->orderBy('created_at', 'desc');
        }

        $data      = $q->get();
        $pasienMap = RegistrasiPasien::whereIn('No_MR', $data->pluck('No_MR')->unique())->get()->keyBy('No_MR');

        // Batch-lookup jaminan dari SQL Server RS
        $noRegs     = $data->pluck('No_Reg')->filter()->unique()->values()->toArray();
        $jaminanMap = $this->buildJaminanMap($noRegs);

        $spriList = $data->map(fn ($s) => $this->format($s, $pasienMap, $jaminanMap[$s->No_Reg] ?? null));

        if ($fJaminan) {
            $spriList = $spriList->filter(fn ($i) => ($i['jaminan_kode'] ?? '') === $fJaminan)->values();
        }

        if ($sortBy === 'nama_pasien') {
            $spriList = $sortDir === 'asc'
                ? $spriList->sortBy(fn ($i) => strtolower($i['nama_pasien']))->values()
                : $spriList->sortByDesc(fn ($i) => strtolower($i['nama_pasien']))->values();
        }

        $allData = IcuSpriInternal::query()->whereIn('NameUser', $this->actorNames())->get();
        $summary = [
            'total'        => $allData->count(),
            'pending_icu'  => $allData->where('status', 'pending_icu')->count(),
            'waiting_list' => $allData->where('status', 'waiting_list')->count(),
            'bed_verified' => $allData->where('status', 'bed_verified')->count(),
            'ditolak'      => $allData->where('status', 'ditolak')->count(),
        ];

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        return Inertia::render('Icu/MenuPetugas', [
            'spriList'        => $spriList,
            'summary'         => $summary,
            'filters'         => compact('fNama', 'fTgl', 'fTglDari', 'fTglAkh', 'fStatus', 'fJaminan', 'sortBy', 'sortDir'),
            'pasienAktif'     => $this->getPasienAktif(''),
            'wardIds'         => $this->userWardIds(),
            'authProvider'    => $authUser?->auth_provider ?? 'local',
            'isIgdUser'       => $this->isIgdUser(),
            'unitKerja'       => $authUser?->unit_kerja ?? '',
            'kamarKosong'     => MRuangMaster::bedKosong(),
            'masterKelas'     => MRuangMaster::jenisIcuTersedia(),
            'masterCaraBayar' => MCaraBayar::list(),
            'flash'           => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function buildJaminanMap(array $noRegs): array
    {
        if (empty($noRegs)) return [];

        try {
            $rows = DB::connection('sqlsrv_rsus')
                ->table('PENDAFTARAN as p')
                ->leftJoin('M_CARABAYAR as cb', 'p.Kode_Bayar', '=', 'cb.Kode_Bayar')
                ->whereIn('p.No_Reg', $noRegs)
                ->select([
                    'p.No_Reg',
                    'p.Kode_Bayar',
                    DB::raw("ISNULL(cb.Ket_Bayar, p.Kode_Bayar) as ket_bayar"),
                ])
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $map[$row->No_Reg] = [
                    'kode' => $row->Kode_Bayar ?? '',
                    'nama' => $this->formatNamaDokter(trim($row->ket_bayar ?? $row->Kode_Bayar ?? '')),
                ];
            }
            return $map;
        } catch (\Exception $e) {
            Log::warning('[buildJaminanMap] ' . $e->getMessage());
            return [];
        }
    }

    private function getPasienAktif(string $cari): array
    {
        $wardIds = $this->userWardIds();
        $isIgd   = $this->isIgdUser();

        if (empty($wardIds) && ! $isIgd) return [];

        try {
            if ($isIgd) {
                $batasWaktu = now()->subDays(3)->toDateString();

                $q = DB::connection('sqlsrv_rsus')
                    ->table('PENDAFTARAN as p')
                    ->join('REGISTER_PASIEN as rp', 'p.No_MR', '=', 'rp.No_MR')
                    ->join('ASESMEN_IGD as ag', 'p.No_Reg', '=', 'ag.No_Reg')
                    ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                    ->leftJoin(
                        DB::raw('(SELECT No_Reg, MAX(Diagnosis) as Diagnosis, MAX(IndikasiRI) as IndikasiRI, MAX(Spesialis) as Spesialis FROM ASESMEN_SURAT_PERMINTAAN_RI WHERE Perawatan = \'ICU\' GROUP BY No_Reg) as spri'),
                        'p.No_Reg', '=', 'spri.No_Reg'
                    )
                    ->where('p.Kode_Masuk', '1')
                    ->where('p.Status', '1')
                    ->where('p.Tanggal', '>=', $batasWaktu)
                    ->whereRaw('NOT EXISTS (SELECT 1 FROM TR_KAMAR tk WHERE tk.No_Reg = p.No_Reg AND tk.Tgl_Mulai IS NOT NULL)')
                    ->where('p.No_MR', '<>', '000000')
                    ->select([
                        'p.No_MR', 'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                        'rp.Nama_Pasien', 'rp.jenis_kelamin',
                        DB::raw("'' as Kode_RuangM"),
                        DB::raw("'IGD' as Nama_RuangM"),
                        DB::raw("'' as Kode_Bangsal"),
                        DB::raw("'IGD' as Nama_Bangsal"),
                        DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(d.Nama_Dokter)),''), p.PermintaanDPJP) as Nama_Dokter"),
                        DB::raw("ISNULL(spri.Diagnosis, '') as spri_diagnosis"),
                        DB::raw("ISNULL(spri.IndikasiRI, '') as spri_indikasi"),
                        DB::raw("ISNULL(spri.Spesialis, '') as spri_spesialis"),
                    ]);

                if ($cari) {
                    $q->where(fn ($qq) => $qq
                        ->where('rp.Nama_Pasien', 'like', "%{$cari}%")
                        ->orWhere('p.No_MR', 'like', "%{$cari}%")
                    );
                }

                return $q->orderBy('p.Tanggal', 'desc')->limit(200)->get()
                    ->map(fn ($r) => [
                        'No_MR'          => $r->No_MR,
                        'No_Reg'         => $r->No_Reg,
                        'Kode_Masuk'     => $r->Kode_Masuk,
                        'Nama_Pasien'    => $r->Nama_Pasien,
                        'jenis_kelamin'  => strtoupper($r->jenis_kelamin ?? ''),
                        'Kode_RuangM'    => $r->Kode_RuangM,
                        'Nama_RuangM'    => $r->Nama_RuangM,
                        'Kode_Bangsal'   => $r->Kode_Bangsal,
                        'Nama_Bangsal'   => $r->Nama_Bangsal,
                        'Dokter'         => $this->formatNamaDokter($r->Nama_Dokter ?? ''),
                        // prefill dari ASESMEN_SURAT_PERMINTAAN_RI (Perawatan = ICU)
                        'spri_diagnosis' => trim($r->spri_diagnosis ?? ''),
                        'spri_indikasi'  => trim($r->spri_indikasi  ?? ''),
                        'spri_spesialis' => trim($r->spri_spesialis ?? ''),
                    ])->toArray();
            }

            // Query rawat inap: pasien aktif di bangsal sesuai ward user
            $q = DB::connection('sqlsrv_rsus')
                ->table('PENDAFTARAN as p')
                ->join('REGISTER_PASIEN as rp', 'p.No_MR', '=', 'rp.No_MR')
                ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                ->leftJoin('M_BANGSAL as b', 'rm.Kode_Bangsal', '=', 'b.Kode_Bangsal')
                ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                ->where('p.Status', '1')
                ->where('p.Status_Pulang', 'Belum')
                ->where('p.Medis', 'RAWAT INAP')
                ->whereIn('rm.Kode_Bangsal', $wardIds)
                ->select([
                    'p.No_MR', 'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                    'rp.Nama_Pasien', 'rp.jenis_kelamin',
                    DB::raw("ISNULL(rm.Kode_RuangM, p.Kode_Ruang) as Kode_RuangM"),
                    DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as Nama_RuangM"),
                    DB::raw("ISNULL(b.Kode_Bangsal, '') as Kode_Bangsal"),
                    DB::raw("ISNULL(b.Nama_Bangsal, '') as Nama_Bangsal"),
                    DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(d.Nama_Dokter)),''), p.PermintaanDPJP) as Nama_Dokter"),
                ]);

            if ($cari) {
                $q->where(fn ($qq) => $qq
                    ->where('rp.Nama_Pasien', 'like', "%{$cari}%")
                    ->orWhere('p.No_MR', 'like', "%{$cari}%")
                );
            }

            return $q->orderBy('Nama_RuangM')->orderBy('rp.Nama_Pasien')->limit(200)->get()
                ->map(fn ($r) => [
                    'No_MR'         => $r->No_MR,
                    'No_Reg'        => $r->No_Reg,
                    'Kode_Masuk'    => $r->Kode_Masuk,
                    'Nama_Pasien'   => $r->Nama_Pasien,
                    'jenis_kelamin' => strtoupper($r->jenis_kelamin ?? ''),
                    'Kode_RuangM'   => $r->Kode_RuangM,
                    'Nama_RuangM'   => $r->Nama_RuangM,
                    'Kode_Bangsal'  => $r->Kode_Bangsal,
                    'Nama_Bangsal'  => $r->Nama_Bangsal,
                    'Dokter'        => $this->formatNamaDokter($r->Nama_Dokter ?? ''),
                ])->toArray();
        } catch (\Exception $e) {
            Log::error('[getPasienAktif] ' . $e->getMessage());
            return [];
        }
    }

    private function isIgdUser(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) return false;

        $unitKerja = strtoupper($user->unit_kerja ?? '');
        if (str_contains($unitKerja, 'IGD') || str_contains($unitKerja, 'UGD') || str_contains($unitKerja, 'EMERGENCY')) {
            return true;
        }

        foreach ($this->userWardIds() as $w) {
            $upper = strtoupper((string) $w);
            if (str_contains($upper, 'IGD') || str_contains($upper, 'UGD') || str_contains($upper, 'EMR')) {
                return true;
            }
        }

        return false;
    }

    private function formatNamaDokter(string $nama): string
    {
        $nama = trim($nama);
        if (empty($nama)) return '';
        return mb_convert_case(mb_strtolower($nama), MB_CASE_TITLE, 'UTF-8');
    }

    public function pasienAktifSearch(Request $request): JsonResponse
    {
        return response()->json($this->getPasienAktif(trim($request->query('q', ''))));
    }

    public function lookupPasien(Request $request): JsonResponse
    {
        $noMr = trim($request->query('No_MR', ''));
        if (! $noMr) return response()->json(['found' => false, 'message' => 'No_MR kosong.']);

        try {
            $pasien = RegistrasiPasien::where('No_MR', $noMr)->first();
        } catch (\Exception $e) {
            return response()->json(['found' => false, 'message' => 'Tidak dapat terhubung ke database RS.']);
        }

        if (! $pasien) return response()->json(['found' => false, 'message' => "No_MR '{$noMr}' tidak ditemukan."]);

        $kunjungans = collect();
        try {
            $rows = DB::connection('sqlsrv_rsus')
                ->table('PENDAFTARAN as p')
                ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                ->leftJoin('M_CARABAYAR as cb', 'p.Kode_Bayar', '=', 'cb.Kode_Bayar')
                ->leftJoin(
                    // Ambil semua diagnosis dari ASESMEN (tidak filter Perawatan) untuk Diagnosis RM
                    DB::raw("(SELECT No_Reg, MAX(Diagnosis) as Diagnosis FROM ASESMEN_SURAT_PERMINTAAN_RI GROUP BY No_Reg) as asmt"),
                    'p.No_Reg', '=', 'asmt.No_Reg'
                )
                ->leftJoin(
                    // Khusus ICU: ambil IndikasiRI & Spesialis hanya dari SPRI dengan Perawatan='ICU'
                    DB::raw("(SELECT No_Reg, MAX(IndikasiRI) as IndikasiRI, MAX(Spesialis) as Spesialis FROM ASESMEN_SURAT_PERMINTAAN_RI WHERE Perawatan = 'ICU' GROUP BY No_Reg) as spri_icu"),
                    'p.No_Reg', '=', 'spri_icu.No_Reg'
                )
                ->where('p.No_MR', $noMr)
                ->orderByDesc('p.Tanggal')
                ->select([
                    'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang', 'p.Kode_Dokter', 'p.Kode_Bayar',
                    DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                    DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(d.Nama_Dokter)),''), p.PermintaanDPJP) as nama_dokter"),
                    DB::raw("ISNULL(cb.Ket_Bayar, p.Kode_Bayar) as ket_bayar"),
                    'asmt.Diagnosis',
                    'spri_icu.IndikasiRI',
                    'spri_icu.Spesialis',
                ])->get();

            $kunjungans = $rows->map(fn ($r) => [
                'No_Reg'      => $r->No_Reg,
                'Kode_Masuk'  => $r->Kode_Masuk,
                'Dokter'      => $this->formatNamaDokter(trim($r->nama_dokter ?? '')),
                'Kode_Dokter' => trim($r->Kode_Dokter   ?? ''),
                'asal_ruang'  => trim($r->nama_asal_ruang ?? $r->Kode_Ruang ?? ''),
                'Diagnosis'   => trim($r->Diagnosis      ?? ''),
                'IndikasiRI'  => trim($r->IndikasiRI     ?? ''),
                'Spesialis'   => trim($r->Spesialis      ?? ''),
                'Kode_Bayar'  => trim($r->Kode_Bayar    ?? ''),
                'jaminan'     => $this->formatNamaDokter(trim($r->ket_bayar ?? '')),
            ]);
        } catch (\Exception $e) {
            Log::error('[lookupPasien] ' . $e->getMessage());
        }

        $prefill = IcuSpriInternal::where('No_MR', $noMr)->latest()->first(['Diagnosis', 'IndikasiRI', 'asal_ruang', 'Dokter']);

        return response()->json([
            'found'       => true,
            'No_MR'       => $pasien->No_MR,
            'nama_pasien' => $pasien->Nama_Pasien,
            'kunjungans'  => $kunjungans,
            'prefill'     => $prefill?->only(['Diagnosis', 'IndikasiRI', 'asal_ruang', 'Dokter']),
        ]);
    }

    public function storeSpri(Request $request): RedirectResponse
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

        $bu = IcuSpriInternal::create([
            ...$validated,
            'NameUser' => $this->actor(),
            'status'   => 'pending_icu',
        ]);

        $nama = $bu->No_MR;
        try {
            $pasien = RegistrasiPasien::where('No_MR', $bu->No_MR)->first();
            if ($pasien) $nama = $pasien->Nama_Pasien . ' (' . $bu->No_MR . ')';
        } catch (\Exception) {}

        $this->activityLog->buatSpri($bu->id, $nama);

        return back()->with('success', "BU (Booking ICU) untuk {$nama} berhasil dikirim ke ICU.");
    }

    public function updateSpri(Request $request, int $id): RedirectResponse
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
            return back()->with('error', 'BU tidak dapat diedit karena sudah diproses.');
        }

        $bu->update($validated);

        $nama = $bu->No_MR;
        try {
            $pasien = RegistrasiPasien::where('No_MR', $bu->No_MR)->first();
            if ($pasien) $nama = $pasien->Nama_Pasien . ' (' . $bu->No_MR . ')';
        } catch (\Exception) {}

        $this->activityLog->log(
            'Edit BookingICU',
            "Edit BookingICU {$nama}",
            'spri_internal',
            $bu->id,
            'IcuSpriInternal'
        );

        return back()->with('success', "Booking ICU {$nama} berhasil diupdate.");
    }

    public function batalSpri(int $id): RedirectResponse
    {
        $bu = IcuSpriInternal::findOrFail($id);

        // Hanya bisa batalkan jika masih pending atau waiting_list
        if (!in_array($bu->status, ['pending_admisi', 'pending_icu', 'waiting_list'])) {
            return back()->with('error', 'Booking ICU tidak dapat dibatalkan.');
        }

        $nama = $bu->No_MR;
        try {
            $pasien = RegistrasiPasien::where('No_MR', $bu->No_MR)->first();
            if ($pasien) $nama = $pasien->Nama_Pasien . ' (' . $bu->No_MR . ')';
        } catch (\Exception) {}

        $oldStatus = $bu->status;

        $bu->update([
            'status' => 'dibatalkan',
            'alasan_tolak' => 'Dibatalkan oleh ' . $this->actor(),
        ]);

        $this->activityLog->log(
            'Batal BookingICU',
            "Batalkan BookingICU {$nama} (status sebelumnya: {$oldStatus})",
            'spri_internal',
            $bu->id,
            'IcuSpriInternal'
        );

        return back()->with('success', "Booking ICU {$nama} berhasil dibatalkan.");
    }

    public function deleteSpri(int $id): RedirectResponse
    {
        $bu = IcuSpriInternal::findOrFail($id);

        // Hanya bisa hapus jika masih pending atau ditolak/dibatalkan
        if (!in_array($bu->status, ['pending_admisi', 'pending_icu', 'ditolak', 'dibatalkan'])) {
            return back()->with('error', 'Booking ICU tidak dapat dihapus karena sudah diproses.');
        }

        $nama = $bu->No_MR;
        try {
            $pasien = RegistrasiPasien::where('No_MR', $bu->No_MR)->first();
            if ($pasien) $nama = $pasien->Nama_Pasien . ' (' . $bu->No_MR . ')';
        } catch (\Exception) {}

        $this->activityLog->log(
            'Hapus BookingICU',
            "Hapus BookingICU {$nama}",
            'spri_internal',
            $bu->id,
            'IcuSpriInternal'
        );

        $bu->delete();

        return back()->with('success', "Booking ICU {$nama} berhasil dihapus.");
    }

        private function hitungLamaProses($mulai, $selesai): ?string
    {
        if (!$mulai || !$selesai) {
            return null;
        }

        $diff = $mulai->diff($selesai);

        $hasil = [];

        if ($diff->d > 0) {
            $hasil[] = "{$diff->d} hari";
        }

        if ($diff->h > 0) {
            $hasil[] = "{$diff->h} jam";
        }

        if ($diff->i > 0) {
            $hasil[] = "{$diff->i} menit";
        }

        return empty($hasil) ? "0 menit" : implode(' ', $hasil);
    }

    private function format(IcuSpriInternal $s, $pasienMap = null, ?array $jaminan = null): array
    {
        $pasien = $pasienMap ? ($pasienMap[$s->No_MR] ?? null) : $s->pasien;
        return [
            'id'             => $s->id,
            'No_MR'          => $s->No_MR,
            'No_Reg'         => $s->No_Reg,
            'nama_pasien'    => $pasien?->Nama_Pasien ?? '-',
            'jenis_kelamin'  => strtoupper($pasien?->Jenis_Kelamin ?? $pasien?->jenis_kelamin ?? ''),
            'Diagnosis'      => $s->Diagnosis,
            'IndikasiRI'     => $s->IndikasiRI,
            'kebutuhan_bed'  => $s->kebutuhan_bed,
            'asal_ruang'     => $s->asal_ruang,
            'Dokter'         => $s->Dokter,
            'spesialis'      => $s->spesialis,
            'Keterangan'     => $s->Keterangan,
            'catatan_admisi' => $s->catatan_admisi,
            'nama_bed'       => $s->nama_bed,
            'status'         => $s->status,
            'status_label'   => $s->statusLabel(),
            'alasan_tolak'   => $s->alasan_tolak,
            'jaminan_kode'   => $jaminan['kode'] ?? null,
            'jaminan_nama'   => $jaminan['nama'] ?? null,
            // waiting list
            'waiting_alasan'       => $s->waiting_alasan,
            'waiting_estimasi'     => $s->waiting_estimasi?->format('Y-m-d H:i'),
            'waiting_estimasi_fmt' => $s->waiting_estimasi?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'waiting_by'           => $s->waiting_by,
            // tracking aksi
            'created_by'     => $s->NameUser ?? '-',
            'approved_by'    => $s->approved_by,
            'approved_at'    => $s->approved_at?->format('Y-m-d H:i'),
            'approved_at_fmt'=> $s->approved_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'verified_by'    => $s->verified_by,
            'verified_at'    => $s->verified_at?->format('Y-m-d H:i'),
            'verified_at_fmt'=> $s->verified_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'created_at'     => $s->created_at?->format('Y-m-d H:i'),
            'created_at_fmt' => $s->created_at?->format('d/m/Y H:i'),
            'lama_proses'      => $this->hitungLamaProses($s->created_at,$s->verified_at),
        ];
    }
}
