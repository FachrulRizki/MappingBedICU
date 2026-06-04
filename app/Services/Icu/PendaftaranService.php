<?php

namespace App\Services\Icu;

use App\Models\IcuAdmision;
use App\Models\Pendaftaran;
use App\Models\RegistrasiPasien;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PendaftaranService
{
    /**
     * Daftarkan pasien baru:
     * 1. Buat RegistrasiPasien (No_MR baru)
     * 2. Buat Pendaftaran (No_Reg baru)
     * 3. Buat IcuAdmision dengan status 'daftar'
     *
     * @return array{pasien: RegistrasiPasien, admision: IcuAdmision}
     */
    public function daftarkanPasien(
        string  $namaPasien,
        string  $noIdentitas,
        ?string $kartuBpjs    = null,
        ?string $jenisKelamin = null
    ): array {
        $noMR  = 'MR-'  . strtoupper(Str::random(6));
        $noReg = 'REG-' . date('Y') . '-' . strtoupper(Str::random(5));

        $pasien = RegistrasiPasien::create([
            'No_MR'          => $noMR,
            'Nama_Pasien'    => $namaPasien,
            'jenis_kelamin'  => $jenisKelamin,
            'tgl_regist'     => Carbon::now(),
            'No_Identitas'   => $noIdentitas,
            'KartuBPJS'      => $kartuBpjs,
            'NameUser'       => 'petugas_daftar',
        ]);

        Pendaftaran::create([
            'No_Reg'     => $noReg,
            'No_MR'      => $noMR,
            'Kode_Masuk' => 'IGD',
            'Kode_Asal'  => 'UGD',
            'NameUser'   => 'petugas_daftar',
        ]);

        $admision = IcuAdmision::create([
            'No_Reg' => $noReg,
            'No_MR'  => $noMR,
            'status' => 'daftar',
        ]);

        return compact('pasien', 'admision');
    }
}
