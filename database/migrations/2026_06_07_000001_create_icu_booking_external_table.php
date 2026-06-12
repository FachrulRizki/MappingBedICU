<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_booking_external', function (Blueprint $table) {
            $table->id();

            // ── Identitas pasien (sebelum punya No_MR)
            $table->string('nama_pasien', 100);
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('no_identitas', 30)->nullable();
            $table->string('asal_rujukan', 100)->nullable();
            $table->string('no_telp_keluarga', 20)->nullable();

            // ── Data klinis
            $table->string('diagnosa', 200);
            $table->string('rencana_tindakan', 200);
            $table->string('kebutuhan_bed', 100)->nullable();

            // ── Jaminan (diisi Admisi saat buat booking)
            $table->string('jaminan', 50)->nullable();
            $table->text('catatan_jaminan')->nullable();
            $table->text('keterangan')->nullable();

            // ── Link No_MR setelah pasien tiba (diisi Admisi saat verifikasi) ─
            $table->string('No_MR', 20)->nullable();
            $table->string('No_Reg', 20)->nullable();

            // ── Bed referensi (diisi ICU, no FK)──────
            $table->string('allocated_bed_id', 20)->nullable();
            $table->string('nama_bed', 100)->nullable();

            // ── Status alur────
            $table->enum('status', [
                'pending_icu',
                'bed_confirmed',
                'admisi_verified',
                'ditolak',
            ])->default('pending_icu');

            $table->string('alasan_tolak', 200)->nullable();

            // ── Tracking siapa yang aksi
            $table->string('created_by', 50)->nullable();
            $table->string('confirmed_by', 50)->nullable();
            $table->string('verified_by', 50)->nullable(); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_booking_external');
    }
};
