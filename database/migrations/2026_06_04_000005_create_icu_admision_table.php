<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ICU_ADMISION — alur LEGACY (jalur lama).
 *
 * Menangani monitoring pasien ICU sebelum ada jalur baru.
 * Jalur baru menggunakan:
 *   - icu_spri_internal  → pasien internal (pindah dari ruang lain)
 *   - icu_booking_external → pasien rujukan dari RS luar
 *
 * ALUR STATUS:
 *   Internal: daftar → igd_periksa → spri_dibuat → waiting_icu → booking_icu → di_icu → pulang
 *   External: ext_request → ext_waiting → booking_icu → di_icu → pulang
 *
 * Tabel ini tetap dipertahankan untuk data historis & controller yang masih aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_admision', function (Blueprint $table) {
            $table->id();

            // ── Referensi pasien (nullable untuk external sebelum linked ke MR) ─
            $table->string('No_Reg', 20)->nullable();
            $table->foreign('No_Reg')
                  ->references('No_Reg')->on('pendaftaran')
                  ->nullOnDelete();
            $table->string('No_MR', 20)->nullable();
            $table->foreign('No_MR')
                  ->references('No_MR')->on('registrasi_pasien')
                  ->nullOnDelete();

            // ── Data pasien external (sebelum punya No_MR di sistem) ───────────
            $table->string('nama_pasien_ext', 100)->nullable();
            $table->string('asal_rs', 100)->nullable();
            $table->string('jaminan', 50)->nullable();       // BPJS | Umum | Asuransi | dll.
            $table->text('catatan_jaminan')->nullable();
            $table->string('jenis_pasien', 20)->default('internal'); // internal | external

            // ── Status alur ────────────────────────────────────────────────────
            $table->enum('status', [
                'daftar',
                'igd_periksa',
                'spri_dibuat',
                'waiting_icu',
                'ext_request',
                'ext_waiting',
                'booking_icu',
                'di_icu',
                'pulang',
            ])->default('daftar');

            // ── Kebutuhan & alokasi bed ────────────────────────────────────────
            // required_bed_type: menyimpan Nama_Kelas (bukan Kode_Kelas)
            // contoh: 'ICU Non Ventilator', 'HCU', 'CVCU'
            $table->string('required_bed_type', 100)->nullable();
            $table->text('catatan_admisi')->nullable();
            $table->string('allocated_bed_id', 20)->nullable();
            $table->foreign('allocated_bed_id')
                  ->references('Kode_Ruang')->on('status_kamar')
                  ->nullOnDelete();
            $table->string('match_status', 20)->nullable(); // waiting | matched | rejected

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_admision');
    }
};
