<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_users', function (Blueprint $table) {
            $table->string('keycloak_id', 100)->nullable()->unique()->after('username');
            $table->string('keycloak_username', 100)->nullable()->after('keycloak_id');
            $table->string('auth_provider', 20)->default('local')->after('keycloak_username'); // local | keycloak
            $table->string('password')->nullable()->change(); // SSO user tidak butuh password
        });
    }

    public function down(): void
    {
        Schema::table('IB_users', function (Blueprint $table) {
            $table->dropColumn(['keycloak_id', 'keycloak_username', 'auth_provider']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
