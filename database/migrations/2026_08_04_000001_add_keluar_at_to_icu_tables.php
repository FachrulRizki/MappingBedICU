<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->timestamp('keluar_at')->nullable()->after('masuk_by')
                ->comment('Di-set otomatis via polling saat STATUS_KAMAR = KOSONG setelah masuk_icu');
            $table->string('keluar_by', 100)->nullable()->after('keluar_at')
                ->comment('Diisi "system" karena di-set otomatis via polling monitor');
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->timestamp('keluar_at')->nullable()->after('masuk_by')
                ->comment('Di-set otomatis via polling saat STATUS_KAMAR = KOSONG setelah masuk_icu');
            $table->string('keluar_by', 100)->nullable()->after('keluar_at')
                ->comment('Diisi "system" karena di-set otomatis via polling monitor');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->dropColumn(['keluar_at', 'keluar_by']);
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn(['keluar_at', 'keluar_by']);
        });
    }
};
