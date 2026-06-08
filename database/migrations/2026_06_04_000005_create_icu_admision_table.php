<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel ICUAdmision — inti alur monitoring pasien ICU.
     *
     * ALUR INTERNAL (dari ruang perawatan):
     *   daftar → igd_periksa → spri_dibuat → waiting_icu
     *   → [Petugas ICU pilih bed] → booking_icu → di_icu → pulang
     *
     * ALUR EXTERNAL (pasien rujukan dari RS luar):
     *   ext_request → ext_waiting
     *   → [Petugas ICU pilih bed] → booking_icu → di_icu → pulang
     *
     * Admisi hanya mengisi catatan/keterangan, tidak menentukan bed.
     * Petugas ICU yang menentukan dan mengalokasikan bed.
     */
    public function up(): void
    {
        Schema::create('icu_admision', function (Blueprint $table) {
            $table->id();

            // FK ke pendaftaran & registrasi (nullable untuk external yg belum punya No_MR)
            $table->string('No_Reg')->nullable();
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->nullOnDelete();
            $table->string('No_MR')->nullable();
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->nullOnDelete();

            // Untuk pasien external yang belum punya No_MR di sistem
            $table->string('nama_pasien_ext')->nullable();   // nama sementara sebelum linked ke MR
            $table->string('asal_rs')->nullable();           // nama RS pengirim
            $table->string('jaminan')->nullable();           // BPJS | Umum | Asuransi | dll
            $table->text('catatan_jaminan')->nullable();     // detail jaminan/catatan tambahan
            $table->string('jenis_pasien')->default('internal'); // internal | external

            $table->enum('status', [
                // ── Internal ──────────────────────────
                'daftar',           // baru didaftarkan
                'igd_periksa',      // dikirim ke IGD
                'spri_dibuat',      // SPRI dibuat oleh dokter/petugas ruang
                'waiting_icu',      // menunggu konfirmasi & bed dari ICU
                // ── External ──────────────────────────
                'ext_request',      // permintaan bed masuk dari RS luar
                'ext_waiting',      // admisi sudah input catatan, menunggu ICU
                // ── Bersama (setelah ICU konfirmasi) ──
                'booking_icu',      // ICU sudah tentukan bed, pasien dalam perjalanan
                'di_icu',           // pasien sudah di ruangan ICU
                'pulang',           // pasien keluar, bed kembali kosong
            ])->default('daftar');

            // Kebutuhan bed — diisi saat SPRI dibuat (internal) atau saat request (external)
            // Nilainya = Kode_Kelas dari M_KELAS: ICUNV, HCU, ICU, CVCU, dst.
            $table->string('required_bed_type')->nullable();

            // Catatan dari Admisi (bukan alokasi bed — hanya keterangan kebutuhan)
            $table->text('catatan_admisi')->nullable();

            // Bed yang dipilih & dialokasikan oleh Petugas ICU
            $table->string('allocated_bed_id')->nullable();
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();

            // matched | waiting | rejected
            $table->string('match_status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_admision');
    }
};
