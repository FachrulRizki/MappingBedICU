<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Icu\ActivityLogController;
use App\Http\Controllers\Icu\DashboardController;
use App\Http\Controllers\Icu\DenahBedController;
use App\Http\Controllers\Icu\Icd10Controller;
use App\Http\Controllers\Icu\MenuIcuController;
use App\Http\Controllers\Icu\MenuAdmisiController;
use App\Http\Controllers\Icu\MenuPetugasController;
use App\Http\Controllers\Icu\MonitorController;
use App\Http\Controllers\Icu\MenuYanmedController;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',                  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login',                 [LoginController::class, 'login']);
    Route::get('/auth/keycloak',          [AuthController::class,  'redirectToKeycloak'])->name('auth.keycloak');
    Route::get('/auth/keycloak/callback', [AuthController::class,  'handleCallback'])->name('auth.keycloak.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Monitor TV (tanpa login) ──────────────────────────────────────────────────
Route::get('/monitor',      [MonitorController::class, 'index'])->name('icu.monitor');
Route::get('/monitor/data', [MonitorController::class, 'data'])->name('icu.monitor.data');

Route::get('/', fn () => redirect()->route('icu.dashboard'));

// ── ICU (wajib login) ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/dashboard-icu', [DashboardController::class, 'index'])->name('icu.dashboard');
    Route::get('/icu/denah-bed', [DenahBedController::class,  'index'])->name('icu.denah_bed');
    Route::get('/icu/search-icd10', [Icd10Controller::class,  'search'])->name('icu.search_icd10');

    // AJAX Lookup
    Route::get('/icu/lookup-pasien',          [MenuPetugasController::class, 'lookupPasien'])->name('icu.spri_internal.lookup_pasien');
    Route::get('/icu/lookup-pasien-external', [MenuAdmisiController::class,  'lookupPasienExternal'])->name('icu.booking_external.lookup_pasien');

    // ── Menu ICU — bisa diakses jika punya salah satu permission view ────────
    Route::get('/icu/menu-icu', [MenuIcuController::class, 'index'])
        ->name('icu.menu_icu')
        ->middleware('permission:booking_ext:view,booking_int:view');
    Route::post('/icu/menu-icu/ext/{id}/konfirmasi',   [MenuIcuController::class, 'konfirmasiExt'])->name('icu.menu_icu.ext.konfirmasi')->middleware('permission:booking_ext:konfirmasi_bed');
    Route::post('/icu/menu-icu/ext/{id}/tolak',        [MenuIcuController::class, 'tolakExt'])->name('icu.menu_icu.ext.tolak')->middleware('permission:booking_ext:tolak');
    Route::post('/icu/menu-icu/ext/{id}/waiting-list', [MenuIcuController::class, 'waitingListExt'])->name('icu.menu_icu.ext.waiting_list')->middleware('permission:booking_ext:waiting_list');
    Route::post('/icu/menu-icu/int/{id}/verifikasi',   [MenuIcuController::class, 'verifikasiInt'])->name('icu.menu_icu.int.verifikasi')->middleware('permission:booking_int:verifikasi_bed');
    Route::post('/icu/menu-icu/int/{id}/tolak',        [MenuIcuController::class, 'tolakInt'])->name('icu.menu_icu.int.tolak')->middleware('permission:booking_int:tolak_icu');
    Route::post('/icu/menu-icu/int/{id}/waiting-list', [MenuIcuController::class, 'waitingListInt'])->name('icu.menu_icu.int.waiting_list')->middleware('permission:booking_int:waiting_list');
    Route::post('/icu/menu-icu/ext/{id}/pindah-bed',   [MenuIcuController::class, 'pindahBedExt'])->name('icu.menu_icu.ext.pindah_bed')->middleware('permission:booking_ext:konfirmasi_bed');
    Route::post('/icu/menu-icu/int/{id}/pindah-bed',   [MenuIcuController::class, 'pindahBedInt'])->name('icu.menu_icu.int.pindah_bed')->middleware('permission:booking_int:verifikasi_bed');

    // ── Menu Admisi ───────────────────────────────────────────────────────────
    Route::get('/icu/menu-admision', [MenuAdmisiController::class, 'index'])->name('icu.menu_admisi');
    Route::post('/icu/menu-admisi/booking',             [MenuAdmisiController::class, 'storeBooking'])->name('icu.menu_admisi.booking.store')->middleware('permission:booking_ext:create');
    Route::put('/icu/menu-admisi/booking/{id}',         [MenuAdmisiController::class, 'updateBooking'])->name('icu.menu_admisi.booking.update')->middleware('permission:booking_ext:create');
    Route::post('/icu/menu-admisi/booking/{id}/batal',  [MenuAdmisiController::class, 'batalBooking'])->name('icu.menu_admisi.booking.batal')->middleware('permission:booking_ext:create');
    Route::delete('/icu/menu-admisi/booking/{id}',      [MenuAdmisiController::class, 'deleteBooking'])->name('icu.menu_admisi.booking.delete')->middleware('permission:booking_ext:create');
    Route::post('/icu/menu-admisi/int/{id}/approve',    [MenuAdmisiController::class, 'approveInt'])->name('icu.menu_admisi.int.approve')->middleware('permission:booking_int:approve');
    Route::post('/icu/menu-admisi/int/{id}/tolak',      [MenuAdmisiController::class, 'tolakInt'])->name('icu.menu_admisi.int.tolak')->middleware('permission:booking_int:tolak_admisi');
    Route::post('/icu/menu-admisi/ext/{id}/verifikasi', [MenuAdmisiController::class, 'verifikasiExt'])->name('icu.menu_admisi.ext.verifikasi')->middleware('permission:booking_ext:verifikasi_pasien');

    // ── Menu Petugas Ruang ────────────────────────────────────────────────────
    Route::get('/icu/menu-petugas',              [MenuPetugasController::class, 'index'])->name('icu.menu_petugas');
    Route::get('/icu/menu-petugas/pasien-aktif', [MenuPetugasController::class, 'pasienAktifSearch'])->name('icu.menu_petugas.pasien_aktif');
    Route::get('/icu/menu-petugas/lookup',       [MenuPetugasController::class, 'lookupPasien'])->name('icu.menu_petugas.lookup');
    Route::post('/icu/menu-petugas/spri',        [MenuPetugasController::class, 'storeSpri'])->name('icu.menu_petugas.spri.store')->middleware('permission:booking_int:create');
    Route::put('/icu/menu-petugas/spri/{id}',    [MenuPetugasController::class, 'updateSpri'])->name('icu.menu_petugas.spri.update')->middleware('permission:booking_int:create');
    Route::post('/icu/menu-petugas/spri/{id}/batal', [MenuPetugasController::class, 'batalSpri'])->name('icu.menu_petugas.spri.batal')->middleware('permission:booking_int:create');
    Route::delete('/icu/menu-petugas/spri/{id}', [MenuPetugasController::class, 'deleteSpri'])->name('icu.menu_petugas.spri.delete')->middleware('permission:booking_int:create');

    // ── Menu Yanmed ───────────────────────────────────────────────────────────
    Route::get('/icu/menu-yanmed', [MenuYanmedController::class, 'index'])
        ->name('icu.menu_yanmed')
        ->middleware('permission:yanmed:view');

    // ── Settings ──────────────────────────────────────────────────────────────
    // Kelola User & Role dikelola penuh oleh Keycloak SSO — tidak ada di aplikasi.
    Route::get('/settings/activity-logs', [ActivityLogController::class, 'index'])
        ->name('settings.activity_logs')
        ->middleware('permission:activity_log:view');
});
