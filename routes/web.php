<?php

use App\Http\Controllers\KesController;
use App\Http\Controllers\MahkamahController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PengantaraanController;
use App\Http\Controllers\PeguamController;
use App\Http\Controllers\SystemAuthController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

// Public landing.
Route::get('/', fn () => view('welcome'))->name('home');

// ---- Guest: login + password reset (plain Laravel, no Filament) ----
Route::middleware('guest')->group(function () {
    Route::get('/system/login', [SystemAuthController::class, 'showLogin'])->name('system.login');
    Route::post('/system/login', [SystemAuthController::class, 'attempt'])->name('system.login.attempt');

    Route::get('/password/forgot', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/password/forgot', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'update'])->name('password.update');
});

// ---- Authenticated (any role) ----
Route::middleware('auth')->group(function () {
    Route::post('/logout', [SystemAuthController::class, 'logout'])->name('system.logout');
});

// ---- Staff area: rekod-kes + panel admin (admin / pengarah / koordinator / pegawai) ----
Route::middleware(['auth', 'role:admin,pengarah,koordinator,pegawai'])->group(function () {
    Route::get('/system', [SystemController::class, 'utama'])->name('system.utama');

    // Rekod kes (Case backbone + permohonan CRUD)
    Route::get('/kes', [KesController::class, 'index'])->name('kes.index');
    Route::get('/kes/create', [KesController::class, 'create'])->name('kes.create');
    Route::post('/kes', [KesController::class, 'store'])->name('kes.store');
    Route::get('/kes/{kes}/edit', [KesController::class, 'edit'])->name('kes.edit')->whereNumber('kes');
    Route::put('/kes/{kes}', [KesController::class, 'update'])->name('kes.update')->whereNumber('kes');
    Route::get('/kes/{kes}', [KesController::class, 'show'])->name('kes.show')->whereNumber('kes');

    // Pengantaraan (mediation) — section edit + hearing reschedule
    Route::get('/kes/{kes}/pengantaraan', [PengantaraanController::class, 'edit'])->name('pengantaraan.edit')->whereNumber('kes');
    Route::put('/kes/{kes}/pengantaraan', [PengantaraanController::class, 'update'])->name('pengantaraan.update')->whereNumber('kes');
    Route::post('/kes/{kes}/sidang', [PengantaraanController::class, 'tangguhSidang'])->name('sidang.tangguh')->whereNumber('kes');

    // Kes Mahkamah (court) — section edit + laporan_kes child records
    Route::get('/kes/{kes}/mahkamah', [MahkamahController::class, 'edit'])->name('mahkamah.edit')->whereNumber('kes');
    Route::put('/kes/{kes}/mahkamah', [MahkamahController::class, 'update'])->name('mahkamah.update')->whereNumber('kes');
    Route::post('/kes/{kes}/laporan', [MahkamahController::class, 'storeLaporan'])->name('laporan.store')->whereNumber('kes');
    Route::delete('/kes/{kes}/laporan/{laporan}', [MahkamahController::class, 'destroyLaporan'])->name('laporan.destroy')->whereNumber('kes')->whereNumber('laporan');
});

// ---- Lawyer area: panel lawyers (peguam) ----
Route::middleware(['auth', 'role:peguam'])->prefix('peguam')->group(function () {
    Route::get('/', [PeguamController::class, 'dashboard'])->name('peguam.dashboard');
});
