<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom timestamp aksi ke tabel booking ICU.
 *
 * icu_booking_external:
 *   - confirmed_at  → kapan ICU konfirmasi bed
 *   - verified_at   → kapan Admisi verifikasi No_MR (pasien tiba)
 *
 * icu_spri_internal:
 *   - approved_at   → kapan Admisi approve SPRI ke ICU
 *   - verified_at   → kapan ICU verifikasi bed untuk SPRI
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('icu_booking_external', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('confirmed_by')
                ->comment('Kapan ICU konfirmasi bed');
            $table->dateTime('verified_at')->nullable()->after('verified_by')
                ->comment('Kapan Admisi verifikasi pasien tiba (link No_MR)');
        });

        Schema::table('icu_spri_internal', function (Blueprint $table) {
            $table->dateTime('approved_at')->nullable()->after('approved_by')
                ->comment('Kapan Admisi approve SPRI ke ICU');
            $table->dateTime('verified_at')->nullable()->after('verified_by')
                ->comment('Kapan ICU verifikasi bed untuk SPRI');
        });
    }

    public function down(): void
    {
        Schema::table('icu_booking_external', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'verified_at']);
        });
        Schema::table('icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'verified_at']);
        });
    }
};
