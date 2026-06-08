<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk jalur EXTERNAL — pasien rujukan dari RS luar.
 *
 * ALUR STATUS (disederhanakan):
 *   pending_icu    → Admisi input data + jaminan, langsung antri ke ICU
 *   bed_confirmed  → ICU pilih & konfirmasi bed (jika ada bed)
 *                    Jika tidak ada bed → tetap pending_icu (waiting list)
 *   di_icu         → ICU konfirmasi pasien masuk ruangan → LANGSUNG di_icu
 *   ditolak        → ICU tolak (kriteria tidak sesuai)
 *   pulang         → Pasien keluar, bed kosong kembali
 *
 * Tidak ada step validasi admisi setelah ICU konfirmasi bed.
 * Admisi hanya mengisi keterangan/jaminan, ICU yang tentukan bed.
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
            $table->string('no_identitas')->nullable();
            $table->string('asal_rujukan')->nullable();          // RS asal / klinik pengirim
            $table->string('no_telp_keluarga')->nullable();

            // ── Data klinis (diisi Admisi saat booking) ───────────────
            $table->string('diagnosa');
            $table->string('rencana_tindakan');
            $table->string('kebutuhan_bed');                     // Nama_Kelas dari m_kelas

            // ── Form jaminan (diisi Admisi) ───────────────────────────
            $table->string('jaminan')->nullable();               // BPJS | Umum | Asuransi | Lainnya
            $table->text('catatan_jaminan')->nullable();         // nomor BPJS, nama asuransi, dll
            $table->text('keterangan')->nullable();              // catatan tambahan dari admisi

            // ── Link ke data RS setelah pasien tiba (opsional) ────────
            $table->string('No_MR')->nullable();
            $table->string('No_Reg')->nullable();
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->nullOnDelete();
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->nullOnDelete();

            // ── Bed allocation (ditentukan Petugas ICU) ───────────────
            $table->string('allocated_bed_id')->nullable();
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();

            // ── Status & tracking ──────────────────────────────────────
            $table->enum('status', [
                'pending_icu',     // admisi input, antri ke ICU
                'bed_confirmed',   // ICU sudah pilih bed → siap antar
                'di_icu',          // pasien di ruangan, bed terisi
                'ditolak',         // ICU tolak
                'pulang',          // pasien keluar
            ])->default('pending_icu');

            $table->string('alasan_tolak')->nullable();
            $table->string('created_by')->nullable();            // admisi yang buat
            $table->string('confirmed_by')->nullable();          // ICU yang konfirmasi bed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_booking_external');
    }
};
