<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->text('alasan_batal')->nullable()->after('alasan_tolak')
                ->comment('Catatan/alasan pembatalan oleh pemohon atau admisi');
            $table->string('dibatalkan_by', 100)->nullable()->after('alasan_batal');
            $table->dateTime('dibatalkan_at')->nullable()->after('dibatalkan_by');
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->text('alasan_batal')->nullable()->after('alasan_tolak')
                ->comment('Catatan/alasan pembatalan oleh petugas atau admisi');
            $table->string('dibatalkan_by', 100)->nullable()->after('alasan_batal');
            $table->dateTime('dibatalkan_at')->nullable()->after('dibatalkan_by');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->dropColumn(['alasan_batal', 'dibatalkan_by', 'dibatalkan_at']);
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn(['alasan_batal', 'dibatalkan_by', 'dibatalkan_at']);
        });
    }
};
