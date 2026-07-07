<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_users', function (Blueprint $table) {
            $table->json('ward_ids')->nullable()->after('auth_provider')
                ->comment('Kode_Bangsal dari Keycloak token — scope akses ruang petugas');
        });
    }

    public function down(): void
    {
        Schema::table('IB_users', function (Blueprint $table) {
            $table->dropColumn('ward_ids');
        });
    }
};
