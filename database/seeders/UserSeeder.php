<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::truncate();

        $users = [
            // ── Admin — full akses ────────────────────────────────
            [
                'name'       => 'Administrator',
                'email'      => 'admin@icu.rs',
                'password'   => Hash::make('admin123'),
                'role'       => 'admin',
                'unit_kerja' => 'Sistem',
                'is_active'  => true,
            ],

            // ── Admisi — kelola booking, validasi, verifikasi ─────
            [
                'name'       => 'Petugas Admisi 1',
                'email'      => 'admisi1@icu.rs',
                'password'   => Hash::make('admisi123'),
                'role'       => 'admisi',
                'unit_kerja' => 'Admisi',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas Admisi 2',
                'email'      => 'admisi2@icu.rs',
                'password'   => Hash::make('admisi123'),
                'role'       => 'admisi',
                'unit_kerja' => 'Admisi',
                'is_active'  => true,
            ],

            // ── Petugas ICU — konfirmasi bed, verifikasi masuk ────
            [
                'name'       => 'Petugas ICU 1',
                'email'      => 'icu1@icu.rs',
                'password'   => Hash::make('icu123'),
                'role'       => 'petugas_icu',
                'unit_kerja' => 'ICU',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas ICU 2',
                'email'      => 'icu2@icu.rs',
                'password'   => Hash::make('icu123'),
                'role'       => 'petugas_icu',
                'unit_kerja' => 'ICU',
                'is_active'  => true,
            ],

            // ── Petugas Ruang — buat surat permintaan ICU ─────────
            [
                'name'       => 'Petugas Poli Dalam',
                'email'      => 'poli.dalam@icu.rs',
                'password'   => Hash::make('ruang123'),
                'role'       => 'petugas_ruang',
                'unit_kerja' => 'Poli Penyakit Dalam',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas Poli Jantung',
                'email'      => 'poli.jantung@icu.rs',
                'password'   => Hash::make('ruang123'),
                'role'       => 'petugas_ruang',
                'unit_kerja' => 'Poli Jantung',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas Poli Paru',
                'email'      => 'poli.paru@icu.rs',
                'password'   => Hash::make('ruang123'),
                'role'       => 'petugas_ruang',
                'unit_kerja' => 'Poli Paru',
                'is_active'  => true,
            ],
        ];

        foreach ($users as $u) {
            User::create($u);
        }

        $this->command->info('✓ Users berhasil di-seed.');
        $this->command->table(
            ['Email', 'Role', 'Password'],
            array_map(fn($u) => [
                $u['email'],
                $u['role'],
                str_replace('123', '***', basename($u['email'], '@icu.rs')) . '123',
            ], $users)
        );
    }
}
