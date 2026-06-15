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
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        IcuSpriInternal::truncate();
        IcuBookingExternal::truncate();
        Pendaftaran::truncate();
        RegistrasiPasien::truncate();
        StatusKamar::truncate();
        MRuangMaster::truncate();
        MKelas::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── 1. M_KELAS ────────────────────────────────────────────────────
        $kelasData = [
            ['Kode_Kelas' => 'ICU',   'Nama_Kelas' => 'ICU',                'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'ICUNV', 'Nama_Kelas' => 'ICU Non Ventilator', 'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'HCU',   'Nama_Kelas' => 'HCU',                'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'CVCU',  'Nama_Kelas' => 'CVCU',               'Kelas' => 'ICU'],
            ['Kode_Kelas' => 'BPICU', 'Nama_Kelas' => 'ICU BPJS',           'Kelas' => 'ICU'],
        ];
        foreach ($kelasData as $k) MKelas::create($k);

        // ── 2. M_RUANG_MASTER ─────────────────────────────────────────────
        $ruangs = [
            ['Kode_RuangM'=>'HC301','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 301','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC302','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 302','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC303','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICUNV','Nama_RuangM'=>'HIGH CARE UNIT 303','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC304','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 304','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC305','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 305','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC306','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 306','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC307','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 307','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC308','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'HCU','Nama_RuangM'=>'HIGH CARE UNIT 308','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC313','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'HIGH CARE UNIT 313','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC314','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'HIGH CARE UNIT 314','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'HC315','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'HIGH CARE UNIT 315','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA2','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A2','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA3','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A3','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUA4','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU A4','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'ICUB1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'ICU','Nama_RuangM'=>'ICU B1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD1','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D1','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD2','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D2','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
            ['Kode_RuangM'=>'CVCD3','Kode_Bangsal'=>'ICU','Kode_Kelas'=>'CVCU','Nama_RuangM'=>'CVCU/ICCU D3','Status'=>1,'KelasBPJS'=>'ICU','KetBed'=>'Bed Khusus'],
        ];
        foreach ($ruangs as $r) MRuangMaster::create($r);

        // ── 3. STATUS_KAMAR ───────────────────────────────────────────────
        $statusKamars = [
            ['Kode_Ruang'=>'HC301','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-005','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC302','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC303','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC304','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC305','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC306','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-099','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC307','Kode_Bangsal'=>'ICU','Status'=>'BOOKING','KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC308','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC313','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC314','Kode_Bangsal'=>'ICU','Status'=>'ISI',    'KelasBPJS'=>'ICU','No_MR'=>'MR-088','Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'HC315','Kode_Bangsal'=>'ICU','Status'=>'BOOKING','KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA2','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA3','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUA4','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'ICUB1','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD1','Kode_Bangsal'=>'ICU','Status'=>'BOOKING','KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
            ['Kode_Ruang'=>'CVCD2','Kode_Bangsal'=>'ICU','Status'=>'KOSONG', 'KelasBPJS'=>'ICU','No_MR'=>null,    'Oksigen'=>'ADA'],
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

        // ── 6. BOOKING EXTERNAL ───────────────────────────────────────────
        $bookingExternals = [
            [
                'nama_pasien' => 'Agus Purnomo', 'jenis_kelamin' => 'L',
                'no_identitas' => '3271010001234', 'asal_rujukan' => 'RS Sejahtera Bogor',
                'no_telp_keluarga' => '081234567890',
                'diagnosa' => 'Gagal Napas Akut', 'rencana_tindakan' => 'Pemasangan Ventilator',
                'kebutuhan_bed' => null, 'jaminan' => 'BPJS',
                'catatan_jaminan' => 'No. BPJS: 0001234567001',
                'keterangan' => 'Saturasi 78%', 'status' => 'pending_icu', 'created_by' => 'admisi1',
            ],
            [
                'nama_pasien' => 'Sri Wahyuni', 'jenis_kelamin' => 'P',
                'no_identitas' => '3271020002345', 'asal_rujukan' => 'Klinik Medika Depok',
                'no_telp_keluarga' => '082345678901',
                'diagnosa' => 'Syok Kardiogenik', 'rencana_tindakan' => 'Monitoring CVCU',
                'kebutuhan_bed' => 'CVCU', 'jaminan' => 'Asuransi',
                'catatan_jaminan' => 'Prudential PRU-2024-001',
                'keterangan' => 'Riwayat PCI 2 tahun lalu',
                'allocated_bed_id' => 'CVCD1', 'nama_bed' => 'CVCU/ICCU D1',
                'status' => 'bed_confirmed', 'created_by' => 'admisi1', 'confirmed_by' => 'icu1',
            ],
            [
                'nama_pasien' => 'Bambang Susilo', 'jenis_kelamin' => 'L',
                'no_identitas' => '3271030003456', 'asal_rujukan' => 'RS Harapan Bersama',
                'no_telp_keluarga' => '083456789012',
                'diagnosa' => 'Meningitis Bakterial', 'rencana_tindakan' => 'ICU monitoring, antibiotik IV',
                'kebutuhan_bed' => 'ICU', 'jaminan' => 'Umum',
                'catatan_jaminan' => 'Bayar mandiri', 'keterangan' => 'GCS 11',
                'No_MR' => 'MR-007',
                'allocated_bed_id' => 'HC315', 'nama_bed' => 'HIGH CARE UNIT 315',
                'status' => 'admisi_verified',
                'created_by' => 'admisi2', 'confirmed_by' => 'icu1', 'verified_by' => 'admisi1',
            ],
        ];
        foreach ($bookingExternals as $be) IcuBookingExternal::create($be);

        // ── 7. SPRI INTERNAL ──────────────────────────────────────────────
        $spriInternals = [
            [
                'No_MR' => 'MR-003', 'No_Reg' => 'REG-2026-003',
                'Diagnosis' => 'Sepsis Berat', 'IndikasiRI' => 'Kondisi memburuk, perlu ICU segera',
                'asal_ruang' => 'Poli Penyakit Dalam', 'Dokter' => 'dr. Citra Sp.PD',
                'Keterangan' => 'MAP < 65', 'NameUser' => 'poli.dalam', 'status' => 'pending_admisi',
            ],
            [
                'No_MR' => 'MR-004', 'No_Reg' => 'REG-2026-004',
                'Diagnosis' => 'Gagal Jantung Dekompensasi', 'IndikasiRI' => 'EF 20%, edema paru akut',
                'asal_ruang' => 'Poli Jantung', 'Dokter' => 'dr. Andi Sp.An',
                'Keterangan' => 'BNP tinggi', 'NameUser' => 'poli.jantung',
                'catatan_admisi' => 'BPJS aktif.', 'approved_by' => 'admisi1', 'status' => 'pending_icu',
            ],
            [
                'No_MR' => 'MR-001', 'No_Reg' => 'REG-2026-001',
                'Diagnosis' => 'PPOK Eksaserbasi', 'IndikasiRI' => 'Saturasi O2 < 80%',
                'kebutuhan_bed' => 'HCU', 'asal_ruang' => 'Poli Paru', 'Dokter' => 'dr. Budi Sp.JP',
                'Keterangan' => 'PaO2/FiO2 < 200', 'NameUser' => 'poli.paru',
                'catatan_admisi' => 'BPJS kelas 1.',
                'allocated_bed_id' => 'HC307', 'nama_bed' => 'HIGH CARE UNIT 307',
                'approved_by' => 'admisi2', 'verified_by' => 'icu1', 'status' => 'bed_verified',
            ],
        ];
        foreach ($spriInternals as $si) IcuSpriInternal::create($si);

        $this->command->info('✓ DatabaseSeeder selesai.');
        $this->command->table(
            ['Tabel', 'Jumlah'],
            [
                ['M_Kelas',          MKelas::count()],
                ['M_Ruang_Master',   MRuangMaster::count()],
                ['Status_Kamar',     StatusKamar::count()],
                ['Registrasi_Pasien',RegistrasiPasien::count()],
                ['Pendaftaran',      Pendaftaran::count()],
                ['Booking_External', IcuBookingExternal::count()],
                ['Spri_Internal',    IcuSpriInternal::count()],
            ]
        );
    }
}
