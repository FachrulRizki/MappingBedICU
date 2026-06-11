<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->string('No_Reg', 20)->primary();
            $table->string('No_MR', 20);
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->onDelete('cascade');
            $table->char('Kode_Masuk', 5)->nullable();
            $table->char('Kode_Asal', 5)->nullable();
            $table->string('referensi', 100)->nullable();
            $table->string('medis', 100)->nullable();
            $table->string('PermintaanDPJP', 100)->nullable();
            $table->string('Kode_Dokter', 20)->nullable();
            $table->string('NameUser', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
