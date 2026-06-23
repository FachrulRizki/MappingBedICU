<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel lokal mirror dari SQL Server RS.
 *
 * Hanya dibuat di environment NON-production (dev lokal, staging).
 * Di prod (DB_RSUS_ENABLED=true), migration ini dilewati otomatis.
 *
 * Kompatibel: MySQL dan SQL Server.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip di production — data RS diambil live dari sqlsrv_rsus
        if (app()->environment('production')) {
            return;
        }
        // M_KELAS
        if (! Schema::hasTable('m_kelas')) {
            Schema::create('m_kelas', function (Blueprint $table) {
                $table->string('Kode_Kelas', 20)->primary();
                $table->string('Nama_Kelas', 100);
                $table->string('Kelas', 50)->nullable();
                $table->timestamps();
            });
        }

        // M_RUANG_MASTER (tanpa FK ke m_kelas agar mudah di-seed bebas)
        if (! Schema::hasTable('m_ruang_master')) {
            Schema::create('m_ruang_master', function (Blueprint $table) {
                $table->string('Kode_RuangM', 20)->primary();
                $table->string('Kode_Bangsal', 20);
                $table->string('Kode_Kelas', 20);
                $table->string('Nama_RuangM', 100);
                $table->tinyInteger('Status')->default(1);
                $table->string('KelasBPJS', 20)->nullable();
                $table->string('KetBed', 50)->nullable();
                $table->timestamps();
            });
        }

        // STATUS_KAMAR (tanpa FK ke m_ruang_master)
        if (! Schema::hasTable('status_kamar')) {
            Schema::create('status_kamar', function (Blueprint $table) {
                $table->string('Kode_Ruang', 20)->primary();
                $table->string('Kode_Bangsal', 20);
                $table->string('Status', 20)->default('KOSONG');
                $table->string('Keterangan', 200)->nullable();
                $table->string('NamaUser', 100)->nullable();
                $table->string('KelasBPJS', 20)->nullable();
                $table->string('No_MR', 20)->nullable();
                $table->string('Oksigen', 10)->nullable();
                $table->timestamps();
            });
        }

        // REGISTRASI_PASIEN
        if (! Schema::hasTable('registrasi_pasien')) {
            Schema::create('registrasi_pasien', function (Blueprint $table) {
                $table->string('No_MR', 20)->primary();
                $table->string('Nama_Pasien', 100);
                $table->char('jenis_kelamin', 1)->nullable();
                $table->dateTime('tgl_regist');
                $table->string('No_Identitas', 30)->nullable();
                $table->string('KartuBPJS', 20)->nullable();
                $table->string('NameUser', 50)->nullable();
                $table->timestamps();
            });
        }

        // PENDAFTARAN (tanpa FK ke registrasi_pasien)
        if (! Schema::hasTable('pendaftaran')) {
            Schema::create('pendaftaran', function (Blueprint $table) {
                $table->string('No_Reg', 20)->primary();
                $table->string('No_MR', 20);
                $table->char('Kode_Masuk', 5)->nullable();
                $table->char('Kode_Asal', 5)->nullable();
                $table->string('Kode_Bayar', 10)->nullable();
                $table->string('referensi', 100)->nullable();
                $table->string('medis', 100)->nullable();
                $table->string('PermintaanDPJP', 100)->nullable();
                $table->string('Kode_Dokter', 20)->nullable();
                $table->string('NameUser', 50)->nullable();
                $table->timestamps();
            });
        }

        // M_CARABAYAR
        if (! Schema::hasTable('m_carabayar')) {
            Schema::create('m_carabayar', function (Blueprint $table) {
                $table->string('KODE_BAYAR', 10)->primary();
                $table->string('KET_BAYAR', 100);
            });
        }
    }

    public function down(): void
    {
        if (app()->environment('production')) {
            return;
        }

        Schema::dropIfExists('pendaftaran');
        Schema::dropIfExists('registrasi_pasien');
        Schema::dropIfExists('m_carabayar');
        Schema::dropIfExists('status_kamar');
        Schema::dropIfExists('m_ruang_master');
        Schema::dropIfExists('m_kelas');
    }
};
