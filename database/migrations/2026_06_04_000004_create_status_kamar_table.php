<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel master & status kamar ICU.
 *
 * Pada produksi, ketiga tabel ini ada di SQL Server DB_RSUS:
 *   - M_KELAS        → master jenis/kelas bed
 *   - M_RUANG_MASTER → master ruangan/bed individual
 *   - STATUS_KAMAR   → status occupancy bed real-time
 *
 * Tabel lokal ini hanya dipakai saat koneksi SQL Server tidak tersedia
 * (fallback mode / development lokal).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. M_KELAS — master jenis kelas bed (ICU, HCU, CVCU, dll.) ───────
        Schema::create('m_kelas', function (Blueprint $table) {
            $table->string('Kode_Kelas', 20)->primary();
            $table->string('Nama_Kelas', 100);
            $table->string('Kelas', 50)->nullable();        // kelompok: 'ICU', 'Kelas I', dll.
            $table->timestamps();
        });

        // ── 2. M_RUANG_MASTER — master ruangan/bed individual ─────────────────
        Schema::create('m_ruang_master', function (Blueprint $table) {
            $table->string('Kode_RuangM', 20)->primary();
            $table->string('Kode_Bangsal', 20);             // contoh: 'ICU'
            $table->string('Kode_Kelas', 20);
            $table->foreign('Kode_Kelas')
                  ->references('Kode_Kelas')->on('m_kelas')
                  ->onDelete('restrict');
            $table->string('Nama_RuangM', 100);             // nama bed: 'ICU A1', dll.
            $table->tinyInteger('Status')->default(1);      // 1=aktif, 0=nonaktif
            $table->string('KelasBPJS', 20)->nullable();
            $table->string('KetBed', 50)->nullable();
            $table->timestamps();
        });

        // ── 3. STATUS_KAMAR — status occupancy bed real-time ──────────────────
        Schema::create('status_kamar', function (Blueprint $table) {
            $table->string('Kode_Ruang', 20)->primary();    // = Kode_RuangM
            $table->foreign('Kode_Ruang')
                  ->references('Kode_RuangM')->on('m_ruang_master')
                  ->onDelete('cascade');
            $table->string('Kode_Bangsal', 20);
            $table->string('Status', 20)->default('KOSONG'); // KOSONG | ISI | BOOKING
            $table->string('Keterangan', 200)->nullable();
            $table->string('NamaUser', 100)->nullable();
            $table->string('KelasBPJS', 20)->nullable();
            $table->string('No_MR', 20)->nullable();        // diisi saat ISI
            $table->string('Oksigen', 10)->nullable();      // ADA | TIDAK
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_kamar');
        Schema::dropIfExists('m_ruang_master');
        Schema::dropIfExists('m_kelas');
    }
};
