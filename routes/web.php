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
use App\Http\Controllers\Icu\PulangController;

// ── Auth ──────────────────────────────────────────────────────────────────
Route::get('/login',  [LoginController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout',[LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ── Redirect root ─────────────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('icu.dashboard'));

// ── Semua route ICU: wajib login ──────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard — semua role bisa lihat
    Route::get('/dashboard-icu', [DashboardController::class, 'index'])
        ->name('icu.dashboard');

    // ── Denah Bed — semua role bisa lihat ────────────────────────────────
    Route::get('/icu/denah-bed', [DenahBedController::class, 'index'])
        ->name('icu.denah_bed');

    // ── Pasien ICU — semua role bisa lihat + ICU/admin bisa pulangkan ────
    Route::get('/icu/pasien-icu', [PasienIcuController::class, 'index'])
        ->name('icu.pasien_icu');
    Route::post('/icu/masuk-ruangan/{id}', [PasienIcuController::class, 'masukRuangan'])
        ->name('icu.masuk_ruangan')->middleware('role:petugas_icu');
    Route::post('/icu/pulangkan/{id}', [PasienIcuController::class, 'pulangkan'])
        ->name('icu.pulangkan')->middleware('role:petugas_icu');
    Route::post('/icu/pulangkan-external/{id}', [PasienIcuController::class, 'pulangkanExternal'])
        ->name('icu.pulangkan_external')->middleware('role:petugas_icu');
    Route::post('/icu/pulangkan-internal/{id}', [PasienIcuController::class, 'pulangkanInternal'])
        ->name('icu.pulangkan_internal')->middleware('role:petugas_icu');

    // ── Booking External ─────────────────────────────────────────────────
    Route::get('/icu/booking-external', [BookingExternalController::class, 'index'])
        ->name('icu.booking_external.index');
    // Admisi: buat booking, validasi, verifikasi, link pasien
    Route::post('/icu/booking-external', [BookingExternalController::class, 'store'])
        ->name('icu.booking_external.store')->middleware('role:admisi');
    Route::post('/icu/booking-external/{id}/validasi-admisi', [BookingExternalController::class, 'validasiAdmisi'])
        ->name('icu.booking_external.validasi_admisi')->middleware('role:admisi');
    Route::post('/icu/booking-external/{id}/link-pasien', [BookingExternalController::class, 'linkPasienTiba'])
        ->name('icu.booking_external.link_pasien')->middleware('role:admisi');
    Route::post('/icu/booking-external/{id}/verifikasi-bed', [BookingExternalController::class, 'verifikasiBed'])
        ->name('icu.booking_external.verifikasi_bed')->middleware('role:admisi');
    // ICU: konfirmasi bed, tolak, konfirmasi masuk
    Route::post('/icu/booking-external/{id}/konfirmasi-icu', [BookingExternalController::class, 'konfirmasiIcu'])
        ->name('icu.booking_external.konfirmasi_icu')->middleware('role:petugas_icu');
    Route::post('/icu/booking-external/{id}/tolak-icu', [BookingExternalController::class, 'tolakIcu'])
        ->name('icu.booking_external.tolak_icu')->middleware('role:petugas_icu');
    Route::post('/icu/booking-external/{id}/konfirmasi-masuk', [BookingExternalController::class, 'konfirmasiMasuk'])
        ->name('icu.booking_external.konfirmasi_masuk')->middleware('role:petugas_icu');
    Route::post('/icu/booking-external/{id}/pulangkan', [BookingExternalController::class, 'pulangkan'])
        ->name('icu.booking_external.pulangkan')->middleware('role:petugas_icu');

    // ── Surat Permintaan Rawat ICU Internal ──────────────────────────────
    Route::get('/icu/spri-internal', [SpriInternalController::class, 'index'])
        ->name('icu.spri_internal.index');
    // Petugas ruang: buat surat
    Route::post('/icu/spri-internal', [SpriInternalController::class, 'store'])
        ->name('icu.spri_internal.store')->middleware('role:petugas_ruang');
    // Admisi: approve/tolak, verifikasi akhir
    Route::post('/icu/spri-internal/{id}/approve-admisi', [SpriInternalController::class, 'approveAdmisi'])
        ->name('icu.spri_internal.approve_admisi')->middleware('role:admisi');
    Route::post('/icu/spri-internal/{id}/tolak-admisi', [SpriInternalController::class, 'tolakAdmisi'])
        ->name('icu.spri_internal.tolak_admisi')->middleware('role:admisi');
    Route::post('/icu/spri-internal/{id}/verifikasi-admisi', [SpriInternalController::class, 'verifikasiAdmisi'])
        ->name('icu.spri_internal.verifikasi_admisi')->middleware('role:admisi');
    // ICU: booking bed, tolak, konfirmasi masuk
    Route::post('/icu/spri-internal/{id}/booking-bed', [SpriInternalController::class, 'bookingBedIcu'])
        ->name('icu.spri_internal.booking_bed')->middleware('role:petugas_icu');
    Route::post('/icu/spri-internal/{id}/tolak-icu', [SpriInternalController::class, 'tolakIcu'])
        ->name('icu.spri_internal.tolak_icu')->middleware('role:petugas_icu');
    Route::post('/icu/spri-internal/{id}/konfirmasi-masuk', [SpriInternalController::class, 'konfirmasiMasuk'])
        ->name('icu.spri_internal.konfirmasi_masuk')->middleware('role:petugas_icu');
    Route::post('/icu/spri-internal/{id}/pulangkan', [SpriInternalController::class, 'pulangkan'])
        ->name('icu.spri_internal.pulangkan')->middleware('role:petugas_icu');

    // ── Route lama (legacy) ───────────────────────────────────────────────
    Route::get('/icu/pendaftaran', [PendaftaranController::class, 'index'])
        ->name('icu.pendaftaran');
    Route::post('/icu/tambah', [PendaftaranController::class, 'store'])
        ->name('icu.tambah')->middleware('role:admisi');

    Route::get('/icu/igd', [IgdController::class, 'index'])
        ->name('icu.igd');
    Route::post('/icu/kirim-igd/{id}', [IgdController::class, 'kirimIgd'])
        ->name('icu.kirim_igd')->middleware('role:admisi');

    Route::get('/icu/spri', [SpriController::class, 'index'])
        ->name('icu.spri');
    Route::post('/icu/buat-spri/{id}', [SpriController::class, 'store'])
        ->name('icu.buat_spri')->middleware('role:petugas_icu');
    Route::post('/icu/approve-spri/{id}', [SpriController::class, 'approve'])
        ->name('icu.approve_spri')->middleware('role:admisi');

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
