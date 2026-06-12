<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_spri_internal', function (Blueprint $table) {
            $table->id();

            // Referensi pasien (no FK — tabel ada di SQL Server RS)
            $table->string('No_MR', 20);
            $table->string('No_Reg', 20);

            // Data klinis dari ruang asal
            $table->string('Diagnosis', 200);
            $table->string('IndikasiRI', 200);
            $table->string('kebutuhan_bed', 100)->nullable();
            $table->string('asal_ruang', 100)->nullable();
            $table->string('Dokter', 100)->nullable();
            $table->string('spesialis', 100)->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('NameUser', 50)->nullable(); 

            // Catatan Admisi (diisi saat approve)
            $table->text('catatan_admisi')->nullable();   

            // Bed referensi (diisi ICU saat verifikasi, no FK─
            $table->string('allocated_bed_id', 20)->nullable();
            $table->string('nama_bed', 100)->nullable();

            // Status alu─
            $table->enum('status', [
                'pending_admisi', 
                'pending_icu',    
                'bed_verified',   
                'ditolak',
            ])->default('pending_admisi');

            $table->string('alasan_tolak', 200)->nullable();

            // Tracking siapa yang aks─
            $table->string('approved_by', 50)->nullable();
            $table->string('verified_by', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_spri_internal');
    }
};
