<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel ICUAdmision adalah tabel INTI alur monitoring.
     * Setiap baris merepresentasikan satu siklus perjalanan pasien dari IGD → ICU.
     *
     * Status enum:
     *   daftar        -> Pasien baru didaftarkan (Registrasi)
     *   igd_periksa   -> Pasien dikirim ke IGD untuk triase/diagnosis
     *   spri_dibuat   -> Dokter IGD membuat SPRI + menentukan required_bed_type
     *   waiting_icu   -> SPRI disetujui Admisi, pasien masuk daftar tunggu ICU
     *   booking_icu   -> Bed ICU yang cocok ditemukan & dialokasikan
     *   di_icu        -> Pasien sudah diantar & masuk ke ruangan ICU
     *   pulang        -> Pasien keluar (sembuh/pindah), bed dikosongkan kembali
     */
    public function up(): void
    {
        Schema::create('icu_admision', function (Blueprint $table) {
            $table->id();
            $table->string('No_Reg');
            $table->foreign('No_Reg')->references('No_Reg')->on('pendaftaran')->onDelete('cascade');
            $table->string('No_MR');
            $table->foreign('No_MR')->references('No_MR')->on('registrasi_pasien')->onDelete('cascade');
            $table->enum('status', [
                'daftar',
                'igd_periksa',
                'spri_dibuat',
                'waiting_icu',
                'booking_icu',
                'di_icu',
                'pulang',
            ])->default('daftar');
            $table->string('required_bed_type')->nullable();   // Kode_Kelas: ICUNV, HCU, ICU, CVCU
            $table->string('allocated_bed_id')->nullable();    // FK ke status_kamar.Kode_Ruang
            $table->foreign('allocated_bed_id')->references('Kode_Ruang')->on('status_kamar')->nullOnDelete();
            // match_status: null | 'matched' | 'waiting'
            $table->string('match_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icu_admision');
    }
};
