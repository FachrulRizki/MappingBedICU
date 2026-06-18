<?php

namespace App\Http\Controllers\Icu;

use App\Http\Controllers\Controller;
use App\Models\IcuSpriInternal;
use App\Models\MRuangMaster;
use App\Models\RegistrasiPasien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MenuPetugasController extends Controller
{
    private function actor(): string
    {
        return auth()->user()?->name ?? 'petugas_ruang';
    }

    private function actorNames(): array
    {
        $user = auth()->user();
        if (! $user) return ['petugas_ruang'];
        $names = [$user->name];
        if ($user->keycloak_username) $names[] = $user->keycloak_username;
        if ($user->username && $user->username !== $user->name) $names[] = $user->username;
        return array_unique(array_filter($names));
    }

    /**
     * Cek nama kolom user di tabel icu_spri_internal.
     * Beberapa environment memakai 'NameUser', lainnya 'NamaUser'.
     * Return nama kolom yang benar.
     */
    private function nameUserColumn(): string
    {
        static $col = null;
        if ($col !== null) return $col;
        try {
            $cols = DB::connection('mysql')->getSchemaBuilder()->getColumnListing('icu_spri_internal');
            $col  = in_array('NamaUser', $cols) ? 'NamaUser' : 'NameUser';
        } catch (\Exception) {
            $col = 'NameUser'; // fallback ke migration default
        }
        return $col;
    }

    private function userWardIds(): array
    {
        return auth()->user()?->getWardIdsArray() ?? [];
    }

    public function index(Request $request): Response
    {
        $fNama    = trim($request->query('nama', ''));
        $fTgl     = $request->query('tgl', '');
        $fStatus  = $request->query('status', '');
        // Kolom yang bisa di-sort langsung di DB
        $dbSortAllowed = ['created_at', 'status'];
        $sortBy   = $request->query('sort', 'created_at');
        $sortDir  = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $q = IcuSpriInternal::query()->whereIn($this->nameUserColumn(), $this->actorNames());
        if ($fStatus) $q->where('status', $fStatus);
        if ($fNama) {
            $ids = RegistrasiPasien::where('Nama_Pasien', 'like', "%{$fNama}%")->pluck('No_MR')->toArray();
            $q->where(fn ($qq) => $qq->whereIn('No_MR', $ids)->orWhere('No_MR', 'like', "%{$fNama}%"));
        }
        if ($fTgl) $q->whereDate('created_at', $fTgl);
        // Hanya sort di DB jika kolom ada di tabel; nama_pasien di-sort di collection
        if (in_array($sortBy, $dbSortAllowed)) {
            $q->orderBy($sortBy, $sortDir);
        } else {
            $q->orderBy('created_at', 'desc');
        }

        $data      = $q->get();
        $pasienMap = RegistrasiPasien::whereIn('No_MR', $data->pluck('No_MR')->unique())->get()->keyBy('No_MR');

        $spriList = $data->map(fn ($s) => $this->format($s, $pasienMap));
        // Sort by nama_pasien dilakukan di collection setelah data di-format
        if ($sortBy === 'nama_pasien') {
            $spriList = $sortDir === 'asc'
                ? $spriList->sortBy(fn ($i) => strtolower($i['nama_pasien']))->values()
                : $spriList->sortByDesc(fn ($i) => strtolower($i['nama_pasien']))->values();
        }

        // Summary dihitung dari SEMUA data user (tanpa filter status), agar card summary selalu akurat
        $allData = IcuSpriInternal::query()->whereIn($this->nameUserColumn(), $this->actorNames())->get();
        $summary = [
            'total'          => $allData->count(),
            'pending_admisi' => $allData->filter(fn ($i) => $i->status === 'pending_admisi')->count(),
            'pending_icu'    => $allData->filter(fn ($i) => $i->status === 'pending_icu')->count(),
            'bed_verified'   => $allData->filter(fn ($i) => $i->status === 'bed_verified')->count(),
            'ditolak'        => $allData->filter(fn ($i) => $i->status === 'ditolak')->count(),
        ];

        Log::info('[DEBUG] actorNames: ' . json_encode($this->actorNames()));
        Log::info('[DEBUG] ward_ids: ' . json_encode(auth()->user()?->ward_ids));

        $data = $q->get();
        Log::info('[DEBUG] total rows: ' . $data->count());

        // Cek langsung di DB nama kolom yang dipakai
        Log::info('[DEBUG] nameUserColumn: ' . $this->nameUserColumn());

        return Inertia::render('Icu/MenuPetugas', [
            'spriList'     => $spriList,
            'summary'      => $summary,
            'filters'      => compact('fNama', 'fTgl', 'fStatus', 'sortBy', 'sortDir'),
            'pasienAktif'  => $this->getPasienAktif(''),
            'wardIds'      => $this->userWardIds(),
            'authProvider' => auth()->user()?->auth_provider ?? 'local',
            'isIgdUser'    => $this->isIgdUser(),
            'unitKerja'    => auth()->user()?->unit_kerja ?? '',
            'kamarKosong'  => MRuangMaster::bedKosong(),
            'masterKelas'  => MRuangMaster::jenisIcuTersedia(),
            'flash'        => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    private function getPasienAktif(string $cari): array
    {
        $user = auth()->user();
        if (! $user) return [];

        if ($user->auth_provider === 'keycloak') {
            return $this->getPasienAktifSSO($cari);
        }

        return $this->getPasienAktifLokal($cari);
    }

    private function isIgdUser(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

        // Cek dari unit_kerja field
        $unitKerja = strtoupper($user->unit_kerja ?? '');
        if (str_contains($unitKerja, 'IGD') || str_contains($unitKerja, 'UGD') || str_contains($unitKerja, 'EMERGENCY')) {
            return true;
        }

        // Cek apakah ada kode IGD/UGD di ward_ids
        foreach ($this->userWardIds() as $w) {
            $upper = strtoupper((string) $w);
            if (str_contains($upper, 'IGD') || str_contains($upper, 'UGD') || str_contains($upper, 'EMR')) {
                return true;
            }
        }

        return false;
    }

    private function getPasienAktifSSO(string $cari): array
    {
        $wardIds = $this->userWardIds();
        if (empty($wardIds) || ! RegistrasiPasien::rsusAvailable()) return [];

        try {
            $isIgd = $this->isIgdUser();

            $q = DB::connection('sqlsrv_rsus')
                ->table('PENDAFTARAN as p')
                ->join('REGISTER_PASIEN as rp', 'p.No_MR', '=', 'rp.No_MR')
                ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                ->leftJoin('M_BANGSAL as b', 'rm.Kode_Bangsal', '=', 'b.Kode_Bangsal')
                // Join tabel DOKTER untuk resolve nama dari kode
                ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                ->where('p.Status', '1')
                ->where('p.Status_Pulang', 'Belum')
                ->select([
                    'p.No_MR', 'p.No_Reg', 'p.Kode_Masuk', 'p.Kode_Ruang',
                    'p.PermintaanDPJP',
                    'rp.Nama_Pasien', 'rp.jenis_kelamin',
                    DB::raw("ISNULL(rm.Kode_RuangM, p.Kode_Ruang) as Kode_RuangM"),
                    DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as Nama_RuangM"),
                    DB::raw("ISNULL(b.Kode_Bangsal, '') as Kode_Bangsal"),
                    DB::raw("ISNULL(b.Nama_Bangsal, '') as Nama_Bangsal"),
                    // Prioritaskan nama dari tabel DOKTER, fallback ke PermintaanDPJP
                    DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(d.Nama_Dokter)),''), p.PermintaanDPJP) as Nama_Dokter"),
                ]);

            if ($isIgd) {
                $q->where('p.Kode_Masuk', '1');
                Log::info('[getPasienAktifSSO] Mode IGD, filter Kode_Masuk=1');
            } else {
                $q->where('p.Medis', 'RAWAT INAP')
                  ->whereIn('rm.Kode_Bangsal', $wardIds);
                Log::info('[getPasienAktifSSO] Mode Bangsal, ward_ids: ' . json_encode($wardIds));
            }

            if ($cari) {
                $q->where(fn ($qq) => $qq
                    ->where('rp.Nama_Pasien', 'like', "%{$cari}%")
                    ->orWhere('p.No_MR', 'like', "%{$cari}%")
                );
            }

            $results = $q->orderBy('Nama_RuangM')->orderBy('rp.Nama_Pasien')->limit(200)->get();
            Log::info('[getPasienAktifSSO] Jumlah: ' . $results->count());

            return $results->map(fn ($r) => [
                'No_MR'         => $r->No_MR,
                'No_Reg'        => $r->No_Reg,
                'Kode_Masuk'    => $r->Kode_Masuk,
                'Nama_Pasien'   => $r->Nama_Pasien,
                'jenis_kelamin' => strtoupper($r->jenis_kelamin ?? ''),
                'Kode_RuangM'   => $r->Kode_RuangM,
                'Nama_RuangM'   => $r->Nama_RuangM,
                'Kode_Bangsal'  => $r->Kode_Bangsal,
                'Nama_Bangsal'  => $r->Nama_Bangsal,
                // Format nama dokter ke Title Case agar tidak ALL CAPS
                'Dokter'        => $this->formatNamaDokter($r->Nama_Dokter ?? ''),
            ])->toArray();

        } catch (\Exception $e) {
            Log::error('[getPasienAktifSSO] Error: ' . $e->getMessage());
            return [];
        }
    }

    private function formatNamaDokter(string $nama): string
    {
        $nama = trim($nama);
        if (empty($nama)) return '';
        return mb_convert_case(mb_strtolower($nama), MB_CASE_TITLE, 'UTF-8');
    }

    private function getPasienAktifLokal(string $cari): array
    {
        try {
            // Join ke pendaftaran agar bisa tampilkan No_Reg, dan groupBy No_MR agar tidak double
            $q = \Illuminate\Support\Facades\DB::connection('mysql')
                ->table('registrasi_pasien as rp')
                ->leftJoin(
                    \Illuminate\Support\Facades\DB::raw(
                        '(SELECT No_MR, MAX(No_Reg) as No_Reg, MAX(Kode_Asal) as Kode_Asal, MAX(PermintaanDPJP) as Dokter FROM pendaftaran GROUP BY No_MR) as p'
                    ),
                    'rp.No_MR', '=', 'p.No_MR'
                )
                ->leftJoin('m_ruang_master as rm', 'p.Kode_Asal', '=', 'rm.Kode_RuangM')
                ->select([
                    'rp.No_MR',
                    'p.No_Reg',
                    'rp.Nama_Pasien',
                    'rp.jenis_kelamin',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal, 'Data Lokal (Dev)') as Nama_RuangM"),
                    \Illuminate\Support\Facades\DB::raw("COALESCE(rm.Kode_RuangM, 'LOKAL') as Kode_RuangM"),
                ])
                ->groupBy('rp.No_MR', 'rp.Nama_Pasien', 'rp.jenis_kelamin',
                          'p.No_Reg', 'p.Kode_Asal', 'p.Dokter',
                          'rm.Nama_RuangM', 'rm.Kode_RuangM');

            if ($cari) {
                $q->where(fn ($qq) => $qq
                    ->where('rp.Nama_Pasien', 'like', "%{$cari}%")
                    ->orWhere('rp.No_MR', 'like', "%{$cari}%")
                );
            }

            return $q->orderBy('rp.Nama_Pasien')->limit(50)->get()
                ->map(fn ($r) => [
                    'No_MR'         => $r->No_MR,
                    'No_Reg'        => $r->No_Reg,
                    'Nama_Pasien'   => $r->Nama_Pasien,
                    'jenis_kelamin' => strtoupper($r->jenis_kelamin ?? ''),
                    'Kode_RuangM'   => $r->Kode_RuangM,
                    'Nama_RuangM'   => $r->Nama_RuangM,
                    'Kode_Bangsal'  => null,
                    'Nama_Bangsal'  => 'MySQL Lokal',
                ])->toArray();
        } catch (\Exception $e) {
            Log::error('[getPasienAktifLokal] ' . $e->getMessage());
            return [];
        }
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
            if (RegistrasiPasien::rsusAvailable()) {
                try {
                    $rows = DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        ->leftJoin('DOKTER as d', 'p.Kode_Dokter', '=', 'd.Kode_Dokter')
                        ->leftJoin(DB::raw('(SELECT No_Reg, MAX(Diagnosis) as Diagnosis FROM ASESMEN_SURAT_PERMINTAAN_RI GROUP BY No_Reg) as asmt'), 'p.No_Reg', '=', 'asmt.No_Reg')
                        ->where('p.No_MR', $noMr)->orderByDesc('p.Tanggal')
                        ->select(['p.No_Reg','p.Kode_Masuk','p.Kode_Ruang','p.PermintaanDPJP','p.Kode_Dokter',
                                  DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                                  DB::raw("ISNULL(d.Nama_Dokter, p.PermintaanDPJP) as nama_dokter"),
                                  'asmt.Diagnosis'])->get();
                } catch (\Exception $e) {
                    Log::warning('[lookupPasien] Dokter join failed: ' . $e->getMessage());
                    $rows = DB::connection('sqlsrv_rsus')
                        ->table('PENDAFTARAN as p')
                        ->leftJoin('M_RUANG_MASTER as rm', 'p.Kode_Ruang', '=', 'rm.Kode_RuangM')
                        ->where('p.No_MR', $noMr)->orderByDesc('p.Tanggal')
                        ->select(['p.No_Reg','p.Kode_Masuk','p.Kode_Ruang','p.PermintaanDPJP','p.Kode_Dokter',
                                  DB::raw("ISNULL(rm.Nama_RuangM, p.Kode_Ruang) as nama_asal_ruang"),
                                  'p.PermintaanDPJP as nama_dokter', DB::raw("NULL as Diagnosis")])->get();
                }
                $kunjungans = $rows->map(fn ($r) => [
                    'No_Reg'      => $r->No_Reg,
                    'Dokter'      => trim($r->nama_dokter    ?? $r->PermintaanDPJP ?? ''),
                    'Kode_Dokter' => trim($r->Kode_Dokter   ?? ''),
                    'asal_ruang'  => trim($r->nama_asal_ruang ?? $r->Kode_Ruang ?? ''),
                    'Diagnosis'   => trim($r->Diagnosis      ?? ''),
                ]);
            } else {
                $rows = DB::connection('mysql')->table('pendaftaran as p')
                    ->leftJoin('m_ruang_master as rm', 'p.Kode_Asal', '=', 'rm.Kode_RuangM')
                    ->where('p.No_MR', $noMr)->orderByDesc('p.created_at')
                    ->select(['p.No_Reg','p.Kode_Masuk','p.Kode_Asal',
                              DB::raw("COALESCE(p.PermintaanDPJP,'') as Dokter"),
                              DB::raw("COALESCE(p.Kode_Dokter,'') as Kode_Dokter"),
                              DB::raw("COALESCE(rm.Nama_RuangM, p.Kode_Asal,'') as nama_asal_ruang"),
                              DB::raw("NULL as Diagnosis")])->get();
                $kunjungans = $rows->map(fn ($r) => [
                    'No_Reg'      => $r->No_Reg,
                    'Dokter'      => $r->Dokter ?? '',
                    'Kode_Dokter' => $r->Kode_Dokter ?? '',
                    'asal_ruang'  => $r->nama_asal_ruang ?? '',
                    'Diagnosis'   => '',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[lookupPasien] ' . $e->getMessage());
        }

        $prefill = IcuSpriInternal::where('No_MR', $noMr)->latest()->first(['Diagnosis','IndikasiRI','asal_ruang','Dokter']);

        return response()->json([
            'found'       => true,
            'No_MR'       => $pasien->No_MR,
            'nama_pasien' => $pasien->Nama_Pasien,
            'kunjungans'  => $kunjungans,
            'prefill'     => $prefill ? $prefill->only(['Diagnosis','IndikasiRI','asal_ruang','Dokter']) : null,
        ]);
    }

    public function storeSpri(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'No_MR'      => 'required|string|max:20',
            'No_Reg'     => 'required|string|max:20',
            'Diagnosis'  => 'required|string|max:255',
            'IndikasiRI' => 'required|string|max:255',
            'asal_ruang' => 'nullable|string|max:100',
            'Dokter'     => 'nullable|string|max:100',
            'spesialis'  => 'nullable|string|max:100',
            'Keterangan' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $spri = IcuSpriInternal::create([
            ...$validated,
            $this->nameUserColumn() => $this->actor(),
            'status'   => 'pending_admisi',
        ]);

        $nama = $spri->No_MR;
        // Coba ambil nama pasien untuk flash message yang informatif
        try {
            $pasien = RegistrasiPasien::where('No_MR', $spri->No_MR)->first();
            if ($pasien) $nama = $pasien->Nama_Pasien . ' (' . $spri->No_MR . ')';
        } catch (\Exception) {}

        return back()->with('success', "SPRI untuk {$nama} berhasil dikirim ke Admisi.");
    }

    private function format(IcuSpriInternal $s, $pasienMap = null): array
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
            'approved_by'    => $s->approved_by,
            'verified_by'    => $s->verified_by,
            'created_at'     => $s->created_at?->format('Y-m-d H:i'),
            'created_at_fmt' => $s->created_at?->format('d/m/Y H:i'),
        ];
    }
}
