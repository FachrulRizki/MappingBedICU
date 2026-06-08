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
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        IcuSpriInternal::truncate();
        IcuBookingExternal::truncate();
        IcuAdmision::truncate();
        Spri::truncate();
        Pendaftaran::truncate();
        RegistrasiPasien::truncate();
        StatusKamar::truncate();
        MRuangMaster::truncate();
        MKelas::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── 1. M_KELAS ────────────────────────────────────────────────────
        $kelasData = [
            ['Kode_Kelas' => 'ICU',   'Nama_Kelas' => 'ICU',               'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'ICUNV', 'Nama_Kelas' => 'ICU Non Ventilator', 'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'HCU',   'Nama_Kelas' => 'HCU',               'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'CVCU',  'Nama_Kelas' => 'CVCU',              'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'BPICU', 'Nama_Kelas' => 'ICU BPJS',          'Kelas' => 'ICU'],
        ];
        foreach ($kelasData as $k) MKelas::create($k);

        // ── 2. M_RUANG_MASTER ─────────────────────────────────────────────
        $ruangs = [
            ['Kode_RuangM'=>'HC301','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 301','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC302','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 302','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC303','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 303','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC304','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU', 'Nama_RuangM'=>'HIGH CARE UNIT 304','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC305','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU', 'Nama_RuangM'=>'HIGH CARE UNIT 305','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC306','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU', 'Nama_RuangM'=>'HIGH CARE UNIT 306','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC307','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU', 'Nama_RuangM'=>'HIGH CARE UNIT 307','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC308','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU', 'Nama_RuangM'=>'HIGH CARE UNIT 308','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC313','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'HIGH CARE UNIT 313','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC314','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'HIGH CARE UNIT 314','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC315','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'HIGH CARE UNIT 315','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'ICU A1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA2','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'ICU A2','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA3','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'ICU A3','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA4','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'ICU A4','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUB1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU', 'Nama_RuangM'=>'ICU B1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD2','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D2','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD3','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D3','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
        ];
        foreach ($ruangs as $r) MRuangMaster::create($r);

        // ── 3. STATUS_KAMAR ───────────────────────────────────────────────
        $statusKamars = [
            ['Kode_Ruang'=>'HC301','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-005','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC302','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC303','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC304','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC305','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC306','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-099','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC307','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC308','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC313','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC314','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-088','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC315','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA2','Kode_Bangsal'=>'ICU','Status'=>'BOOKING','KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA3','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA4','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUB1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD2','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,   'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD3','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-077','Oksigen'=>'ADA'],
        ];
        foreach ($statusKamars as $sk) StatusKamar::create($sk);

        // ── 4. REGISTRASI PASIEN ──────────────────────────────────────────
        $pasiens = [
            ['No_MR'=>'MR-001','Nama_Pasien'=>'Budi Santoso',   'jenis_kelamin'=>'L','tgl_regist'=>Carbon::now()->subDays(5),'No_Identitas'=>'3201010001','KartuBPJS'=>'0001111001','NameUser'=>'admin'],
            ['No_MR'=>'MR-002','Nama_Pasien'=>'Siti Rahayu',    'jenis_kelamin'=>'P','tgl_regist'=>Carbon::now()->subDays(4),'No_Identitas'=>'3201010002','KartuBPJS'=>'0001111002','NameUser'=>'admin'],
            ['No_MR'=>'MR-003','Nama_Pasien'=>'Ahmad Fauzi',    'jenis_kelamin'=>'L','tgl_regist'=>Carbon::now()->subDays(3),'No_Identitas'=>'3201010003','KartuBPJS'=>null,        'NameUser'=>'admin'],
            ['No_MR'=>'MR-004','Nama_Pasien'=>'Dewi Lestari',   'jenis_kelamin'=>'P','tgl_regist'=>Carbon::now()->subDays(2),'No_Identitas'=>'3201010004','KartuBPJS'=>'0001111004','NameUser'=>'admin'],
            ['No_MR'=>'MR-005','Nama_Pasien'=>'Hendra Wijaya',  'jenis_kelamin'=>'L','tgl_regist'=>Carbon::now()->subDays(1),'No_Identitas'=>'3201010005','KartuBPJS'=>'0001111005','NameUser'=>'admin'],
            ['No_MR'=>'MR-006','Nama_Pasien'=>'Rina Marlina',   'jenis_kelamin'=>'P','tgl_regist'=>Carbon::now()->subDays(1),'No_Identitas'=>'3201010006','KartuBPJS'=>null,        'NameUser'=>'admin'],
            ['No_MR'=>'MR-007','Nama_Pasien'=>'Eko Prasetyo',   'jenis_kelamin'=>'L','tgl_regist'=>Carbon::now(),            'No_Identitas'=>'3201010007','KartuBPJS'=>'0001111007','NameUser'=>'admin'],
            ['No_MR'=>'MR-099','Nama_Pasien'=>'Yuli Ardiansyah','jenis_kelamin'=>'L','tgl_regist'=>Carbon::now()->subDays(3),'No_Identitas'=>'3201010099','KartuBPJS'=>'0001111099','NameUser'=>'admin'],
            ['No_MR'=>'MR-088','Nama_Pasien'=>'Kartini Susanti','jenis_kelamin'=>'P','tgl_regist'=>Carbon::now()->subDays(4),'No_Identitas'=>'3201010088','KartuBPJS'=>null,        'NameUser'=>'admin'],
            ['No_MR'=>'MR-077','Nama_Pasien'=>'Rudi Hermawan',  'jenis_kelamin'=>'L','tgl_regist'=>Carbon::now()->subDays(2),'No_Identitas'=>'3201010077','KartuBPJS'=>'0001111077','NameUser'=>'admin'],
        ];
        foreach ($pasiens as $p) RegistrasiPasien::create($p);

        // ── 5. PENDAFTARAN ────────────────────────────────────────────────
        $pendaftarans = [
            ['No_Reg'=>'REG-2026-001','No_MR'=>'MR-001','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas1'],
            ['No_Reg'=>'REG-2026-002','No_MR'=>'MR-002','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Budi Sp.JP', 'Kode_Dokter'=>'DR-002','NameUser'=>'petugas1'],
            ['No_Reg'=>'REG-2026-003','No_MR'=>'MR-003','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Citra Sp.PD','Kode_Dokter'=>'DR-003','NameUser'=>'petugas2'],
            ['No_Reg'=>'REG-2026-004','No_MR'=>'MR-004','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas2'],
            ['No_Reg'=>'REG-2026-005','No_MR'=>'MR-005','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Budi Sp.JP', 'Kode_Dokter'=>'DR-002','NameUser'=>'petugas3'],
            ['No_Reg'=>'REG-2026-006','No_MR'=>'MR-006','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Citra Sp.PD','Kode_Dokter'=>'DR-003','NameUser'=>'petugas3'],
            ['No_Reg'=>'REG-2026-007','No_MR'=>'MR-007','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas1'],
            ['No_Reg'=>'REG-2026-099','No_MR'=>'MR-099','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Budi Sp.JP', 'Kode_Dokter'=>'DR-002','NameUser'=>'petugas1'],
            ['No_Reg'=>'REG-2026-088','No_MR'=>'MR-088','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Citra Sp.PD','Kode_Dokter'=>'DR-003','NameUser'=>'petugas2'],
            ['No_Reg'=>'REG-2026-077','No_MR'=>'MR-077','Kode_Masuk'=>'IGD','Kode_Asal'=>'UGD','PermintaanDPJP'=>'dr. Andi Sp.An', 'Kode_Dokter'=>'DR-001','NameUser'=>'petugas3'],
        ];
        foreach ($pendaftarans as $reg) Pendaftaran::create($reg);

        // ── 6. SPRI (jalur lama, masih dipakai untuk IcuAdmision) ────────
        $spris = [
            ['No_Reg'=>'REG-2026-001','Diagnosis'=>'Gagal Napas Akut',      'IndikasiRI'=>'Butuh ventilasi mekanik',  'spesialis'=>'Anestesiologi', 'Dokter'=>'dr. Andi Sp.An', 'NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'Prioritas tinggi','Status'=>'draft'],
            ['No_Reg'=>'REG-2026-002','Diagnosis'=>'Gagal Napas + Infeksi', 'IndikasiRI'=>'Perlu ICU Non Ventilator', 'spesialis'=>'Pulmologi',    'Dokter'=>'dr. Budi Sp.JP', 'NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'Monitor ketat',  'Status'=>'approved'],
            ['No_Reg'=>'REG-2026-003','Diagnosis'=>'Syok Sepsis',           'IndikasiRI'=>'Monitoring intensif HCU', 'spesialis'=>'Penyakit Dalam','Dokter'=>'dr. Citra Sp.PD','NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'-',              'Status'=>'approved'],
            ['No_Reg'=>'REG-2026-004','Diagnosis'=>'Gagal Jantung',         'IndikasiRI'=>'ICU standar, monitoring', 'spesialis'=>'Kardiologi',   'Dokter'=>'dr. Andi Sp.An', 'NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'EKG 24 jam',     'Status'=>'approved'],
            ['No_Reg'=>'REG-2026-005','Diagnosis'=>'Pneumonia Berat',       'IndikasiRI'=>'ICU Non Ventilator',      'spesialis'=>'Pulmologi',    'Dokter'=>'dr. Budi Sp.JP', 'NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'-',              'Status'=>'approved'],
            ['No_Reg'=>'REG-2026-099','Diagnosis'=>'Pasca Operasi Jantung', 'IndikasiRI'=>'High Care Unit post op', 'spesialis'=>'Bedah Jantung','Dokter'=>'dr. Budi Sp.JP', 'NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'Post CABG',      'Status'=>'approved'],
            ['No_Reg'=>'REG-2026-088','Diagnosis'=>'Stroke Hemoragik',      'IndikasiRI'=>'ICU monitoring intensif', 'spesialis'=>'Neurologi',   'Dokter'=>'dr. Citra Sp.PD','NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'CT scan ulang',  'Status'=>'approved'],
            ['No_Reg'=>'REG-2026-077','Diagnosis'=>'STEMI Anterior',        'IndikasiRI'=>'CVCU monitoring EKG',    'spesialis'=>'Kardiologi',   'Dokter'=>'dr. Andi Sp.An', 'NameUser'=>'dr_igd','Perawatan'=>'ICU','Keterangan'=>'Primary PCI',    'Status'=>'approved'],
        ];
        foreach ($spris as $s) Spri::create($s);

        // ── 7. ICU ADMISION (jalur lama) ──────────────────────────────────
        // required_bed_type sekarang pakai Nama_Kelas (bukan Kode_Kelas)
        $admisions = [
            ['No_Reg'=>'REG-2026-006','No_MR'=>'MR-006','status'=>'daftar',      'required_bed_type'=>null,                'allocated_bed_id'=>null,   'match_status'=>null],
            ['No_Reg'=>'REG-2026-007','No_MR'=>'MR-007','status'=>'igd_periksa', 'required_bed_type'=>null,                'allocated_bed_id'=>null,   'match_status'=>null],
            ['No_Reg'=>'REG-2026-001','No_MR'=>'MR-001','status'=>'spri_dibuat', 'required_bed_type'=>'ICU Non Ventilator','allocated_bed_id'=>null,   'match_status'=>'waiting'],
            ['No_Reg'=>'REG-2026-002','No_MR'=>'MR-002','status'=>'waiting_icu', 'required_bed_type'=>'ICU Non Ventilator','allocated_bed_id'=>null,   'match_status'=>'waiting'],
            ['No_Reg'=>'REG-2026-003','No_MR'=>'MR-003','status'=>'waiting_icu', 'required_bed_type'=>'HCU',               'allocated_bed_id'=>null,   'match_status'=>'waiting'],
            ['No_Reg'=>'REG-2026-004','No_MR'=>'MR-004','status'=>'booking_icu', 'required_bed_type'=>'ICU',               'allocated_bed_id'=>'ICUA2','match_status'=>'matched'],
            ['No_Reg'=>'REG-2026-005','No_MR'=>'MR-005','status'=>'di_icu',      'required_bed_type'=>'ICU Non Ventilator','allocated_bed_id'=>'HC301','match_status'=>'matched'],
            ['No_Reg'=>'REG-2026-099','No_MR'=>'MR-099','status'=>'di_icu',      'required_bed_type'=>'HCU',               'allocated_bed_id'=>'HC306','match_status'=>'matched'],
            ['No_Reg'=>'REG-2026-088','No_MR'=>'MR-088','status'=>'di_icu',      'required_bed_type'=>'ICU',               'allocated_bed_id'=>'HC314','match_status'=>'matched'],
            ['No_Reg'=>'REG-2026-077','No_MR'=>'MR-077','status'=>'di_icu',      'required_bed_type'=>'CVCU',              'allocated_bed_id'=>'CVCD3','match_status'=>'matched'],
        ];
        foreach ($admisions as $a) IcuAdmision::create($a);

        // ── 8. ICU BOOKING EXTERNAL — data demo berbagai status ──────────
        $bookingExternals = [
            // Booking baru dikirim ke ICU untuk dicek
            [
                'nama_pasien'      => 'Agus Purnomo',
                'jenis_kelamin'    => 'L',
                'no_identitas'     => '3271010001234',
                'asal_rujukan'     => 'RS Sejahtera Bogor',
                'no_telp_keluarga' => '081234567890',
                'diagnosa'         => 'Gagal Napas Akut',
                'rencana_tindakan' => 'Pemasangan Ventilator Mekanik',
                'kebutuhan_bed'    => 'ICU Non Ventilator',
                'keterangan'       => 'Pasien dirujuk emergency',
                'status'           => 'pending_icu',
                'created_by'       => 'admisi_01',
            ],
            // ICU sudah konfirmasi ada bed
            [
                'nama_pasien'      => 'Sri Wahyuni',
                'jenis_kelamin'    => 'P',
                'no_identitas'     => '3271020002345',
                'asal_rujukan'     => 'Klinik Medika Depok',
                'no_telp_keluarga' => '082345678901',
                'diagnosa'         => 'Syok Kardiogenik',
                'rencana_tindakan' => 'Monitoring CVCU intensif',
                'kebutuhan_bed'    => 'CVCU',
                'keterangan'       => 'Riwayat PCI 2 tahun lalu',
                'allocated_bed_id' => 'CVCD1',
                'status'           => 'bed_confirmed',
                'created_by'       => 'admisi_01',
                'confirmed_by'     => 'icu_nurse_01',
            ],
            // Admisi sudah validasi, pasien dalam perjalanan
            [
                'nama_pasien'      => 'Bambang Susilo',
                'jenis_kelamin'    => 'L',
                'no_identitas'     => '3271030003456',
                'asal_rujukan'     => 'RS Harapan Bersama',
                'no_telp_keluarga' => '083456789012',
                'diagnosa'         => 'Meningitis Bakterial',
                'rencana_tindakan' => 'ICU monitoring ketat',
                'kebutuhan_bed'    => 'ICU',
                'keterangan'       => 'Suhu tinggi, kejang',
                'allocated_bed_id' => 'HC315',
                'status'           => 'admisi_validated',
                'created_by'       => 'admisi_02',
                'confirmed_by'     => 'icu_nurse_01',
                'validated_by'     => 'admisi_01',
            ],
        ];
        // Update bed yang dipakai menjadi BOOKING
        StatusKamar::where('Kode_Ruang', 'CVCD1')->update(['Status' => 'BOOKING']);
        StatusKamar::where('Kode_Ruang', 'HC315')->update(['Status' => 'BOOKING']);
        foreach ($bookingExternals as $be) IcuBookingExternal::create($be);

        // ── 9. ICU SPRI INTERNAL — data demo berbagai status ─────────────
        $spriInternals = [
            // Surat baru masuk, menunggu approval admisi
            [
                'No_MR'         => 'MR-003',
                'No_Reg'        => 'REG-2026-003',
                'Diagnosis'     => 'Sepsis Berat + Disfungsi Multi Organ',
                'IndikasiRI'    => 'Kondisi memburuk, perlu ICU segera',
                'kebutuhan_bed' => 'ICU',
                'asal_ruang'    => 'Poli Penyakit Dalam - Lantai 3',
                'Dokter'        => 'dr. Citra Sp.PD',
                'spesialis'     => 'Penyakit Dalam',
                'Keterangan'    => 'Lab kultur darah positif, MAP < 65',
                'NameUser'      => 'perawat_pd',
                'status'        => 'pending_admisi',
            ],
            // Admisi sudah approve, menunggu ICU booking bed
            [
                'No_MR'         => 'MR-004',
                'No_Reg'        => 'REG-2026-004',
                'Diagnosis'     => 'Gagal Jantung Dekompensasi Akut',
                'IndikasiRI'    => 'EF 20%, edema paru akut',
                'kebutuhan_bed' => 'CVCU',
                'asal_ruang'    => 'Poli Jantung - Gedung B',
                'Dokter'        => 'dr. Andi Sp.An',
                'spesialis'     => 'Kardiologi',
                'Keterangan'    => 'BNP sangat tinggi, perlu diuretik IV',
                'NameUser'      => 'perawat_jantung',
                'approved_by'   => 'admisi_01',
                'status'        => 'pending_icu',
            ],
            // ICU sudah booking bed, menunggu verifikasi admisi
            [
                'No_MR'            => 'MR-001',
                'No_Reg'           => 'REG-2026-001',
                'Diagnosis'        => 'PPOK Eksaserbasi Akut',
                'IndikasiRI'       => 'Saturasi O2 < 80%, perlu HCU',
                'kebutuhan_bed'    => 'HCU',
                'asal_ruang'       => 'Poli Paru - Lantai 2',
                'Dokter'           => 'dr. Budi Sp.JP',
                'spesialis'        => 'Pulmologi',
                'Keterangan'       => 'PaO2/FiO2 < 200',
                'NameUser'         => 'perawat_paru',
                'allocated_bed_id' => 'HC307',
                'approved_by'      => 'admisi_02',
                'booked_by'        => 'icu_nurse_01',
                'status'           => 'bed_booked',
            ],
        ];
        // Update bed yang dipakai menjadi BOOKING
        StatusKamar::where('Kode_Ruang', 'HC307')->update(['Status' => 'BOOKING']);
        foreach ($spriInternals as $si) IcuSpriInternal::create($si);

        $this->command->info('✓ Seeder selesai. Data berhasil di-seed termasuk booking external dan surat permintaan internal.');
    }
}
