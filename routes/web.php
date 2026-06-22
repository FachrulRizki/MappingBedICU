<?php

use Illuminate\Support\Facades\Route;
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

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',                 [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login',                [LoginController::class, 'login']);
    Route::get('/auth/keycloak',         [AuthController::class,  'redirectToKeycloak'])->name('auth.keycloak');
    Route::get('/auth/keycloak/callback',[AuthController::class,  'handleCallback'])->name('auth.keycloak.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── ICU (wajib login) ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard & Denah Bed
    Route::get('/dashboard-icu', [DashboardController::class, 'index'])->name('icu.dashboard');
    Route::get('/icu/denah-bed', [DenahBedController::class,  'index'])->name('icu.denah_bed');

    // ICD10 search (semua role)
    Route::get('/icu/search-icd10', [Icd10Controller::class, 'search'])->name('icu.search_icd10');

    // ── AJAX Lookup (semua role) ───────────────────────────────────────────
    // Lookup pasien untuk form SPRI (MenuPetugas)
    Route::get('/icu/lookup-pasien',          [MenuPetugasController::class, 'lookupPasien'])->name('icu.spri_internal.lookup_pasien');
    // Lookup pasien untuk verifikasi booking external (MenuAdmisi)
    Route::get('/icu/lookup-pasien-external', [MenuAdmisiController::class,  'lookupPasienExternal'])->name('icu.booking_external.lookup_pasien');

    // ── MENU ICU ──────────────────────────────────────────────────────────
    Route::get('/icu/menu-icu', [MenuIcuController::class, 'index'])->name('icu.menu_icu');

    Route::middleware('role:petugas_icu')->group(function () {
        Route::post('/icu/menu-icu/ext/{id}/konfirmasi', [MenuIcuController::class, 'konfirmasiExt'])->name('icu.menu_icu.ext.konfirmasi');
        Route::post('/icu/menu-icu/ext/{id}/tolak',      [MenuIcuController::class, 'tolakExt'])->name('icu.menu_icu.ext.tolak');
        Route::post('/icu/menu-icu/int/{id}/verifikasi', [MenuIcuController::class, 'verifikasiInt'])->name('icu.menu_icu.int.verifikasi');
        Route::post('/icu/menu-icu/int/{id}/tolak',      [MenuIcuController::class, 'tolakInt'])->name('icu.menu_icu.int.tolak');
    });

    // ── MENU ADMISI ───────────────────────────────────────────────────────
    Route::get('/icu/menu-admision', [MenuAdmisiController::class, 'index'])->name('icu.menu_admisi');

    Route::middleware('role:admisi')->group(function () {
        Route::post('/icu/menu-admisi/booking',             [MenuAdmisiController::class, 'storeBooking'])->name('icu.menu_admisi.booking.store');
        Route::post('/icu/menu-admisi/int/{id}/approve',    [MenuAdmisiController::class, 'approveInt'])->name('icu.menu_admisi.int.approve');
        Route::post('/icu/menu-admisi/int/{id}/tolak',      [MenuAdmisiController::class, 'tolakInt'])->name('icu.menu_admisi.int.tolak');
        Route::post('/icu/menu-admisi/ext/{id}/verifikasi', [MenuAdmisiController::class, 'verifikasiExt'])->name('icu.menu_admisi.ext.verifikasi');
    });

    // ── MENU PETUGAS RUANG ────────────────────────────────────────────────
    Route::get('/icu/menu-petugas',              [MenuPetugasController::class, 'index'])->name('icu.menu_petugas');
    Route::get('/icu/menu-petugas/pasien-aktif', [MenuPetugasController::class, 'pasienAktifSearch'])->name('icu.menu_petugas.pasien_aktif');
    Route::get('/icu/menu-petugas/lookup',       [MenuPetugasController::class, 'lookupPasien'])->name('icu.menu_petugas.lookup');
    Route::post('/icu/menu-petugas/spri',        [MenuPetugasController::class, 'storeSpri'])->name('icu.menu_petugas.spri.store')->middleware('role:petugas_ruang');

    // ── SETTINGS (admin only) ─────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings/users',                      [UserController::class,          'index'])->name('settings.users');
        Route::post('/settings/users',                     [UserController::class,          'store'])->name('settings.users.store');
        Route::put('/settings/users/{id}',                 [UserController::class,          'update'])->name('settings.users.update');
        Route::post('/settings/users/{id}/reset-password', [UserController::class,          'resetPassword'])->name('settings.users.reset_password');
        Route::delete('/settings/users/{id}',              [UserController::class,          'destroy'])->name('settings.users.destroy');
        Route::get('/settings/roles',                      [RolePermissionController::class,'index'])->name('settings.roles');
        Route::post('/settings/roles/user/{id}',           [RolePermissionController::class,'updateUserRole'])->name('settings.roles.update_user');

        // ── LOG AKTIVITAS (admin only) ────────────────────────────────────
        Route::get('/settings/activity-logs', [ActivityLogController::class, 'index'])->name('settings.activity_logs');
    });
});
