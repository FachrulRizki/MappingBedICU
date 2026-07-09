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
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolePermissionController;

use App\Http\Controllers\Icu\MonitorController;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',                  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login',                 [LoginController::class, 'login']);
    Route::get('/auth/keycloak',          [AuthController::class,  'redirectToKeycloak'])->name('auth.keycloak');
    Route::get('/auth/keycloak/callback', [AuthController::class,  'handleCallback'])->name('auth.keycloak.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Monitor TV (tanpa login — untuk layar TV di ruang ICU) ───────────────────
Route::get('/monitor',      [MonitorController::class, 'index'])->name('icu.monitor');
Route::get('/monitor/data', [MonitorController::class, 'data'])->name('icu.monitor.data');

Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── ICU (wajib login) ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard & Denah Bed
    Route::get('/dashboard-icu', [DashboardController::class, 'index'])->name('icu.dashboard');
    Route::get('/icu/denah-bed', [DenahBedController::class,  'index'])->name('icu.denah_bed');

    // ICD10 search (semua role)
    Route::get('/icu/search-icd10', [Icd10Controller::class, 'search'])->name('icu.search_icd10');

    // ── AJAX Lookup (semua role) ───────────────────────────────────────────
    Route::get('/icu/lookup-pasien',          [MenuPetugasController::class, 'lookupPasien'])->name('icu.spri_internal.lookup_pasien');
    Route::get('/icu/lookup-pasien-external', [MenuAdmisiController::class,  'lookupPasienExternal'])->name('icu.booking_external.lookup_pasien');

    // ── MENU ICU ──────────────────────────────────────────────────────────
    Route::get('/icu/menu-icu', [MenuIcuController::class, 'index'])->name('icu.menu_icu');

    // Sebelumnya: role:petugas_icu → sekarang per-permission
    Route::post('/icu/menu-icu/ext/{id}/konfirmasi',   [MenuIcuController::class, 'konfirmasiExt'])->name('icu.menu_icu.ext.konfirmasi')->middleware('permission:booking_ext:konfirmasi_bed');
    Route::post('/icu/menu-icu/ext/{id}/tolak',        [MenuIcuController::class, 'tolakExt'])->name('icu.menu_icu.ext.tolak')->middleware('permission:booking_ext:tolak');
    Route::post('/icu/menu-icu/ext/{id}/waiting-list', [MenuIcuController::class, 'waitingListExt'])->name('icu.menu_icu.ext.waiting_list')->middleware('permission:booking_ext:waiting_list');
    Route::post('/icu/menu-icu/int/{id}/verifikasi',   [MenuIcuController::class, 'verifikasiInt'])->name('icu.menu_icu.int.verifikasi')->middleware('permission:booking_int:verifikasi_bed');
    Route::post('/icu/menu-icu/int/{id}/tolak',        [MenuIcuController::class, 'tolakInt'])->name('icu.menu_icu.int.tolak')->middleware('permission:booking_int:tolak_icu');
    Route::post('/icu/menu-icu/int/{id}/waiting-list', [MenuIcuController::class, 'waitingListInt'])->name('icu.menu_icu.int.waiting_list')->middleware('permission:booking_int:waiting_list');

    // ── MENU ADMISI ───────────────────────────────────────────────────────
    Route::get('/icu/menu-admision', [MenuAdmisiController::class, 'index'])->name('icu.menu_admisi');

    // Sebelumnya: role:admisi → sekarang per-permission
    Route::post('/icu/menu-admisi/booking',             [MenuAdmisiController::class, 'storeBooking'])->name('icu.menu_admisi.booking.store')->middleware('permission:booking_ext:create');
    Route::post('/icu/menu-admisi/int/{id}/approve',    [MenuAdmisiController::class, 'approveInt'])->name('icu.menu_admisi.int.approve')->middleware('permission:booking_int:approve');
    Route::post('/icu/menu-admisi/int/{id}/tolak',      [MenuAdmisiController::class, 'tolakInt'])->name('icu.menu_admisi.int.tolak')->middleware('permission:booking_int:tolak_admisi');
    Route::post('/icu/menu-admisi/ext/{id}/verifikasi', [MenuAdmisiController::class, 'verifikasiExt'])->name('icu.menu_admisi.ext.verifikasi')->middleware('permission:booking_ext:verifikasi');

    // ── MENU PETUGAS RUANG ────────────────────────────────────────────────
    Route::get('/icu/menu-petugas',              [MenuPetugasController::class, 'index'])->name('icu.menu_petugas');
    Route::get('/icu/menu-petugas/pasien-aktif', [MenuPetugasController::class, 'pasienAktifSearch'])->name('icu.menu_petugas.pasien_aktif');
    Route::get('/icu/menu-petugas/lookup',       [MenuPetugasController::class, 'lookupPasien'])->name('icu.menu_petugas.lookup');

    // Sebelumnya: role:petugas_ruang → sekarang permission
    Route::post('/icu/menu-petugas/spri', [MenuPetugasController::class, 'storeSpri'])->name('icu.menu_petugas.spri.store')->middleware('permission:booking_int:create');

    // ── SETTINGS ──────────────────────────────────────────────────────────
    Route::middleware('permission:settings_users:view')->group(function () {
        Route::get('/settings/users',      [UserController::class, 'index'])->name('settings.users');
        Route::put('/settings/users/{id}', [UserController::class, 'update'])->name('settings.users.update')->middleware('permission:settings_users:edit');
    });

    Route::middleware('permission:settings_roles:view')->group(function () {
        Route::get('/settings/roles', [RolePermissionController::class, 'index'])->name('settings.roles');
    });

    // ── LOG AKTIVITAS ─────────────────────────────────────────────────────
    Route::get('/settings/activity-logs', [ActivityLogController::class, 'index'])->name('settings.activity_logs')->middleware('permission:activity_log:view');
});