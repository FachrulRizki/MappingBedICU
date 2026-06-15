<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_carabayar', function (Blueprint $table) {
            $table->string('KODE_BAYAR', 10)->primary();
            $table->string('KET_BAYAR', 100);
        });

        // Seed data default (mirror dari M_CARABAYAR di SQL Server)
        DB::table('m_carabayar')->insert([
            ['KODE_BAYAR' => '1', 'KET_BAYAR' => 'BPJS'],
            ['KODE_BAYAR' => '2', 'KET_BAYAR' => 'ASURANSI'],
            ['KODE_BAYAR' => '3', 'KET_BAYAR' => 'PRIBADI/MANDIRI'],
            ['KODE_BAYAR' => '8', 'KET_BAYAR' => 'TANGGUNGAN INSTANSI/PERUSAHAAN'],
            ['KODE_BAYAR' => '9', 'KET_BAYAR' => 'COVID'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('m_carabayar');
    }
};
