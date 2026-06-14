<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom untuk SSO Keycloak ke tabel users.
 *
 * - keycloak_id       : nilai 'sub' dari JWT token (unique identifier Keycloak)
 * - keycloak_username : 'preferred_username' dari token
 * - auth_provider     : 'local' | 'keycloak' — untuk tahu jalur login user
 * - password          : dijadikan nullable karena user SSO tidak punya password lokal
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_id', 100)->nullable()->unique()->after('username');
            $table->string('keycloak_username', 100)->nullable()->after('keycloak_id');
            $table->string('auth_provider', 20)->default('local')->after('keycloak_username'); // local | keycloak
            $table->string('password')->nullable()->change(); // SSO user tidak butuh password
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['keycloak_id', 'keycloak_username', 'auth_provider']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
