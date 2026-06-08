<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah status 'pulang' ke enum kedua tabel jalur baru.
 * MySQL tidak support ALTER ENUM langsung — pakai MODIFY COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE icu_booking_external MODIFY COLUMN `status` ENUM(
                'booking_request','pending_icu','bed_confirmed','admisi_validated',
                'pasien_tiba','bed_verified','di_icu','ditolak','pulang'
            ) NOT NULL DEFAULT 'booking_request'");

            DB::statement("ALTER TABLE icu_spri_internal MODIFY COLUMN `status` ENUM(
                'spri_dibuat','pending_admisi','admisi_approved','pending_icu',
                'bed_booked','admisi_verified','di_icu','ditolak','pulang'
            ) NOT NULL DEFAULT 'spri_dibuat'");
        } catch (\Throwable $e) {
            // SQLite atau driver lain — skip
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE icu_booking_external MODIFY COLUMN `status` ENUM(
                'booking_request','pending_icu','bed_confirmed','admisi_validated',
                'pasien_tiba','bed_verified','di_icu','ditolak'
            ) NOT NULL DEFAULT 'booking_request'");

            DB::statement("ALTER TABLE icu_spri_internal MODIFY COLUMN `status` ENUM(
                'spri_dibuat','pending_admisi','admisi_approved','pending_icu',
                'bed_booked','admisi_verified','di_icu','ditolak'
            ) NOT NULL DEFAULT 'spri_dibuat'");
        } catch (\Throwable) {}
    }
};
