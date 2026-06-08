<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom role dan unit_kerja ke tabel users.
 *
 * Role:
 *   admin        — full akses semua menu
 *   admisi       — kelola booking external, surat permintaan, verifikasi
 *   petugas_icu  — konfirmasi bed, verifikasi pasien masuk
 *   petugas_ruang — buat surat permintaan internal (dari bangsal lain)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'admisi', 'petugas_icu', 'petugas_ruang'])
                ->default('admisi')
                ->after('email');
            $table->string('unit_kerja')->nullable()->after('role');  // contoh: "ICU", "Poli Dalam", "Admisi"
            $table->boolean('is_active')->default(true)->after('unit_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'unit_kerja', 'is_active']);
        });
    }
};
