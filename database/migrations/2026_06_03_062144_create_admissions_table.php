<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            // Alur Status: 'daftar', 'igd_periksa', 'spri_dibuat', 'waiting_icu', 'booking_icu', 'di_icu'
            $table->string('status')->default('daftar');
            $table->string('required_bed_type')->nullable(); // Diisi saat BUAT SPRI
            $table->string('allocated_bed')->nullable(); // Diisi saat DAPAT KAMAR
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admissions');
    }
};
