<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── IB_icu_booking_external ─────────────────────────────────────────
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            // Filter utama: WHERE status IN (...)
            $table->index('status', 'idx_ibe_status');
            // Sync & releasePemegangBed: WHERE allocated_bed_id + status
            $table->index(['allocated_bed_id', 'status'], 'idx_ibe_bed_status');
            // Lookup pasien & filter nama
            $table->index('No_MR', 'idx_ibe_no_mr');
            // Filter tanggal range
            $table->index('created_at', 'idx_ibe_created_at');
            // Timestamp-based filters di summary
            $table->index('confirmed_at', 'idx_ibe_confirmed_at');
            $table->index('verified_at',  'idx_ibe_verified_at');
        });

        // ── IB_icu_spri_internal ────────────────────────────────────────────
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            // Filter utama: WHERE status IN (...)
            $table->index('status', 'idx_isi_status');
            // Sync & releasePemegangBed: WHERE allocated_bed_id + status
            $table->index(['allocated_bed_id', 'status'], 'idx_isi_bed_status');
            // Lookup pasien di AntrianService::queryInternal
            $table->index('No_MR', 'idx_isi_no_mr');
            // Filter tanggal range
            $table->index('created_at', 'idx_isi_created_at');
            // Timestamp-based filters di summary
            $table->index('approved_at', 'idx_isi_approved_at');
            $table->index('verified_at', 'idx_isi_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_booking_external', function (Blueprint $table) {
            $table->dropIndex('idx_ibe_status');
            $table->dropIndex('idx_ibe_bed_status');
            $table->dropIndex('idx_ibe_no_mr');
            $table->dropIndex('idx_ibe_created_at');
            $table->dropIndex('idx_ibe_confirmed_at');
            $table->dropIndex('idx_ibe_verified_at');
        });

        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropIndex('idx_isi_status');
            $table->dropIndex('idx_isi_bed_status');
            $table->dropIndex('idx_isi_no_mr');
            $table->dropIndex('idx_isi_created_at');
            $table->dropIndex('idx_isi_approved_at');
            $table->dropIndex('idx_isi_verified_at');
        });
    }
};
