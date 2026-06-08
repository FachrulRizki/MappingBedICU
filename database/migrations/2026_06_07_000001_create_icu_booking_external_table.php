<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk jalur EXTERNAL — pasien dari luar RS.
 * Booking dibuat Admisi sebelum pasien tiba, belum punya No_MR.
 *
 * Alur status:
 *   booking_request  → Admisi input data pasien + kebutuhan bed
 *   pending_icu      → Menunggu petugas ICU cek ketersediaan bed
 *   bed_confirmed    → ICU konfirmasi ada bed yang sesuai
 *   admisi_validated → Admisi validasi konfirmasi ICU, pasien dalam perjalanan
 *   pasien_tiba      → Pasien tiba di IGD, No_MR sudah di-link
 *   bed_verified     → Admisi verifikasi bed setelah pasien tiba
 *   di_icu           → Pasien sudah di ruang ICU, bed terisi
 *   ditolak          → ICU/Admisi menolak (bed tidak ada / tidak sesuai kriteria)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icu_booking_external', function (Blueprint $table) {
            $table->id();

            // ── Data pasien (sebelum punya No_MR) ────────────────────
            $table->string('nama_pasien');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('no_identitas')->nullable();          // NIK / identitas sementara
            $table->string('asal_rujukan')->nullable();          // RS asal / klinik pengirim
            $table->string('no_telp_keluarga')->nullable();

            // ── Data klinis (diisi Admisi saat booking) ───────────────
            $table->string('diagnosa');
            $table->string('rencana_tindakan');
            $table->string('kebutuhan_bed');                     // Nama_Kelas dari m_kelas
            $table->text('keterangan')->nullable();

            // ── Link ke data RS setelah pasien tiba ───────────────────
            $table->string('No_MR')->nullable();                 // FK setelah pasien terdaftar
            $table->string('No_Reg')->nullable();                // FK setelah kunjungan dibuat
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->nullOnDelete();
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->nullOnDelete();

            // ── Bed allocation ─────────────────────────────────────────
            $table->string('allocated_bed_id')->nullable();
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();

            // ── Status & tracking ──────────────────────────────────────
            $table->enum('status', [
                'booking_request',
                'pending_icu',
                'bed_confirmed',
                'admisi_validated',
                'pasien_tiba',
                'bed_verified',
                'di_icu',
                'ditolak',
            ])->default('booking_request');

            $table->string('alasan_tolak')->nullable();
            $table->string('created_by')->nullable();            // user admisi pembuat
            $table->string('confirmed_by')->nullable();          // user ICU yang konfirmasi
            $table->string('validated_by')->nullable();          // user admisi yang validasi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_booking_external');
    }
};
