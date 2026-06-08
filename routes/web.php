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
use App\Http\Controllers\Icu\BookingExternalController;
use App\Http\Controllers\Icu\SpriInternalController;
use App\Http\Controllers\Icu\DenahBedController;

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
Route::post('/icu/pulangkan-external/{id}', [PasienIcuController::class, 'pulangkanExternal'])
    ->name('icu.pulangkan_external');
Route::post('/icu/pulangkan-internal/{id}', [PasienIcuController::class, 'pulangkanInternal'])
    ->name('icu.pulangkan_internal');

// ── Booking External (Pasien dari luar RS) ────────────────────────────────
Route::get('/icu/booking-external', [BookingExternalController::class, 'index'])
    ->name('icu.booking_external.index');
Route::post('/icu/booking-external', [BookingExternalController::class, 'store'])
    ->name('icu.booking_external.store');
Route::post('/icu/booking-external/{id}/konfirmasi-icu', [BookingExternalController::class, 'konfirmasiIcu'])
    ->name('icu.booking_external.konfirmasi_icu');
Route::post('/icu/booking-external/{id}/tolak-icu', [BookingExternalController::class, 'tolakIcu'])
    ->name('icu.booking_external.tolak_icu');
Route::post('/icu/booking-external/{id}/validasi-admisi', [BookingExternalController::class, 'validasiAdmisi'])
    ->name('icu.booking_external.validasi_admisi');
Route::post('/icu/booking-external/{id}/link-pasien', [BookingExternalController::class, 'linkPasienTiba'])
    ->name('icu.booking_external.link_pasien');
Route::post('/icu/booking-external/{id}/verifikasi-bed', [BookingExternalController::class, 'verifikasiBed'])
    ->name('icu.booking_external.verifikasi_bed');
Route::post('/icu/booking-external/{id}/konfirmasi-masuk', [BookingExternalController::class, 'konfirmasiMasuk'])
    ->name('icu.booking_external.konfirmasi_masuk');

// ── Surat Permintaan Rawat ICU Internal (Pasien sudah di RS) ─────────────
Route::get('/icu/spri-internal', [SpriInternalController::class, 'index'])
    ->name('icu.spri_internal.index');
Route::post('/icu/spri-internal', [SpriInternalController::class, 'store'])
    ->name('icu.spri_internal.store');
Route::post('/icu/spri-internal/{id}/approve-admisi', [SpriInternalController::class, 'approveAdmisi'])
    ->name('icu.spri_internal.approve_admisi');
Route::post('/icu/spri-internal/{id}/tolak-admisi', [SpriInternalController::class, 'tolakAdmisi'])
    ->name('icu.spri_internal.tolak_admisi');
Route::post('/icu/spri-internal/{id}/booking-bed', [SpriInternalController::class, 'bookingBedIcu'])
    ->name('icu.spri_internal.booking_bed');
Route::post('/icu/spri-internal/{id}/tolak-icu', [SpriInternalController::class, 'tolakIcu'])
    ->name('icu.spri_internal.tolak_icu');
Route::post('/icu/spri-internal/{id}/verifikasi-admisi', [SpriInternalController::class, 'verifikasiAdmisi'])
    ->name('icu.spri_internal.verifikasi_admisi');
Route::post('/icu/spri-internal/{id}/konfirmasi-masuk', [SpriInternalController::class, 'konfirmasiMasuk'])
    ->name('icu.spri_internal.konfirmasi_masuk');

// ── Denah Bed ICU ─────────────────────────────────────────────────────────
Route::get('/icu/denah-bed', [DenahBedController::class, 'index'])
    ->name('icu.denah_bed');

// ── Pulang dari jalur baru ────────────────────────────────────────────────
Route::post('/icu/booking-external/{id}/pulangkan', [BookingExternalController::class, 'pulangkan'])
    ->name('icu.booking_external.pulangkan');
Route::post('/icu/spri-internal/{id}/pulangkan', [SpriInternalController::class, 'pulangkan'])
    ->name('icu.spri_internal.pulangkan');
