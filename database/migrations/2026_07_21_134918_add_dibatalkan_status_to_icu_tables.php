<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `IB_icu_booking_external` MODIFY `status` ENUM('pending_icu','bed_confirmed','admisi_verified','ditolak','waiting_list','dibatalkan') NOT NULL DEFAULT 'pending_icu'");

        DB::statement("ALTER TABLE `IB_icu_spri_internal` MODIFY `status` ENUM('pending_admisi','pending_icu','bed_verified','ditolak','waiting_list','dibatalkan') NOT NULL DEFAULT 'pending_admisi'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: update status 'dibatalkan' ke 'ditolak' sebelum hapus dari ENUM
        DB::table('IB_icu_booking_external')
            ->where('status', 'dibatalkan')
            ->update(['status' => 'ditolak']);
            
        DB::table('IB_icu_spri_internal')
            ->where('status', 'dibatalkan')
            ->update(['status' => 'ditolak']);

        DB::statement("ALTER TABLE `IB_icu_booking_external` MODIFY `status` ENUM('pending_icu','bed_confirmed','admisi_verified','ditolak','waiting_list') NOT NULL DEFAULT 'pending_icu'");

        DB::statement("ALTER TABLE `IB_icu_spri_internal` MODIFY `status` ENUM('pending_admisi','pending_icu','bed_verified','ditolak','waiting_list') NOT NULL DEFAULT 'pending_admisi'");
    }
};
