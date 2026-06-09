<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegistrasiPasien;
use App\Models\MKelas;
use App\Models\StatusKamar;

class TestDbConnection extends Command
{
    protected $signature   = 'db:test-rsus';
    protected $description = 'Test koneksi DB RS dan fallback mechanism';

    public function handle(): int
    {
        $sqlsrvAvail = config('app.sqlsrv_available', 'NOT SET');
        $ext         = extension_loaded('pdo_sqlsrv') ? '✓ YA' : '✗ TIDAK';

        $this->info('=== Test Koneksi DB ===');
        $this->line("pdo_sqlsrv extension  : {$ext}");
        $this->line("app.sqlsrv_available  : " . ($sqlsrvAvail === true ? 'true' : ($sqlsrvAvail === false ? 'false' : $sqlsrvAvail)));

        $rp = new RegistrasiPasien();
        $mk = new MKelas();
        $sk = new StatusKamar();

        $this->newLine();
        $this->info('=== Model Connections ===');
        $this->line("RegistrasiPasien  → connection: {$rp->getConnectionName()} | table: {$rp->getTable()}");
        $this->line("MKelas            → connection: {$mk->getConnectionName()} | table: {$mk->getTable()}");
        $this->line("StatusKamar       → connection: {$sk->getConnectionName()} | table: {$sk->getTable()}");

        $this->newLine();
        $this->info('=== Query Test ===');

        try {
            $rpCount = RegistrasiPasien::count();
            $this->line("RegistrasiPasien count : {$rpCount} ✓");
        } catch (\Exception $e) {
            $this->error("RegistrasiPasien ERROR: " . $e->getMessage());
        }

        try {
            $mkCount = MKelas::count();
            $this->line("MKelas count           : {$mkCount} ✓");
        } catch (\Exception $e) {
            $this->error("MKelas ERROR: " . $e->getMessage());
        }

        try {
            $skCount = StatusKamar::where('Status', 'KOSONG')->count();
            $this->line("StatusKamar KOSONG     : {$skCount} ✓");
        } catch (\Exception $e) {
            $this->error("StatusKamar ERROR: " . $e->getMessage());
        }

        return 0;
    }
}
