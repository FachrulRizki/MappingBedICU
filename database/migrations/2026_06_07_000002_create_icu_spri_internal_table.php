<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk jalur INTERNAL — pasien sudah ada di RS, indikasi pindah ke ICU.
 *
 * ALUR STATUS (disederhanakan):
 *   pending_admisi  → Petugas ruang buat SPRI, menunggu Admisi
 *   pending_icu     → Admisi approve + isi catatan → diteruskan ke ICU
 *   bed_booked      → ICU pilih & booking bed → siap antar
 *                     Jika tidak ada bed → tetap pending_icu (waiting list)
 *   di_icu          → ICU konfirmasi pasien masuk → LANGSUNG di_icu
 *   ditolak         → Admisi atau ICU tolak
 *   pulang          → Pasien keluar, bed kosong kembali
 *
 * Tidak ada step admisi_verified / verifikasiAdmisi setelah ICU booking bed.
 * Admisi hanya mengisi catatan, ICU yang tentukan & pilih bed.
 */
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
            $table->string('kebutuhan_bed');                     // Nama_Kelas dari m_kelas
            $table->string('asal_ruang')->nullable();
            $table->string('Dokter')->nullable();
            $table->string('spesialis')->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('NameUser')->nullable();              // petugas ruang yang input

            // ── Catatan dari Admisi (HANYA keterangan, bukan bed) ─────
            $table->text('catatan_admisi')->nullable();          // jaminan, catatan, info tambahan

            // ── Bed allocation (ditentukan Petugas ICU) ───────────────
            $table->string('allocated_bed_id')->nullable();
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();

            // ── Status & tracking ──────────────────────────────────────
            $table->enum('status', [
                'pending_admisi',  // petugas ruang buat, menunggu admisi
                'pending_icu',     // admisi approve + catat, antri ke ICU
                'bed_booked',      // ICU sudah pilih bed → siap antar
                'di_icu',          // pasien di ruangan, bed terisi
                'ditolak',         // admisi/ICU tolak
                'pulang',          // pasien keluar
            ])->default('pending_admisi');

            $table->string('alasan_tolak')->nullable();
            $table->string('approved_by')->nullable();           // admisi yang approve
            $table->string('booked_by')->nullable();             // ICU yang booking bed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_spri_internal');
    }
};
