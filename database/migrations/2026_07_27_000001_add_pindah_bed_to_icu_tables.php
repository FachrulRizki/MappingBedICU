<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->text('pindah_alasan')->nullable()->after('waiting_by')
                ->comment('Alasan pindah bed (opsional)');
            $table->string('pindah_bed_lama', 100)->nullable()->after('pindah_alasan')
                ->comment('Nama bed sebelum dipindah (histori)');
            $table->string('pindah_by', 100)->nullable()->after('pindah_bed_lama')
                ->comment('Petugas yang memproses pindah bed');
            $table->dateTime('pindah_at')->nullable()->after('pindah_by')
                ->comment('Waktu proses pindah bed');
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->text('pindah_alasan')->nullable()->after('waiting_by')
                ->comment('Alasan pindah bed (opsional)');
            $table->string('pindah_bed_lama', 100)->nullable()->after('pindah_alasan')
                ->comment('Nama bed sebelum dipindah (histori)');
            $table->string('pindah_by', 100)->nullable()->after('pindah_bed_lama')
                ->comment('Petugas yang memproses pindah bed');
            $table->dateTime('pindah_at')->nullable()->after('pindah_by')
                ->comment('Waktu proses pindah bed');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->dropColumn(['pindah_alasan', 'pindah_bed_lama', 'pindah_by', 'pindah_at']);
        });
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn(['pindah_alasan', 'pindah_bed_lama', 'pindah_by', 'pindah_at']);
        });
    }
};
