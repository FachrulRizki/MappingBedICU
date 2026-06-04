<?php

namespace App\Http\Controllers;

use App\Models\IcuAdmision;
use App\Models\MKelas;
use App\Models\MRuangMaster;
use App\Models\Pendaftaran;
use App\Models\RegistrasiPasien;
use App\Models\Spri;
use App\Models\StatusKamar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class IcuDashboardController extends Controller
{
    // ─── Eager load standar untuk semua query admision ───────────────────
    private array $withAdmision = [
        'pasien',
        'pendaftaran',
        'pendaftaran.spriAktif',
        'bed',
        'bed.ruang',         // M_RUANG_MASTER
        'bed.ruang.kelas',   // M_KELAS → Nama_Kelas
    ];

    // ─────────────────────────────────────────────────────────────────────
    // DASHBOARD
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        $w = $this->withAdmision;

        $tahapDaftar      = IcuAdmision::with($w)->where('status', 'daftar')->get();
        $tahapIgd         = IcuAdmision::with($w)->where('status', 'igd_periksa')->get();
        $tahapSpri        = IcuAdmision::with($w)->where('status', 'spri_dibuat')->get();
        $tahapNungguKamar = IcuAdmision::with($w)->where('status', 'waiting_icu')->get();
        $tahapBooking     = IcuAdmision::with($w)->where('status', 'booking_icu')->get();
        $tahapDiIcu       = IcuAdmision::with($w)->where('status', 'di_icu')->get();

        // Semua kamar dengan join ruang + kelas untuk dapat Nama_Kelas
        $semuaKamar = StatusKamar::with('ruang.kelas')->get();

        // Kamar kosong dikelompokkan per Kode_Kelas untuk matching di view
        $kamarKosong = StatusKamar::with('ruang.kelas')
            ->where('Status', 'KOSONG')
            ->get();

        // Master kelas untuk dropdown SPRI
        $masterKelas = MKelas::all();

        return view('dashboard', compact(
            'tahapDaftar', 'tahapIgd', 'tahapSpri',
            'tahapNungguKamar', 'tahapBooking', 'tahapDiIcu',
            'semuaKamar', 'kamarKosong', 'masterKelas'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 1: DAFTARKAN PASIEN BARU
    // ─────────────────────────────────────────────────────────────────────

    public function tambahPasien(Request $request)
    {
        $request->validate([
            'Nama_Pasien'  => 'required|string|max:200',
            'No_Identitas' => 'required|string|max:50',
            'KartuBPJS'    => 'nullable|string|max:50',
        ]);

        $noMR  = 'MR-'  . strtoupper(Str::random(6));
        $noReg = 'REG-' . date('Y') . '-' . strtoupper(Str::random(5));

        $pasien = RegistrasiPasien::create([
            'No_MR'        => $noMR,
            'Nama_Pasien'  => $request->Nama_Pasien,
            'tgl_regist'   => Carbon::now(),
            'No_Identitas' => $request->No_Identitas,
            'KartuBPJS'    => $request->KartuBPJS,
            'NameUser'     => 'petugas_daftar',
        ]);

        Pendaftaran::create([
            'No_Reg'     => $noReg,
            'No_MR'      => $noMR,
            'Kode_Masuk' => 'IGD',
            'Kode_Asal'  => 'UGD',
            'NameUser'   => 'petugas_daftar',
        ]);

        IcuAdmision::create([
            'No_Reg' => $noReg,
            'No_MR'  => $noMR,
            'status' => 'daftar',
        ]);

        return back()->with('success', "Pasien {$pasien->Nama_Pasien} berhasil didaftarkan. No. MR: {$noMR}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 2: KIRIM KE IGD
    // ─────────────────────────────────────────────────────────────────────

    public function kirimIgd($id)
    {
        $admision = IcuAdmision::with('pasien')->findOrFail($id);
        $admision->update(['status' => 'igd_periksa']);

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} dikirim ke IGD untuk diperiksa.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 3: BUAT SPRI
    // required_bed_type = Kode_Kelas dari M_KELAS (ICUNV, HCU, ICU, CVCU)
    // ─────────────────────────────────────────────────────────────────────

    public function buatSpri(Request $request, $id)
    {
        $request->validate([
            'Diagnosis'         => 'required|string|max:255',
            'IndikasiRI'        => 'required|string|max:255',
            'required_bed_type' => 'required|exists:m_kelas,Kode_Kelas',
        ]);

        $admision = IcuAdmision::with('pendaftaran')->findOrFail($id);

        Spri::updateOrCreate(
            ['No_Reg' => $admision->No_Reg],
            [
                'Diagnosis'  => $request->Diagnosis,
                'IndikasiRI' => $request->IndikasiRI,
                'spesialis'  => $request->spesialis ?? '-',
                'Dokter'     => $request->Dokter ?? 'Dokter IGD',
                'NameUser'   => 'dr_igd',
                'Perawatan'  => 'ICU',
                'Keterangan' => $request->Keterangan ?? '-',
                'Status'     => 'draft',
            ]
        );

        $admision->update([
            'status'            => 'spri_dibuat',
            'required_bed_type' => $request->required_bed_type,
            'match_status'      => 'waiting',
        ]);

        $namaKelas = MKelas::find($request->required_bed_type)?->Nama_Kelas ?? $request->required_bed_type;

        return back()->with('success', "SPRI dibuat. Kebutuhan bed: {$namaKelas} ({$request->required_bed_type})");
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 4: APPROVE SPRI
    // ─────────────────────────────────────────────────────────────────────

    public function approveSpri($id)
    {
        $admision = IcuAdmision::with('pendaftaran.spriAktif', 'pasien')->findOrFail($id);

        if ($admision->pendaftaran?->spriAktif) {
            $admision->pendaftaran->spriAktif->update(['Status' => 'approved']);
        }

        $admision->update(['status' => 'waiting_icu']);

        return back()->with('success', "SPRI disetujui. Pasien {$admision->pasien->Nama_Pasien} masuk daftar tunggu ICU.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 5: ALOKASI BED
    // Matching: STATUS_KAMAR.Status='KOSONG' + M_RUANG_MASTER.Kode_Kelas = required_bed_type
    // ─────────────────────────────────────────────────────────────────────

    public function alokasiBed(Request $request, $id)
    {
        $request->validate([
            'Kode_Ruang' => 'required|exists:status_kamar,Kode_Ruang',
        ]);

        $admision = IcuAdmision::with('pasien')->findOrFail($id);

        // Validasi: bed harus KOSONG dan Kode_Kelas-nya cocok
        $bed = StatusKamar::with('ruang.kelas')
            ->where('Kode_Ruang', $request->Kode_Ruang)
            ->where('Status', 'KOSONG')
            ->whereHas('ruang', fn($q) => $q->where('Kode_Kelas', $admision->required_bed_type))
            ->firstOrFail();

        // Tandai bed → BOOKING
        $bed->update(['Status' => 'BOOKING']);

        // Update admision
        $admision->update([
            'status'           => 'booking_icu',
            'allocated_bed_id' => $bed->Kode_Ruang,
            'match_status'     => 'matched',
        ]);

        $namaRuang = $bed->ruang?->Nama_RuangM ?? $bed->Kode_Ruang;

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} mendapat kamar {$namaRuang}.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 6: DIANTAR KE RUANGAN
    // ─────────────────────────────────────────────────────────────────────

    public function masukRuangan($id)
    {
        $admision = IcuAdmision::with('bed', 'pasien')->findOrFail($id);

        if ($admision->bed) {
            $admision->bed->update([
                'Status' => 'ISI',
                'No_MR'  => $admision->No_MR,
            ]);
        }

        $admision->update(['status' => 'di_icu']);

        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id;

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} masuk ke {$namaRuang}.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // STEP 7: PULANGKAN PASIEN
    // ─────────────────────────────────────────────────────────────────────

    public function pulangkanPasien($id)
    {
        $admision = IcuAdmision::with('bed.ruang', 'pasien')->findOrFail($id);

        $namaRuang = $admision->bed?->ruang?->Nama_RuangM ?? $admision->allocated_bed_id ?? '-';

        if ($admision->bed) {
            $admision->bed->update([
                'Status' => 'KOSONG',
                'No_MR'  => null,
            ]);
        }

        $admision->update([
            'status'           => 'pulang',
            'allocated_bed_id' => null,
            'match_status'     => null,
        ]);

        return back()->with('success', "Pasien {$admision->pasien->Nama_Pasien} dipulangkan. {$namaRuang} kembali kosong.");
    }
}
