<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->text('Diagnosis')->change();
            $table->text('IndikasiRI')->change();
        });
    }

    public function down(): void
    {
        Schema::table('IB_icu_spri_internal', function (Blueprint $table) {
            $table->string('Diagnosis', 200)->change();
            $table->string('IndikasiRI', 200)->change();
        });
    }
};
