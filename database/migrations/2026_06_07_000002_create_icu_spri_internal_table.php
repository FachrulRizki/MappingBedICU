<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_spri_internal', function (Blueprint $table) {
            $table->id();

            // ── Referensi ke data pasien yang sudah ada ───────────────
            $table->string('No_MR');
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->onDelete('cascade');
            $table->string('No_Reg');
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->onDelete('cascade');

            // ── Data klinis dari ruang asal ───────────────────────────
            $table->string('Diagnosis');
            $table->string('IndikasiRI');
            $table->string('kebutuhan_bed')->nullable();   // ← hapus ->change()
            $table->string('asal_ruang')->nullable();
            $table->string('Dokter')->nullable();
            $table->string('spesialis')->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('NameUser')->nullable();

            // ── Catatan dari Admisi ───────────────────────────────────
            $table->text('catatan_admisi')->nullable();    // ← hapus ->change()

            // ── Bed allocation (ditentukan Petugas ICU) ───────────────
            $table->string('allocated_bed_id')->nullable(); // ← hapus ->change()
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();

            // ── Status & tracking ─────────────────────────────────────
            $table->enum('status', [
                'pending_admisi',
                'pending_icu',
                'bed_booked',
                'di_icu',
                'ditolak',
                'pulang',
            ])->default('pending_admisi');

            $table->string('alasan_tolak')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('booked_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_spri_internal');
    }
};