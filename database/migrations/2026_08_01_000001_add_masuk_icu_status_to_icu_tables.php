<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan status 'masuk_icu' dan timestamp 'masuk_at' ke kedua tabel ICU.
 *
 * Status ini di-set otomatis oleh MonitorController saat Bed Management
 * mengubah STATUS_KAMAR menjadi 'ISI' untuk bed yang sudah dikonfirmasi ICU.
 * Aplikasi ini TIDAK pernah menulis ke STATUS_KAMAR — murni read-only dari sisi kita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->timestamp('masuk_at')->nullable()->after('verified_at');
            $table->string('masuk_by', 100)->nullable()->after('masuk_at')
                ->comment('Diisi "system" karena di-set otomatis via polling monitor');
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->timestamp('masuk_at')->nullable()->after('verified_at');
            $table->string('masuk_by', 100)->nullable()->after('masuk_at')
                ->comment('Diisi "system" karena di-set otomatis via polling monitor');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->dropColumn(['masuk_at', 'masuk_by']);
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn(['masuk_at', 'masuk_by']);
        });
    }
};
