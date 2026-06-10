<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('icu_spri_internal', function (Blueprint $table) {
            $table->dropForeign(['No_MR']);
            $table->dropForeign(['No_Reg']);
        });
    }

    public function down(): void
    {
        //
    }
};
