<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ICU_SPRI_INTERNAL — jalur INTERNAL (pindah ICU dari ruang lain).
 *
 * Pasien sudah terdaftar di RS (punya No_MR & No_Reg).
 * Petugas ruang membuat SPRI → Admisi validasi → Petugas ICU tentukan bed.
 *
 * ALUR STATUS:
 *   pending_admisi → pending_icu → bed_booked → di_icu → pulang
 *                               ↘ ditolak
 *
 * Catatan:
 *   - FK ke registrasi_pasien & pendaftaran sengaja tidak dibuat karena
 *     pada produksi tabel tersebut ada di SQL Server RS (bukan DB lokal ini).
 *     Relasi dijaga via logika aplikasi (bukan constraint DB).
 *   - kebutuhan_bed: nullable, menyimpan Nama_Kelas dari m_kelas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_spri_internal', function (Blueprint $table) {
            $table->id();

            // ── Referensi pasien (no FK — lihat catatan di atas) ──────────────
            $table->string('No_MR', 20);
            $table->string('No_Reg', 20);

            // ── Data klinis dari ruang asal ────────────────────────────────────
            $table->string('Diagnosis', 200);
            $table->string('IndikasiRI', 200);
            $table->string('kebutuhan_bed', 100)->nullable(); // Nama_Kelas dari m_kelas
            $table->string('asal_ruang', 100)->nullable();
            $table->string('Dokter', 100)->nullable();
            $table->string('spesialis', 100)->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('NameUser', 50)->nullable();       // petugas ruang yang buat SPRI

            // ── Catatan dari Admisi ────────────────────────────────────────────
            $table->text('catatan_admisi')->nullable();

            // ── Alokasi bed (ditentukan Petugas ICU) ──────────────────────────
            $table->string('allocated_bed_id', 20)->nullable();
            $table->foreign('allocated_bed_id')
                  ->references('Kode_Ruang')->on('status_kamar')
                  ->nullOnDelete();

            // ── Status & tracking ──────────────────────────────────────────────
            $table->enum('status', [
                'pending_admisi', // SPRI dibuat, menunggu validasi admisi
                'pending_icu',    // admisi approve, menunggu ICU tentukan bed
                'bed_booked',     // ICU sudah pilih bed, siap antar
                'di_icu',         // pasien di ruangan, bed terisi
                'ditolak',        // ICU atau admisi tolak
                'pulang',         // pasien keluar
            ])->default('pending_admisi');

            $table->string('alasan_tolak', 200)->nullable();
            $table->string('approved_by', 50)->nullable();   // admisi yang approve
            $table->string('booked_by', 50)->nullable();     // ICU yang booking bed

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_spri_internal');
    }
};
