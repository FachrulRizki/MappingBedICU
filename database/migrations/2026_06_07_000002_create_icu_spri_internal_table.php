<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk jalur INTERNAL — pasien sudah ada di RS, indikasi pindah ke ICU.
 * Data pasien diambil dari tabel pendaftaran & registrasi_pasien yang sudah ada.
 * Dokumen ini adalah "Surat Permintaan Rawat ICU" (rename dari SPRI).
 *
 * Alur status:
 *   spri_dibuat      → Petugas ruang asal buat surat permintaan
 *   pending_admisi   → Menunggu Admisi approve
 *   admisi_approved  → Admisi setujui, lanjut ke ICU untuk booking bed
 *   pending_icu      → Menunggu petugas ICU validasi booking bed
 *   bed_booked       → ICU konfirmasi booking bed sesuai kebutuhan
 *   admisi_verified  → Admisi verifikasi akhir, pasien siap diantar
 *   di_icu           → Pasien sudah di ruang ICU, bed terisi
 *   ditolak          → Ditolak oleh Admisi atau ICU
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
            $table->string('IndikasiRI');                        // Indikasi rawat ICU
            $table->string('kebutuhan_bed');                     // Nama_Kelas dari m_kelas
            $table->string('asal_ruang')->nullable();            // Nama/kode ruang asal pasien
            $table->string('Dokter')->nullable();                // Dokter DPJP yang meminta
            $table->string('spesialis')->nullable();
            $table->text('Keterangan')->nullable();
            $table->string('NameUser')->nullable();              // Petugas ruang yang input

            // ── Bed allocation ─────────────────────────────────────────
            $table->string('allocated_bed_id')->nullable();
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();

            // ── Status & tracking ──────────────────────────────────────
            $table->enum('status', [
                'spri_dibuat',
                'pending_admisi',
                'admisi_approved',
                'pending_icu',
                'bed_booked',
                'admisi_verified',
                'di_icu',
                'ditolak',
            ])->default('spri_dibuat');

            $table->string('alasan_tolak')->nullable();
            $table->string('approved_by')->nullable();           // user admisi yang approve
            $table->string('booked_by')->nullable();             // user ICU yang booking bed
            $table->string('verified_by')->nullable();           // user admisi yang verifikasi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_spri_internal');
    }
};
