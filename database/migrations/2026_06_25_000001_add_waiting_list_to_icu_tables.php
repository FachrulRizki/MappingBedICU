<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom waiting list ke tabel booking external dan spri internal.
 *
 * Waiting list: ICU tidak bisa alokasi bed sekarang, tapi memberikan estimasi
 * kapan bed akan siap, beserta alasan kenapa masuk waiting list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('icu_booking_external', function (Blueprint $table) {
            $table->text('waiting_alasan')->nullable()->after('alasan_tolak')
                ->comment('Alasan masuk waiting list (diisi ICU)');
            $table->dateTime('waiting_estimasi')->nullable()->after('waiting_alasan')
                ->comment('Estimasi bed siap (diisi ICU)');
            $table->string('waiting_by', 100)->nullable()->after('waiting_estimasi')
                ->comment('Nama petugas ICU yang memasukkan ke waiting list');
        });

        Schema::table('icu_spri_internal', function (Blueprint $table) {
            $table->text('waiting_alasan')->nullable()->after('alasan_tolak')
                ->comment('Alasan masuk waiting list (diisi ICU)');
            $table->dateTime('waiting_estimasi')->nullable()->after('waiting_alasan')
                ->comment('Estimasi bed siap (diisi ICU)');
            $table->string('waiting_by', 100)->nullable()->after('waiting_estimasi')
                ->comment('Nama petugas ICU yang memasukkan ke waiting list');
        });
    }

    public function down(): void
    {
        Schema::table('icu_booking_external', function (Blueprint $table) {
            $table->dropColumn(['waiting_alasan', 'waiting_estimasi', 'waiting_by']);
        });
        Schema::table('icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn(['waiting_alasan', 'waiting_estimasi', 'waiting_by']);
        });
    }
};
