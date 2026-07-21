<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->table('IB_icu_spri_internal', function (Blueprint $table) {
                $table->string('simrs_no_reg', 20)
                      ->nullable()
                      ->unique()
                      ->after('No_Reg')
                      ->comment('No_Reg SIMRS — unique guard anti duplikat import');

                $table->string('simrs_source', 30)
                      ->nullable()
                      ->after('simrs_no_reg')
                      ->comment('Sumber import: igd_asesmen, dll');

                $table->timestamp('simrs_imported_at')
                      ->nullable()
                      ->after('simrs_source')
                      ->comment('Waktu pertama kali data masuk dari SIMRS');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('IB_icu_spri_internal', function (Blueprint $table) {
                $table->dropColumn(['simrs_no_reg', 'simrs_source', 'simrs_imported_at']);
            });
    }
};
