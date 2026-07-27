<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Booking External — tambah diagnosa_icd setelah kolom diagnosa
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->string('diagnosa_icd', 200)->nullable()->after('diagnosa')
                ->comment('Kode ICD-10 untuk keperluan klaim/coding (opsional)');
        });

        // SPRI Internal — tambah Diagnosis_ICD setelah kolom Diagnosis
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->string('Diagnosis_ICD', 200)->nullable()->after('Diagnosis')
                ->comment('Kode ICD-10 untuk keperluan klaim/coding (opsional)');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->dropColumn('diagnosa_icd');
        });
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn('Diagnosis_ICD');
        });
    }
};
