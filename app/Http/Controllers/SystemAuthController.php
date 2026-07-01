<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

// Plain Laravel auth - NO Filament/Breeze/Jetstream. Login view = resources/views/system/login.blade.php.
// Unified users table (staff + lawyers); landing area decided by role/user_type.
class SystemAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        // Simple number captcha (legacy parity). Store the answer in session.
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $request->session()->put('captcha_sum', $a + $b);

        return view('system.login', ['captchaA' => $a, 'captchaB' => $b]);
    }

    public function attempt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'integer'],
        ]);

        // Verify the number captcha before touching credentials.
        if ((int) $data['captcha'] !== (int) $request->session()->get('captcha_sum')) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['captcha' => 'Jawapan pengesahan salah. Cuba lagi.']);
        }

        $remember = $request->boolean('remember');

        // Only active accounts may sign in.
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true], $remember)) {
            // LOG-01: failed sign-in leaves a forensic trail (never log the password).
            Log::warning('auth.login_failed', ['email' => $data['email'], 'ip' => $request->ip(), 'ua' => $request->userAgent()]);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Emel atau kata laluan tidak sah, atau akaun tidak aktif.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        Log::info('auth.login_success', ['user_id' => $user->id, 'email' => $user->email, 'ip' => $request->ip()]);

        return redirect()->intended(route($user->homeRoute()));
    }

    public function showChangePassword(): View
    {
        return view('system.change-password');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
        ])->save();

        return redirect()->route($user->homeRoute())->with('status', 'Kata laluan dikemaskini.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('system.login');
    }
}
