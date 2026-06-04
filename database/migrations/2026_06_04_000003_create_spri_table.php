<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spri', function (Blueprint $table) {
            $table->id();
            $table->string('No_Reg');
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->onDelete('cascade');
            $table->string('Diagnosis')->nullable();
            $table->string('IndikasiRI')->nullable();
            $table->string('spesialis')->nullable();
            $table->string('Dokter')->nullable();
            $table->string('NameUser')->nullable();
            $table->string('Perawatan')->nullable();
            $table->string('Keterangan')->nullable();
            // Status: 'draft', 'approved', 'rejected'
            $table->string('Status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spri');
    }
};
