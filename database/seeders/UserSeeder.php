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
            // ── Admin ─────────────────────────────────────────────
            [
                'name'       => 'Administrator',
                'username'   => 'admin',
                'email'      => 'admin@icu.rs',
                'password'   => Hash::make('admin123'),
                'role'       => 'admin',
                'unit_kerja' => 'Sistem',
                'is_active'  => true,
            ],

            // ── Admisi ────────────────────────────────────────────
            [
                'name'       => 'Petugas Admisi 1',
                'username'   => 'admisi1',
                'email'      => 'admisi1@icu.rs',
                'password'   => Hash::make('admisi123'),
                'role'       => 'admisi',
                'unit_kerja' => 'Admisi',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas Admisi 2',
                'username'   => 'admisi2',
                'email'      => 'admisi2@icu.rs',
                'password'   => Hash::make('admisi123'),
                'role'       => 'admisi',
                'unit_kerja' => 'Admisi',
                'is_active'  => true,
            ],

            // ── Petugas ICU ───────────────────────────────────────
            [
                'name'       => 'Petugas ICU 1',
                'username'   => 'icu1',
                'email'      => 'icu1@icu.rs',
                'password'   => Hash::make('icu123'),
                'role'       => 'petugas_icu',
                'unit_kerja' => 'ICU',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas ICU 2',
                'username'   => 'icu2',
                'email'      => 'icu2@icu.rs',
                'password'   => Hash::make('icu123'),
                'role'       => 'petugas_icu',
                'unit_kerja' => 'ICU',
                'is_active'  => true,
            ],

            // ── Petugas Ruang ─────────────────────────────────────
            [
                'name'       => 'Petugas Poli Dalam',
                'username'   => 'poli.dalam',
                'email'      => 'poli.dalam@icu.rs',
                'password'   => Hash::make('ruang123'),
                'role'       => 'petugas_ruang',
                'unit_kerja' => 'Poli Penyakit Dalam',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas Poli Jantung',
                'username'   => 'poli.jantung',
                'email'      => 'poli.jantung@icu.rs',
                'password'   => Hash::make('ruang123'),
                'role'       => 'petugas_ruang',
                'unit_kerja' => 'Poli Jantung',
                'is_active'  => true,
            ],
            [
                'name'       => 'Petugas Poli Paru',
                'username'   => 'poli.paru',
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
            ['Username', 'Role', 'Password'],
            array_map(fn($u) => [
                $u['username'],
                $u['role'],
                // tampilkan password hint
                match($u['role']) {
                    'admin'         => 'admin123',
                    'admisi'        => 'admisi123',
                    'petugas_icu'   => 'icu123',
                    'petugas_ruang' => 'ruang123',
                    default         => '???',
                },
            ], $users)
        );
    }
}
