<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ICU_BOOKING_EXTERNAL — jalur EXTERNAL (pasien rujukan dari RS luar).
 *
 * Pasien belum tentu memiliki No_MR saat booking dibuat.
 * Admisi mengisi data klinis + jaminan, Petugas ICU yang menentukan bed.
 *
 * ALUR STATUS:
 *   pending_icu → bed_confirmed → di_icu → pulang
 *                              ↘ ditolak
 *
 * Catatan:
 *   - kebutuhan_bed: nullable, menyimpan Nama_Kelas dari m_kelas
 *   - No_MR & No_Reg: opsional, diisi setelah pasien resmi terdaftar di sistem RS
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_booking_external', function (Blueprint $table) {
            $table->id();

            // ── Data pasien (sebelum punya No_MR) ─────────────────────────────
            $table->string('nama_pasien', 100);
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('no_identitas', 30)->nullable();
            $table->string('asal_rujukan', 100)->nullable();
            $table->string('no_telp_keluarga', 20)->nullable();

            // ── Data klinis ────────────────────────────────────────────────────
            $table->string('diagnosa', 200);
            $table->string('rencana_tindakan', 200);
            $table->string('kebutuhan_bed', 100)->nullable(); // Nama_Kelas dari m_kelas

            // ── Jaminan & catatan (diisi Admisi) ──────────────────────────────
            $table->string('jaminan', 50)->nullable();        // BPJS | Umum | Asuransi | Lainnya
            $table->text('catatan_jaminan')->nullable();
            $table->text('keterangan')->nullable();

            // ── Link ke data RS setelah pasien tiba (opsional) ────────────────
            $table->string('No_MR', 20)->nullable();
            $table->foreign('No_MR')
                  ->references('No_MR')->on('registrasi_pasien')
                  ->nullOnDelete();
            $table->string('No_Reg', 20)->nullable();
            $table->foreign('No_Reg')
                  ->references('No_Reg')->on('pendaftaran')
                  ->nullOnDelete();

            // ── Alokasi bed (ditentukan Petugas ICU) ──────────────────────────
            $table->string('allocated_bed_id', 20)->nullable();
            $table->foreign('allocated_bed_id')
                  ->references('Kode_Ruang')->on('status_kamar')
                  ->nullOnDelete();

            // ── Status & tracking ──────────────────────────────────────────────
            $table->enum('status', [
                'pending_icu',    // admisi input, antri ke ICU
                'bed_confirmed',  // ICU sudah pilih bed, siap antar
                'di_icu',         // pasien di ruangan, bed terisi
                'ditolak',        // ICU tolak
                'pulang',         // pasien keluar
            ])->default('pending_icu');

            $table->string('alasan_tolak', 200)->nullable();
            $table->string('created_by', 50)->nullable();
            $table->string('confirmed_by', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_booking_external');
    }
};
