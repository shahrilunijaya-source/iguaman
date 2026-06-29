<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
