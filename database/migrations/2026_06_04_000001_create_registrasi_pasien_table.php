<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrasi_pasien', function (Blueprint $table) {
            $table->string('No_MR')->primary();
            $table->string('Nama_Pasien');
            $table->dateTime('tgl_regist');
            $table->string('No_Identitas')->nullable();
            $table->string('KartuBPJS')->nullable();
            $table->string('NameUser')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrasi_pasien');
    }
};
