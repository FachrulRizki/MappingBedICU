<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_admision', function (Blueprint $table) {
            $table->id();

            $table->string('No_Reg', 20)->nullable();
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->nullOnDelete();
            $table->string('No_MR', 20)->nullable();
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->nullOnDelete();

            $table->string('nama_pasien_ext', 100)->nullable();
            $table->string('asal_rs', 100)->nullable();
            $table->string('jaminan', 50)->nullable(); 
            $table->text('catatan_jaminan')->nullable();
            $table->string('jenis_pasien', 20)->default('internal');

            $table->enum('status', [
                'daftar',
                'igd_periksa',
                'spri_dibuat',
                'waiting_icu',
                'ext_request',
                'ext_waiting',
                'booking_icu',
                'di_icu',
                'pulang',
            ])->default('daftar');

            $table->string('required_bed_type', 100)->nullable();
            $table->text('catatan_admisi')->nullable();
            $table->string('allocated_bed_id', 20)->nullable();
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();
            $table->string('match_status', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_admision');
    }
};
