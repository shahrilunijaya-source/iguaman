<?php

use App\Http\Controllers\AgihanController;
use App\Http\Controllers\CetakanController;
use App\Http\Controllers\KesController;
use App\Http\Controllers\MahkamahController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PengantaraanController;
use App\Http\Controllers\PeguamController;
use App\Http\Controllers\PeguamDaftarController;
use App\Http\Controllers\PermohonanPeguamController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\SystemAuthController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

// Public landing.
Route::get('/', fn () => view('welcome'))->name('home');

// Public lawyer panel application (no login — prospective panel lawyers). Throttled + honeypot.
Route::get('/peguam/daftar', [PeguamDaftarController::class, 'create'])->name('peguam.daftar');
Route::post('/peguam/daftar', [PeguamDaftarController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('peguam.daftar.store');

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

    // Forced / voluntary password change (migrated accounts are pinned here until done).
    Route::get('/password/change', [SystemAuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password/change', [SystemAuthController::class, 'changePassword'])->name('password.change.update');
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

    // Cetakan (per-case printouts → dompdf, inline stream)
    Route::get('/kes/{kes}/cetak/ringkasan', [CetakanController::class, 'ringkasan'])->name('cetak.ringkasan')->whereNumber('kes');
    Route::get('/kes/{kes}/cetak/penugasan', [CetakanController::class, 'agihan'])->name('cetak.penugasan')->whereNumber('kes');
    Route::get('/kes/{kes}/cetak/laporan', [CetakanController::class, 'laporan'])->name('cetak.laporan')->whereNumber('kes');

    // Statistik + exports
    Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik.index');
    Route::get('/statistik/excel', [StatistikController::class, 'excel'])->name('statistik.excel');
    Route::get('/statistik/pdf', [StatistikController::class, 'pdf'])->name('statistik.pdf');

    // Agihan peguam (assignment) + workload
    Route::get('/kes/{kes}/agih', [AgihanController::class, 'form'])->name('agihan.form')->whereNumber('kes');
    Route::post('/kes/{kes}/agih', [AgihanController::class, 'store'])->name('agihan.store')->whereNumber('kes');
    Route::get('/peguam-panel/beban', [AgihanController::class, 'beban'])->name('agihan.beban');

    // Permohonan peguam panel (application approval workflow)
    Route::get('/permohonan-peguam', [PermohonanPeguamController::class, 'index'])->name('permohonan-peguam.index');
    Route::get('/permohonan-peguam/{butiran}', [PermohonanPeguamController::class, 'show'])->name('permohonan-peguam.show')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/sokong', [PermohonanPeguamController::class, 'sokong'])->name('permohonan-peguam.sokong')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/keputusan', [PermohonanPeguamController::class, 'keputusan'])->name('permohonan-peguam.keputusan')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/tarik-diri', [PermohonanPeguamController::class, 'tarikDiri'])->name('permohonan-peguam.tarik')->whereNumber('butiran');
});

// ---- Lawyer area: panel lawyers (peguam) ----
Route::middleware(['auth', 'role:peguam'])->prefix('peguam')->group(function () {
    Route::get('/', [PeguamController::class, 'dashboard'])->name('peguam.dashboard');
    Route::get('/kes', [PeguamController::class, 'kes'])->name('peguam.kes');
    Route::get('/profil', [PeguamController::class, 'profil'])->name('peguam.profil');
});
