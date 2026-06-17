<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah ward_ids (bangsal yang menjadi tanggung jawab user) dari Keycloak token.
 * Nilai berupa JSON array of Kode_Bangsal, e.g. ["CB"] atau ["ICU"].
 * Admin bisa punya banyak ward_ids, petugas_ruang hanya 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('ward_ids')->nullable()->after('auth_provider')
                ->comment('Kode_Bangsal dari Keycloak token — scope akses ruang petugas');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ward_ids');
        });
    }
};
