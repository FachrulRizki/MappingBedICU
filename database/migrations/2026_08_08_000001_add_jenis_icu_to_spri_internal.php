<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->string('jenis_icu', 100)->nullable()->after('kebutuhan_bed');
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->dropColumn('jenis_icu');
        });
    }
};
