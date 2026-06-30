<?php

namespace App\Providers;

use App\Events\PemindahanCawanganDimulakan;
use App\Listeners\MaklumkanPemindahanMasuk;
use App\Models\KhidmatNasihat;
use App\Models\User;
use App\Policies\KhidmatNasihatPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // admin is a super-admin: bypasses every permission check (mirrors the legacy
        // "admin is in every role: list" behavior and future-proofs new modules).
        // Return null (not false) so non-admins fall through to normal gate checks.
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);

        Gate::policy(KhidmatNasihat::class, KhidmatNasihatPolicy::class);

        // W21 — real-time integration: a branch transfer fans out a queued notification
        // to the destination branch's supervisors without blocking the transfer txn.
        Event::listen(PemindahanCawanganDimulakan::class, MaklumkanPemindahanMasuk::class);
    }
}
