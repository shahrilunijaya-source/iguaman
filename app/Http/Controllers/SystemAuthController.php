<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// Plain Laravel auth — NO Filament/Breeze/Jetstream. Login view = resources/views/system/login.blade.php.
// Unified users table (staff + lawyers); landing area decided by role/user_type.
class SystemAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        return view('system.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Only active accounts may sign in.
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true], $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Emel atau kata laluan tidak sah, atau akaun tidak aktif.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('system.login');
    }
}
