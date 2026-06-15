<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Icu\DashboardController;
use App\Http\Controllers\Icu\BookingExternalController;
use App\Http\Controllers\Icu\SpriInternalController;
use App\Http\Controllers\Icu\DenahBedController;
use App\Http\Controllers\Icu\Icd10Controller;
use App\Http\Controllers\Icu\MenuIcuController;
use App\Http\Controllers\Icu\MenuAdmisiController;
use App\Http\Controllers\Icu\MenuPetugasController;

// ── Auth — Lokal ──────────────────────────────────────────────────────────────
Route::get('/login',  [LoginController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');

// ── Auth — SSO Keycloak ───────────────────────────────────────────────────────
// Redirect ke Keycloak login page (aktif hanya jika Keycloak reachable)
Route::get('/auth/keycloak', [AuthController::class, 'redirectToKeycloak'])
    ->name('auth.keycloak')
    ->middleware('guest');

// Callback dari Keycloak setelah user login berhasil
Route::get('/auth/keycloak/callback', [AuthController::class, 'handleCallback'])
    ->name('auth.keycloak.callback');

// ── Logout — handle keycloak + lokal ─────────────────────────────────────────
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Redirect root ke dashboard
Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── ICU (wajib login) ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/dashboard-icu', [DashboardController::class, 'index'])->name('icu.dashboard');
    Route::get('/icu/denah-bed', [DenahBedController::class, 'index'])->name('icu.denah_bed');

    // ── Booking External ──────────────────────────────────────────────────────
    Route::get('/icu/booking-external', [BookingExternalController::class, 'index'])
        ->name('icu.booking_external.index');

    Route::post('/icu/booking-external', [BookingExternalController::class, 'store'])
        ->name('icu.booking_external.store')
        ->middleware('role:admisi');

    Route::post('/icu/booking-external/{id}/konfirmasi-icu', [BookingExternalController::class, 'konfirmasiIcu'])
        ->name('icu.booking_external.konfirmasi_icu')
        ->middleware('role:petugas_icu');

    Route::post('/icu/booking-external/{id}/tolak-icu', [BookingExternalController::class, 'tolakIcu'])
        ->name('icu.booking_external.tolak_icu')
        ->middleware('role:petugas_icu');

    Route::post('/icu/booking-external/{id}/verifikasi-admisi', [BookingExternalController::class, 'verifikasiAdmisi'])
        ->name('icu.booking_external.verifikasi_admisi')
        ->middleware('role:admisi');

    Route::get('/icu/booking-external/lookup-pasien', [BookingExternalController::class, 'lookupPasienExternal'])
        ->name('icu.booking_external.lookup_pasien');

    // ── SPRI Internal ─────────────────────────────────────────────────────────
    Route::get('/icu/spri-internal', [SpriInternalController::class, 'index'])
        ->name('icu.spri_internal.index');

    Route::post('/icu/spri-internal', [SpriInternalController::class, 'store'])
        ->name('icu.spri_internal.store')
        ->middleware('role:petugas_ruang');

    Route::get('/icu/spri-internal/lookup-pasien', [SpriInternalController::class, 'lookupPasien'])
        ->name('icu.spri_internal.lookup_pasien');

    Route::get('/icu/search-icd10', [Icd10Controller::class, 'search'])
        ->name('icu.search_icd10');

    // ── Menu ICU ──────────────────────────────────────────────────────────────
    Route::get('/icu/menu-icu', [MenuIcuController::class, 'index'])
        ->name('icu.menu_icu');

    Route::post('/icu/menu-icu/ext/{id}/konfirmasi', [MenuIcuController::class, 'konfirmasiExt'])
        ->name('icu.menu_icu.ext.konfirmasi')
        ->middleware('role:petugas_icu');

    Route::post('/icu/menu-icu/ext/{id}/tolak', [MenuIcuController::class, 'tolakExt'])
        ->name('icu.menu_icu.ext.tolak')
        ->middleware('role:petugas_icu');

    Route::post('/icu/menu-icu/int/{id}/verifikasi', [MenuIcuController::class, 'verifikasiInt'])
        ->name('icu.menu_icu.int.verifikasi')
        ->middleware('role:petugas_icu');

    Route::post('/icu/menu-icu/int/{id}/tolak', [MenuIcuController::class, 'tolakInt'])
        ->name('icu.menu_icu.int.tolak')
        ->middleware('role:petugas_icu');

    // ── Menu Admisi ───────────────────────────────────────────────────────────
    Route::get('/icu/menu-admision', [MenuAdmisiController::class, 'index'])
        ->name('icu.menu_admisi');

    Route::post('/icu/menu-admisi/int/{id}/approve', [MenuAdmisiController::class, 'approveInt'])
        ->name('icu.menu_admisi.int.approve')
        ->middleware('role:admisi');

    Route::post('/icu/menu-admisi/int/{id}/tolak', [MenuAdmisiController::class, 'tolakInt'])
        ->name('icu.menu_admisi.int.tolak')
        ->middleware('role:admisi');

    Route::post('/icu/menu-admisi/ext/{id}/verifikasi', [MenuAdmisiController::class, 'verifikasiExt'])
        ->name('icu.menu_admisi.ext.verifikasi')
        ->middleware('role:admisi');

    // Tambah Booking dari Menu Admisi
    Route::post('/icu/menu-admisi/booking', [MenuAdmisiController::class, 'storeBooking'])
        ->name('icu.menu_admisi.booking.store')
        ->middleware('role:admisi');

    // ── Menu Petugas Internal (petugas_ruang) ─────────────────────────────
    Route::get('/icu/menu-petugas', [\App\Http\Controllers\Icu\MenuPetugasController::class, 'index'])
        ->name('icu.menu_petugas');

    Route::post('/icu/menu-petugas/spri', [\App\Http\Controllers\Icu\MenuPetugasController::class, 'storeSpri'])
        ->name('icu.menu_petugas.spri.store')
        ->middleware('role:petugas_ruang');

    Route::post('/icu/spri-internal/{id}/approve-admisi', [SpriInternalController::class, 'approveAdmisi'])
        ->name('icu.spri_internal.approve_admisi')
        ->middleware('role:admisi');

    Route::post('/icu/spri-internal/{id}/tolak-admisi', [SpriInternalController::class, 'tolakAdmisi'])
        ->name('icu.spri_internal.tolak_admisi')
        ->middleware('role:admisi');

    Route::post('/icu/spri-internal/{id}/verifikasi-bed', [SpriInternalController::class, 'verifikasiBedIcu'])
        ->name('icu.spri_internal.verifikasi_bed')
        ->middleware('role:petugas_icu');

    Route::post('/icu/spri-internal/{id}/tolak-icu', [SpriInternalController::class, 'tolakIcu'])
        ->name('icu.spri_internal.tolak_icu')
        ->middleware('role:petugas_icu');

    // ── Settings (admin only) ─────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings/users', [\App\Http\Controllers\UserController::class, 'index'])
            ->name('settings.users');
        Route::post('/settings/users', [\App\Http\Controllers\UserController::class, 'store'])
            ->name('settings.users.store');
        Route::put('/settings/users/{id}', [\App\Http\Controllers\UserController::class, 'update'])
            ->name('settings.users.update');
        Route::post('/settings/users/{id}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])
            ->name('settings.users.reset_password');
        Route::delete('/settings/users/{id}', [\App\Http\Controllers\UserController::class, 'destroy'])
            ->name('settings.users.destroy');
        Route::get('/settings/roles', [\App\Http\Controllers\RolePermissionController::class, 'index'])
            ->name('settings.roles');
        Route::post('/settings/roles/user/{id}', [\App\Http\Controllers\RolePermissionController::class, 'updateUserRole'])
            ->name('settings.roles.update_user');
    });
});
