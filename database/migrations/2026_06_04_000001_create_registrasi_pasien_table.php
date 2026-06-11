<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel lokal untuk data pasien.
 * Pada produksi, data ini dibaca dari SQL Server: DB_RSUS.dbo.REGISTER_PASIEN
 * Kolom disesuaikan agar query lokal dan RS compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrasi_pasien', function (Blueprint $table) {
            $table->string('No_MR', 20)->primary();
            $table->string('Nama_Pasien', 100);
            $table->char('jenis_kelamin', 1)->nullable();   // 'L' | 'P'
            $table->dateTime('tgl_regist');
            $table->string('No_Identitas', 30)->nullable();
            $table->string('KartuBPJS', 20)->nullable();
            $table->string('NameUser', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrasi_pasien');
    }
};
