<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\IcuBookingExternal;
use App\Models\IcuSpriInternal;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        // Seed data transaksional dummy untuk testing
        $this->seedTransactionalData();

        $this->command->info('✓ DatabaseSeeder selesai.');
    }

    private function truncateSafe(array $models): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach ($models as $model) {
                $model::truncate();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            // SQL Server: pakai DELETE
            foreach ($models as $model) {
                try {
                    DB::connection()->statement('DELETE FROM ' . (new $model())->getTable());
                } catch (\Exception $e) {
                    $this->command->warn("Gagal hapus {$model}: " . $e->getMessage());
                }
            }
        }
    }

    private function seedTransactionalData(): void
    {
        $this->truncateSafe([IcuSpriInternal::class, IcuBookingExternal::class]);

        // BOOKING EXTERNAL
        IcuBookingExternal::create([
            'nama_pasien'      => 'Agus Purnomo',
            'jenis_kelamin'    => 'L',
            'no_identitas'     => '3271010001234',
            'asal_rujukan'     => 'RS Sejahtera Bogor',
            'no_telp_keluarga' => '081234567890',
            'diagnosa'         => 'Gagal Napas Akut',
            'rencana_tindakan' => 'Pemasangan Ventilator',
            'jaminan'          => 'BPJS',
            'catatan_jaminan'  => 'No. BPJS: 0001234567001',
            'keterangan'       => 'Saturasi 78%',
            'status'           => 'pending_icu',
            'created_by'       => 'admisi1',
        ]);

        IcuBookingExternal::create([
            'nama_pasien'      => 'Sri Wahyuni',
            'jenis_kelamin'    => 'P',
            'no_identitas'     => '3271020002345',
            'asal_rujukan'     => 'Klinik Medika Depok',
            'no_telp_keluarga' => '082345678901',
            'diagnosa'         => 'Syok Kardiogenik',
            'rencana_tindakan' => 'Monitoring CVCU',
            'kebutuhan_bed'    => 'CVCU',
            'jaminan'          => 'Asuransi',
            'catatan_jaminan'  => 'Prudential PRU-2024-001',
            'keterangan'       => 'Riwayat PCI 2 tahun lalu',
            'allocated_bed_id' => 'CVCD1',
            'nama_bed'         => 'CVCU/ICCU D1',
            'status'           => 'bed_confirmed',
            'created_by'       => 'admisi1',
            'confirmed_by'     => 'icu1',
        ]);

        // SPRI INTERNAL — No_MR harus ada di REGISTER_PASIEN di DB_RSUS
        // Gunakan No_MR yang memang ada di DB RS
        IcuSpriInternal::create([
            'No_MR'      => '000001',
            'No_Reg'     => 'REG-2026-001',
            'Diagnosis'  => 'Sepsis Berat',
            'IndikasiRI' => 'Kondisi memburuk, perlu ICU segera',
            'asal_ruang' => 'Poli Penyakit Dalam',
            'Dokter'     => 'dr. Contoh Sp.PD',
            'Keterangan' => 'MAP < 65',
            'NameUser'   => 'Petugas Poli Dalam',
            'status'     => 'pending_admisi',
        ]);

        $this->command->info('→ Data dummy booking ICU berhasil di-seed.');
    }
}
