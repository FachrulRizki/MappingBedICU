<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MKelas;
use App\Models\MRuangMaster;
use App\Models\StatusKamar;
use App\Models\RegistrasiPasien;
use App\Models\Pendaftaran;
use App\Models\Spri;
use App\Models\IcuAdmision;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Disable FK checks agar truncate tidak error
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        IcuAdmision::truncate();
        Spri::truncate();
        Pendaftaran::truncate();
        RegistrasiPasien::truncate();
        StatusKamar::truncate();
        MRuangMaster::truncate();
        MKelas::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── 1. M_KELAS — master jenis bed ────────────────────────────────
        // Data mengikuti kelas yang ada di DB existing
        $kelasData = [
            ['Kode_Kelas' => 'ICU',   'Nama_Kelas' => 'ICU',              'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'ICUNV', 'Nama_Kelas' => 'ICU Non Ventilator','Kelas' => 'ICU'],
            ['Kode_Kelas' => 'HCU',   'Nama_Kelas' => 'HCU',              'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'CVCU',  'Nama_Kelas' => 'CVCU',             'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'BPICU', 'Nama_Kelas' => 'ICU BPJS',         'Kelas' => 'ICU'],
        ];
        foreach ($kelasData as $k) {
            MKelas::create($k);
        }

        // ── 2. M_RUANG_MASTER — master bed individual ─────────────────────
        // Mengikuti pola naming dari DB existing:
        //   HC301-HC303 → ICUNV  (HIGH CARE UNIT, ICU Non Ventilator)
        //   HC304-HC312 → HCU    (HIGH CARE UNIT, High Care Unit)
        //   HC313-HC315 → ICU    (HIGH CARE UNIT, ICU biasa)
        //   ICU A1-A8   → ICU
        //   ICU C6-C9   → ICU
        //   ICU F11-F15 → ICU
        //   CVCU D1-D7  → CVCU
        $ruangs = [
            // ICUNV — ICU Non Ventilator
            ['Kode_RuangM'=>'HC301','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 301','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC302','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 302','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC303','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 303','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            // HCU — High Care Unit
            ['Kode_RuangM'=>'HC304','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 304','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC305','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 305','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC306','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 306','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC307','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 307','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC308','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 308','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            // ICU standar
            ['Kode_RuangM'=>'HC313','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'HIGH CARE UNIT 313','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC314','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'HIGH CARE UNIT 314','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC315','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'HIGH CARE UNIT 315','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA2','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A2','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA3','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A3','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA4','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A4','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUB1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU B1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            // CVCU
            ['Kode_RuangM'=>'CVCD1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD2','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D2','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD3','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D3','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
        ];
        foreach ($ruangs as $r) {
            MRuangMaster::create($r);
        }

        // ── 3. STATUS_KAMAR — occupancy bed real-time ─────────────────────
        // Skenario: beberapa KOSONG, beberapa ISI, satu BOOKING
        // Status uppercase mengikuti DB existing
        $statusKamars = [
            // ICUNV
            ['Kode_Ruang'=>'HC301','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-005','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC302','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC303','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            // HCU
            ['Kode_Ruang'=>'HC304','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC305','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC306','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-099','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC307','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC308','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            // ICU standar
            ['Kode_Ruang'=>'HC313','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC314','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-088','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC315','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA2','Kode_Bangsal'=>'ICU','Status'=>'BOOKING','KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA3','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA4','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUB1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            // CVCU
            ['Kode_Ruang'=>'CVCD1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD2','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD3','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-077','Oksigen'=>'ADA'],
        ];
        foreach ($statusKamars as $sk) {
            StatusKamar::create($sk);
        }

        // ── 4. REGISTRASI PASIEN ──────────────────────────────────────────
        $pasiens = [
            ['No_MR'=>'MR-001','Nama_Pasien'=>'Budi Santoso',  'tgl_regist'=>Carbon::now()->subDays(5),'No_Identitas'=>'3201010001','KartuBPJS'=>'0001111001','NameUser'=>'admin_daftar'],
            ['No_MR'=>'MR-002','Nama_Pasien'=>'Siti Rahayu',   'tgl_regist'=>Carbon::now()->subDays(4),'No_Identitas'=>'3201010002','KartuBPJS'=>'0001111002','NameUser'=>'admin_daftar'],
            ['No_MR'=>'MR-003','Nama_Pasien'=>'Ahmad Fauzi',   'tgl_regist'=>Carbon::now()->subDays(3),'No_Identitas'=>'3201010003','KartuBPJS'=>null,        'NameUser'=>'admin_daftar'],
            ['No_MR'=>'MR-004','Nama_Pasien'=>'Dewi Lestari',  'tgl_regist'=>Carbon::now()->subDays(2),'No_Identitas'=>'3201010004','KartuBPJS'=>'0001111004','NameUser'=>'admin_daftar'],
            ['No_MR'=>'MR-005','Nama_Pasien'=>'Hendra Wijaya', 'tgl_regist'=>Carbon::now()->subDays(1),'No_Identitas'=>'3201010005','KartuBPJS'=>'0001111005','NameUser'=>'admin_daftar'],
            ['No_MR'=>'MR-006','Nama_Pasien'=>'Rina Marlina',  'tgl_regist'=>Carbon::now()->subDays(1),'No_Identitas'=>'3201010006','KartuBPJS'=>null,        'NameUser'=>'admin_daftar'],
            ['No_MR'=>'MR-007','Nama_Pasien'=>'Eko Prasetyo',  'tgl_regist'=>Carbon::now(),            'No_Identitas'=>'3201010007','KartuBPJS'=>'0001111007','NameUser'=>'admin_daftar'],
        ];
        foreach ($pasiens as $p) {
            RegistrasiPasien::create($p);
        }

        // ── 5. PENDAFTARAN ────────────────────────────────────────────────
        $pendaftarans = [
            ['No_Reg'=>'REG-2026-001','No_MR'=>'MR-001','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas1'],
            ['No_Reg'=>'REG-2026-002','No_MR'=>'MR-002','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Budi Sp.JP', 'Kode_Dokter'=>'DR-002','NameUser'=>'petugas1'],
            ['No_Reg'=>'REG-2026-003','No_MR'=>'MR-003','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Citra Sp.PD','Kode_Dokter'=>'DR-003','NameUser'=>'petugas2'],
            ['No_Reg'=>'REG-2026-004','No_MR'=>'MR-004','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas2'],
            ['No_Reg'=>'REG-2026-005','No_MR'=>'MR-005','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Budi Sp.JP', 'Kode_Dokter'=>'DR-002','NameUser'=>'petugas3'],
            ['No_Reg'=>'REG-2026-006','No_MR'=>'MR-006','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Citra Sp.PD','Kode_Dokter'=>'DR-003','NameUser'=>'petugas3'],
            ['No_Reg'=>'REG-2026-007','No_MR'=>'MR-007','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas1'],
        ];
        foreach ($pendaftarans as $reg) {
            Pendaftaran::create($reg);
        }

        // ── 6. SPRI ───────────────────────────────────────────────────────
        $spris = [
            // REG-001 → spri_dibuat, draft (belum diapprove admisi)
            ['No_Reg'=>'REG-2026-001','Diagnosis'=>'Gagal Napas Akut',         'IndikasiRI'=>'Butuh ventilasi mekanik segera','spesialis'=>'Anestesiologi','Dokter'=>'dr. Andi Sp.An', 'NameUser'=>'dr_igd_1','Perawatan'=>'ICU','Keterangan'=>'Prioritas tinggi','Status'=>'draft'],
            // REG-002 → waiting_icu, spri approved, butuh ICUNV (HC302/HC303 kosong)
            ['No_Reg'=>'REG-2026-002','Diagnosis'=>'Gagal Napas + Infeksi',     'IndikasiRI'=>'Perlu ICU Non Ventilator',       'spesialis'=>'Pulmologi',    'Dokter'=>'dr. Budi Sp.JP', 'NameUser'=>'dr_igd_1','Perawatan'=>'ICU','Keterangan'=>'Monitor ketat',  'Status'=>'approved'],
            // REG-003 → waiting_icu, butuh HCU (HC304/HC305 kosong)
            ['No_Reg'=>'REG-2026-003','Diagnosis'=>'Syok Sepsis',               'IndikasiRI'=>'Monitoring intensif HCU',        'spesialis'=>'Penyakit Dalam','Dokter'=>'dr. Citra Sp.PD','NameUser'=>'dr_igd_2','Perawatan'=>'ICU','Keterangan'=>'-',              'Status'=>'approved'],
            // REG-004 → booking_icu, dapat ICUA2 (sudah BOOKING di status_kamar)
            ['No_Reg'=>'REG-2026-004','Diagnosis'=>'Gagal Jantung Kongestif',   'IndikasiRI'=>'ICU standar, monitoring EKG',    'spesialis'=>'Kardiologi',   'Dokter'=>'dr. Andi Sp.An', 'NameUser'=>'dr_igd_2','Perawatan'=>'ICU','Keterangan'=>'EKG 24 jam',     'Status'=>'approved'],
            // REG-005 → di_icu, di HC301 (sudah ISI di status_kamar, ICUNV)
            ['No_Reg'=>'REG-2026-005','Diagnosis'=>'Pneumonia Berat',           'IndikasiRI'=>'ICU Non Ventilator, isolasi',    'spesialis'=>'Pulmologi',    'Dokter'=>'dr. Budi Sp.JP', 'NameUser'=>'dr_igd_3','Perawatan'=>'ICU','Keterangan'=>'-',              'Status'=>'approved'],
        ];
        foreach ($spris as $s) {
            Spri::create($s);
        }

        // ── 7. ICU ADMISION — tabel inti, semua tahap direpresentasikan ──
        $admisions = [
            // Tahap 1 — baru daftar
            ['No_Reg'=>'REG-2026-006','No_MR'=>'MR-006','status'=>'daftar',      'required_bed_type'=>null,   'allocated_bed_id'=>null,   'match_status'=>null],
            // Tahap 2 — di IGD, sedang diperiksa
            ['No_Reg'=>'REG-2026-007','No_MR'=>'MR-007','status'=>'igd_periksa', 'required_bed_type'=>null,   'allocated_bed_id'=>null,   'match_status'=>null],
            // Tahap 3 — SPRI draft, menunggu approval admisi
            // required_bed_type = Kode_Kelas dari M_KELAS
            ['No_Reg'=>'REG-2026-001','No_MR'=>'MR-001','status'=>'spri_dibuat', 'required_bed_type'=>'ICUNV','allocated_bed_id'=>null,   'match_status'=>'waiting'],
            // Tahap 4 — SPRI approved, menunggu kamar ICUNV (HC302 & HC303 kosong → bisa dialokasi)
            ['No_Reg'=>'REG-2026-002','No_MR'=>'MR-002','status'=>'waiting_icu', 'required_bed_type'=>'ICUNV','allocated_bed_id'=>null,   'match_status'=>'waiting'],
            // Tahap 4b — menunggu kamar HCU (HC304/HC305 kosong → bisa dialokasi)
            ['No_Reg'=>'REG-2026-003','No_MR'=>'MR-003','status'=>'waiting_icu', 'required_bed_type'=>'HCU',  'allocated_bed_id'=>null,   'match_status'=>'waiting'],
            // Tahap 5 — booking, ICUA2 sudah BOOKING di status_kamar
            ['No_Reg'=>'REG-2026-004','No_MR'=>'MR-004','status'=>'booking_icu', 'required_bed_type'=>'ICU',  'allocated_bed_id'=>'ICUA2','match_status'=>'matched'],
            // Tahap 6 — di ICU, HC301 sudah ISI di status_kamar (ICUNV)
            ['No_Reg'=>'REG-2026-005','No_MR'=>'MR-005','status'=>'di_icu',      'required_bed_type'=>'ICUNV','allocated_bed_id'=>'HC301', 'match_status'=>'matched'],
        ];
        foreach ($admisions as $a) {
            IcuAdmision::create($a);
        }
    }
}
