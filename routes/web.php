<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IcuDashboardController;

// Redirect root ke dashboard
Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── Dashboard ────────────────────────────────────────────────────────────
Route::get('/dashboard-icu', [IcuDashboardController::class, 'index'])
    ->name('icu.dashboard');

// ── Step 1: Daftar Pasien Baru ───────────────────────────────────────────
Route::post('/icu/tambah', [IcuDashboardController::class, 'tambahPasien'])
    ->name('icu.tambah');

// ── Step 2: Kirim ke IGD ─────────────────────────────────────────────────
Route::post('/icu/kirim-igd/{id}', [IcuDashboardController::class, 'kirimIgd'])
    ->name('icu.kirim_igd');

// ── Step 3: Buat SPRI + Kebutuhan Bed ───────────────────────────────────
Route::post('/icu/buat-spri/{id}', [IcuDashboardController::class, 'buatSpri'])
    ->name('icu.buat_spri');

// ── Step 4: Approve SPRI ────────────────────────────────────────────────
Route::post('/icu/approve-spri/{id}', [IcuDashboardController::class, 'approveSpri'])
    ->name('icu.approve_spri');

// ── Step 5: Alokasi Bed (Dapat Kamar) ───────────────────────────────────
Route::post('/icu/alokasi-bed/{id}', [IcuDashboardController::class, 'alokasiBed'])
    ->name('icu.alokasi_bed');

// ── Step 6: Masuk Ruangan (Transfer ICU) ────────────────────────────────
Route::post('/icu/masuk-ruangan/{id}', [IcuDashboardController::class, 'masukRuangan'])
    ->name('icu.masuk_ruangan');

// ── Step 7: Pulangkan Pasien ─────────────────────────────────────────────
Route::post('/icu/pulangkan/{id}', [IcuDashboardController::class, 'pulangkanPasien'])
    ->name('icu.pulangkan');
