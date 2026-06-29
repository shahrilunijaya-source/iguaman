<?php

use App\Http\Controllers\SystemAuthController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

// Public landing (restyle/replace as needed).
Route::get('/', fn () => view('welcome'))->name('home');

// ---- System auth (plain Laravel, no Filament) ----
Route::middleware('guest')->group(function () {
    Route::get('/system/login', [SystemAuthController::class, 'showLogin'])->name('system.login');
    Route::post('/system/login', [SystemAuthController::class, 'attempt'])->name('system.login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/system', [SystemController::class, 'utama'])->name('system.utama'); // dashboard
    Route::post('/logout', [SystemAuthController::class, 'logout'])->name('system.logout');
});
