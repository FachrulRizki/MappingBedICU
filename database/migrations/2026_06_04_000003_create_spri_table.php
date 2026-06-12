<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel SPRI — alur LEGACY, terikat dengan icu_admision.
 * Status: 'draft' | 'approved' | 'rejected'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spri', function (Blueprint $table) {
            $table->id();
            $table->string('No_Reg', 20);
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->onDelete('cascade');
            $table->string('Diagnosis', 200)->nullable();
            $table->string('IndikasiRI', 200)->nullable();
            $table->string('spesialis', 100)->nullable();
            $table->string('Dokter', 100)->nullable();
            $table->string('NameUser', 50)->nullable();
            $table->string('Perawatan', 50)->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('Status', 20)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spri');
    }
};
