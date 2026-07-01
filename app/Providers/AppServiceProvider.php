<?php

namespace App\Providers;

use App\Events\PemindahanCawanganDimulakan;
use App\Listeners\MaklumkanPemindahanMasuk;
use App\Models\KhidmatNasihat;
use App\Models\User;
use App\Policies\KhidmatNasihatPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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

        // AUTH-08: minimum password strength for change-password + reset flows.
        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        // AUTH-08: login rate limit — per-identifier (blunts distributed credential stuffing
        // against one account, which IP-only throttling misses) AND a looser per-IP cap.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
