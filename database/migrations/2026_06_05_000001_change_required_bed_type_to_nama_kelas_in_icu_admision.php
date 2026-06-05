<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ubah semantik kolom required_bed_type di icu_admision:
 *   SEBELUM: menyimpan Kode_Kelas (contoh: "ICUNV", "HCU")
 *   SESUDAH: menyimpan Nama_Kelas  (contoh: "High Care Unit", "ICU BPJS")
 *
 * Kolom tetap bertipe string — hanya isi data yang berubah.
 * Migration ini juga mengkonversi data lama (jika ada) dari Kode → Nama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Konversi data lama: Kode_Kelas → Nama_Kelas ──────────────
        // Ambil semua mapping dari m_kelas
        $kelas = DB::table('m_kelas')
            ->whereNotNull('Kode_Kelas')
            ->whereNotNull('Nama_Kelas')
            ->pluck('Nama_Kelas', 'Kode_Kelas'); // ['ICUNV' => 'High Care Unit', ...]

        foreach ($kelas as $kode => $nama) {
            DB::table('icu_admision')
                ->where('required_bed_type', $kode)
                ->update(['required_bed_type' => $nama]);
        }

        // ── 2. Update komentar kolom (dokumentasi) ───────────────────────
        // Gunakan DB::statement karena Blueprint tidak support column comment change saja
        // di semua driver. Ini opsional — skip jika driver tidak support.
        try {
            DB::statement("ALTER TABLE icu_admision MODIFY COLUMN `required_bed_type` VARCHAR(255) NULL COMMENT 'Nama_Kelas dari m_kelas (bukan Kode_Kelas)'");
        } catch (\Throwable) {
            // SQLite atau driver lain yang tidak support MODIFY — abaikan
        }
    }

    public function down(): void
    {
        // ── Rollback: Nama_Kelas → Kode_Kelas ───────────────────────────
        $kelas = DB::table('m_kelas')
            ->whereNotNull('Kode_Kelas')
            ->whereNotNull('Nama_Kelas')
            ->pluck('Kode_Kelas', 'Nama_Kelas'); // ['High Care Unit' => 'ICUNV', ...]

        foreach ($kelas as $nama => $kode) {
            DB::table('icu_admision')
                ->where('required_bed_type', $nama)
                ->update(['required_bed_type' => $kode]);
        }

        try {
            DB::statement("ALTER TABLE icu_admision MODIFY COLUMN `required_bed_type` VARCHAR(255) NULL COMMENT 'Kode_Kelas dari m_kelas (ICUNV, HCU, ICU, CVCU)'");
        } catch (\Throwable) {}
    }
};
