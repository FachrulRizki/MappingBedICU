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

            // Data pasien external (sebelum punya No_MR di sistem)
            $table->string('nama_pasien_ext', 100)->nullable();
            $table->string('asal_rs', 100)->nullable();
            $table->string('jaminan', 50)->nullable();
            $table->text('catatan_jaminan')->nullable();
            $table->string('jenis_pasien', 20)->default('internal');

            // Status alur 
            $table->enum('status', [
                'daftar',
                'igd_periksa',
                'spri_dibuat',
                'waiting_icu',
                'booking_icu',
                'di_icu',
                'pulang',
            ])->default('daftar');

            // Kebutuhan bed & catatan
            $table->string('required_bed_type', 100)->nullable();
            $table->text('catatan_admisi')->nullable();

            // Catatan bed (referensi saja, no FK constraint)─
            $table->string('allocated_bed_id', 20)->nullable();
            $table->string('match_status', 20)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_admision');
    }
};
