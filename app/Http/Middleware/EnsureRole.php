<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route group to one or more roles.
 *   ->middleware('role:admin,pengarah,koordinator,pegawai')   // staff area
 *   ->middleware('role:peguam')                               // lawyer area
 *
 * Unauthenticated -> login. Authenticated-but-wrong-role -> own dashboard
 * (so staff and lawyers can never wander into each other's area).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('system.login');
        }

        if (! $user->hasRole(...$roles)) {
            return redirect()->route($user->homeRoute());
        }

        return $next($request);
    }
}
