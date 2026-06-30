<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts flagged must_change_password (migrated legacy plaintext) are pinned to the
 * change-password screen until they set a new password. Logout + the change routes are allowed.
 */
class ForcePasswordChange
{
    private array $allowed = ['password.change', 'password.change.update', 'system.logout', 'awam.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_change_password && ! $request->routeIs($this->allowed)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
