<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel M_KELAS — master jenis/kelas bed (ICUNV, HCU, ICU, CVCU, dst.)
        Schema::create('m_kelas', function (Blueprint $table) {
            $table->string('Kode_Kelas', 20)->primary();
            $table->string('Nama_Kelas', 100);
            $table->string('Kelas', 50)->nullable();   // contoh: 'ICU', 'Kelas I', dst.
            $table->timestamps();
        });

        // Tabel M_RUANG_MASTER — master ruangan/bed individual
        Schema::create('m_ruang_master', function (Blueprint $table) {
            $table->string('Kode_RuangM', 20)->primary();
            $table->string('Kode_Bangsal', 20);        // contoh: 'ICU'
            $table->string('Kode_Kelas', 20);          // FK ke m_kelas
            $table->foreign('Kode_Kelas')->references('Kode_Kelas')->on('m_kelas');
            $table->string('Nama_RuangM', 100);        // nama bed, contoh: 'ICU A1'
            $table->tinyInteger('Status')->default(1); // 1=aktif, 0=nonaktif
            $table->string('KelasBPJS', 20)->nullable();
            $table->string('KetBed', 50)->nullable();  // 'Bed Khusus', dsb.
            $table->timestamps();
        });

        // Tabel STATUS_KAMAR — status occupancy bed real-time
        // Struktur mengikuti DB existing:
        //   Kode_Ruang  → FK ke m_ruang_master.Kode_RuangM
        //   Kode_Bangsal → kode bangsal (ICU, HCU, dst.)
        //   Status      → ISI | KOSONG | BOOKING (mengikuti existing, uppercase)
        Schema::create('status_kamar', function (Blueprint $table) {
            $table->string('Kode_Ruang', 20)->primary(); // PK = Kode_RuangM (HC301, dst.)
            $table->string('Kode_Bangsal', 20);          // ICU, HCU, CVCU
            $table->string('Status', 20)->default('KOSONG'); // ISI | KOSONG | BOOKING
            $table->string('Keterangan')->nullable();
            $table->string('NamaUser', 100)->nullable();
            $table->string('KelasBPJS', 20)->nullable(); // ICU, HCU, CVCU
            $table->string('No_MR', 20)->nullable();     // diisi saat ISI
            $table->string('Oksigen', 10)->nullable();   // ADA | TIDAK
            $table->timestamps();

            // Relasi ke ruang master untuk mendapatkan Kode_Kelas → Nama_Kelas
            $table->foreign('Kode_Ruang')->references('Kode_RuangM')->on('m_ruang_master');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_kamar');
        Schema::dropIfExists('m_ruang_master');
        Schema::dropIfExists('m_kelas');
    }
};
