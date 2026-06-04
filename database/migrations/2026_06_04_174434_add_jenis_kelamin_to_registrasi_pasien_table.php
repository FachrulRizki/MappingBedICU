<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrasi_pasien', function (Blueprint $table) {
            // 'L' = Laki-laki, 'P' = Perempuan
            $table->char('jenis_kelamin', 1)->nullable()->after('Nama_Pasien');
        });
    }

    public function down(): void
    {
        Schema::table('registrasi_pasien', function (Blueprint $table) {
            $table->dropColumn('jenis_kelamin');
        });
    }
};
