<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Icu\DashboardController;
use App\Http\Controllers\Icu\PendaftaranController;
use App\Http\Controllers\Icu\IgdController;
use App\Http\Controllers\Icu\SpriController;
use App\Http\Controllers\Icu\BedController;
use App\Http\Controllers\Icu\PasienIcuController;
use App\Http\Controllers\Icu\BookingExternalController;
use App\Http\Controllers\Icu\SpriInternalController;
use App\Http\Controllers\Icu\DenahBedController;

// ── Auth ──────────────────────────────────────────────────────────────────
Route::get('/login',  [LoginController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout',[LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ── Redirect root ─────────────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── Semua route ICU: wajib login ──────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────
    Route::get('/dashboard-icu', [DashboardController::class, 'index'])
        ->name('icu.dashboard');

    // ── Denah Bed ─────────────────────────────────────────────────────────
    Route::get('/icu/denah-bed', [DenahBedController::class, 'index'])
        ->name('icu.denah_bed');

    // ── Pasien ICU ────────────────────────────────────────────────────────
    Route::get('/icu/pasien-icu', [PasienIcuController::class, 'index'])
        ->name('icu.pasien_icu');
    Route::post('/icu/masuk-ruangan/{id}', [PasienIcuController::class, 'masukRuangan'])
        ->name('icu.masuk_ruangan')->middleware('role:petugas_icu');
    Route::post('/icu/pulangkan/{id}', [PasienIcuController::class, 'pulangkan'])
        ->name('icu.pulangkan')->middleware('role:petugas_icu');

    // ══════════════════════════════════════════════════════════════════════
    // BOOKING EXTERNAL
    // Alur: Admisi buat request (+ jaminan) → ICU pilih bed → ICU konfirmasi masuk
    // ══════════════════════════════════════════════════════════════════════
    Route::get('/icu/booking-external', [BookingExternalController::class, 'index'])
        ->name('icu.booking_external.index');

    // Admisi — buat booking + form jaminan (langsung pending_icu)
    Route::post('/icu/booking-external', [BookingExternalController::class, 'store'])
        ->name('icu.booking_external.store')->middleware('role:admisi');

    // Petugas ICU — pilih bed
    Route::post('/icu/booking-external/{id}/konfirmasi-icu', [BookingExternalController::class, 'konfirmasiIcu'])
        ->name('icu.booking_external.konfirmasi_icu')->middleware('role:petugas_icu');

    // Petugas ICU — belum ada bed, catat & tetap waiting
    Route::post('/icu/booking-external/{id}/catat-tanpa-bed', [BookingExternalController::class, 'catatTanpaBed'])
        ->name('icu.booking_external.catat_tanpa_bed')->middleware('role:petugas_icu');

    // Petugas ICU — tolak
    Route::post('/icu/booking-external/{id}/tolak-icu', [BookingExternalController::class, 'tolakIcu'])
        ->name('icu.booking_external.tolak_icu')->middleware('role:petugas_icu');

    // Petugas ICU — konfirmasi pasien masuk ruangan → langsung di_icu
    Route::post('/icu/booking-external/{id}/konfirmasi-masuk', [BookingExternalController::class, 'konfirmasiMasuk'])
        ->name('icu.booking_external.konfirmasi_masuk')->middleware('role:petugas_icu');

    // Petugas ICU — pulangkan
    Route::post('/icu/booking-external/{id}/pulangkan', [BookingExternalController::class, 'pulangkan'])
        ->name('icu.booking_external.pulangkan')->middleware('role:petugas_icu');

    // ══════════════════════════════════════════════════════════════════════
    // SURAT PERMINTAAN RAWAT ICU (INTERNAL)
    // Alur: Petugas Ruang buat → Admisi approve+catat → ICU pilih bed → ICU konfirmasi masuk
    // ══════════════════════════════════════════════════════════════════════
    Route::get('/icu/spri-internal', [SpriInternalController::class, 'index'])
        ->name('icu.spri_internal.index');

    // Petugas Ruang — buat surat permintaan
    Route::post('/icu/spri-internal', [SpriInternalController::class, 'store'])
        ->name('icu.spri_internal.store')->middleware('role:petugas_ruang');

    // Lookup pasien (AJAX) — semua role bisa akses
    Route::get('/icu/spri-internal/lookup-pasien', [SpriInternalController::class, 'lookupPasien'])
        ->name('icu.spri_internal.lookup_pasien');

    // Admisi — approve + isi catatan jaminan (TIDAK menentukan bed)
    Route::post('/icu/spri-internal/{id}/approve-admisi', [SpriInternalController::class, 'approveAdmisi'])
        ->name('icu.spri_internal.approve_admisi')->middleware('role:admisi');

    // Admisi — tolak
    Route::post('/icu/spri-internal/{id}/tolak-admisi', [SpriInternalController::class, 'tolakAdmisi'])
        ->name('icu.spri_internal.tolak_admisi')->middleware('role:admisi');

    // Petugas ICU — pilih bed
    Route::post('/icu/spri-internal/{id}/booking-bed', [SpriInternalController::class, 'bookingBedIcu'])
        ->name('icu.spri_internal.booking_bed')->middleware('role:petugas_icu');

    // Petugas ICU — belum ada bed, catat & tetap waiting
    Route::post('/icu/spri-internal/{id}/catat-tanpa-bed', [SpriInternalController::class, 'catatTanpaBed'])
        ->name('icu.spri_internal.catat_tanpa_bed')->middleware('role:petugas_icu');

    // Petugas ICU — tolak
    Route::post('/icu/spri-internal/{id}/tolak-icu', [SpriInternalController::class, 'tolakIcu'])
        ->name('icu.spri_internal.tolak_icu')->middleware('role:petugas_icu');

    // Petugas ICU — konfirmasi pasien masuk ruangan → langsung di_icu
    Route::post('/icu/spri-internal/{id}/konfirmasi-masuk', [SpriInternalController::class, 'konfirmasiMasuk'])
        ->name('icu.spri_internal.konfirmasi_masuk')->middleware('role:petugas_icu');

    // Petugas ICU — pulangkan
    Route::post('/icu/spri-internal/{id}/pulangkan', [SpriInternalController::class, 'pulangkan'])
        ->name('icu.spri_internal.pulangkan')->middleware('role:petugas_icu');

    // ── Alokasi Bed (legacy dashboard) ───────────────────────────────────
    Route::get('/icu/alokasi-bed', [BedController::class, 'index'])
        ->name('icu.alokasi_bed.index');
    Route::post('/icu/alokasi-bed/{id}', [BedController::class, 'alokasi'])
        ->name('icu.alokasi_bed')->middleware('role:petugas_icu');

    // ── Settings: User Management (admin only) ───────────────────────────
    Route::get('/settings/users', [\App\Http\Controllers\UserController::class, 'index'])
        ->name('settings.users')->middleware('role:admin');
    Route::post('/settings/users', [\App\Http\Controllers\UserController::class, 'store'])
        ->name('settings.users.store')->middleware('role:admin');
    Route::put('/settings/users/{id}', [\App\Http\Controllers\UserController::class, 'update'])
        ->name('settings.users.update')->middleware('role:admin');
    Route::post('/settings/users/{id}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])
        ->name('settings.users.reset_password')->middleware('role:admin');
    Route::delete('/settings/users/{id}', [\App\Http\Controllers\UserController::class, 'destroy'])
        ->name('settings.users.destroy')->middleware('role:admin');

    // ── Settings: Role & Permission (admin only) ─────────────────────────
    Route::get('/settings/roles', [\App\Http\Controllers\RolePermissionController::class, 'index'])
        ->name('settings.roles')->middleware('role:admin');
    Route::post('/settings/roles/user/{id}', [\App\Http\Controllers\RolePermissionController::class, 'updateUserRole'])
        ->name('settings.roles.update_user')->middleware('role:admin');
});
