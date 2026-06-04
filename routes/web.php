<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Icu\DashboardController;
use App\Http\Controllers\Icu\PendaftaranController;
use App\Http\Controllers\Icu\IgdController;
use App\Http\Controllers\Icu\SpriController;
use App\Http\Controllers\Icu\BedController;
use App\Http\Controllers\Icu\TransferController;
use App\Http\Controllers\Icu\PulangController;
use App\Http\Controllers\Icu\PasienIcuController;

// Redirect root ke dashboard
Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── Dashboard ─────────────────────────────────────────────────────────────
Route::get('/dashboard-icu', [DashboardController::class, 'index'])
    ->name('icu.dashboard');

// ── Pendaftaran ───────────────────────────────────────────────────────────
Route::get('/icu/pendaftaran', [PendaftaranController::class, 'index'])
    ->name('icu.pendaftaran');
Route::post('/icu/tambah', [PendaftaranController::class, 'store'])
    ->name('icu.tambah');

// ── IGD ───────────────────────────────────────────────────────────────────
Route::get('/icu/igd', [IgdController::class, 'index'])
    ->name('icu.igd');
Route::post('/icu/kirim-igd/{id}', [IgdController::class, 'kirimIgd'])
    ->name('icu.kirim_igd');

// ── SPRI ──────────────────────────────────────────────────────────────────
Route::get('/icu/spri', [SpriController::class, 'index'])
    ->name('icu.spri');
Route::post('/icu/buat-spri/{id}', [SpriController::class, 'store'])
    ->name('icu.buat_spri');
Route::post('/icu/approve-spri/{id}', [SpriController::class, 'approve'])
    ->name('icu.approve_spri');

// ── Alokasi Bed ───────────────────────────────────────────────────────────
Route::get('/icu/alokasi-bed', [BedController::class, 'index'])
    ->name('icu.alokasi_bed.index');
Route::post('/icu/alokasi-bed/{id}', [BedController::class, 'alokasi'])
    ->name('icu.alokasi_bed');

// ── Pasien ICU (Transfer + Pulang) ────────────────────────────────────────
Route::get('/icu/pasien-icu', [PasienIcuController::class, 'index'])
    ->name('icu.pasien_icu');
Route::post('/icu/masuk-ruangan/{id}', [PasienIcuController::class, 'masukRuangan'])
    ->name('icu.masuk_ruangan');
Route::post('/icu/pulangkan/{id}', [PasienIcuController::class, 'pulangkan'])
    ->name('icu.pulangkan');
