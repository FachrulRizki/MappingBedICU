<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('icu_booking_external', function (Blueprint $table) {
            $table->string('kebutuhan_bed')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('icu_booking_external', function (Blueprint $table) {
            $table->string('kebutuhan_bed')->nullable(false)->change();
        });
    }
};