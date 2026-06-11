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

            $table->string('No_MR', 20);
            $table->string('No_Reg', 20);

            $table->string('Diagnosis', 200);
            $table->string('IndikasiRI', 200);
            $table->string('kebutuhan_bed', 100)->nullable();
            $table->string('asal_ruang', 100)->nullable();
            $table->string('Dokter', 100)->nullable();
            $table->string('spesialis', 100)->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('NameUser', 50)->nullable();  

            $table->text('catatan_admisi')->nullable();

            $table->string('allocated_bed_id', 20)->nullable();
            $table->foreign('allocated_bed_id')
                  ->references('Kode_Ruang')->on('status_kamar')
                  ->nullOnDelete();

            $table->enum('status', [
                'pending_admisi', 
                'pending_icu',   
                'bed_booked',    
                'di_icu',        
                'ditolak',       
                'pulang',       
            ])->default('pending_admisi');

            $table->string('alasan_tolak', 200)->nullable();
            $table->string('approved_by', 50)->nullable();  
            $table->string('booked_by', 50)->nullable();     

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_spri_internal');
    }
};
