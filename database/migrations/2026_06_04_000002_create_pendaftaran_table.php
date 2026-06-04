<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->string('No_Reg')->primary();
            $table->string('No_MR');
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->onDelete('cascade');
            $table->char('Kode_Masuk', 5)->nullable();   // contoh: IGD, RJ, dsb
            $table->char('Kode_Asal', 5)->nullable();
            $table->string('referensi')->nullable();
            $table->string('medis')->nullable();
            $table->string('PermintaanDPJP')->nullable();
            $table->string('Kode_Dokter')->nullable();
            $table->string('NameUser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
