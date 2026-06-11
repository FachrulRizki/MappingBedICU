<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_kelas', function (Blueprint $table) {
            $table->string('Kode_Kelas', 20)->primary();
            $table->string('Nama_Kelas', 100);
            $table->string('Kelas', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('m_ruang_master', function (Blueprint $table) {
            $table->string('Kode_RuangM', 20)->primary();
            $table->string('Kode_Bangsal', 20);
            $table->string('Kode_Kelas', 20);
            $table->foreign('Kode_Kelas')->references('Kode_Kelas')->on('m_kelas')->onDelete('restrict');
            $table->string('Nama_RuangM', 100);
            $table->tinyInteger('Status')->default(1);
            $table->string('KelasBPJS', 20)->nullable();
            $table->string('KetBed', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('status_kamar', function (Blueprint $table) {
            $table->string('Kode_Ruang', 20)->primary(); 
            $table->foreign('Kode_Ruang')->references('Kode_RuangM')->on('m_ruang_master')->onDelete('cascade');
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

    public function down(): void
    {
        Schema::dropIfExists('status_kamar');
        Schema::dropIfExists('m_ruang_master');
        Schema::dropIfExists('m_kelas');
    }
};
