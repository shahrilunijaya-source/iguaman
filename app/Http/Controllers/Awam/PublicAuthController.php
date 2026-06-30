<?php

namespace App\Http\Controllers\Awam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Awam\AwamDaftarRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Public citizen (awam) authentication. Login by No. KP (IC), not email. Same
 * users table + web guard; new accounts get user_type=awam + role 'awam'. Captcha
 * (session 'captcha_sum') guards both forms; honeypot guards register. Routes are
 * throttled in routes/web.php.
 */
class PublicAuthController extends Controller
{
    public function showDaftar(Request $request): View
    {
        return view('awam.auth.daftar', $this->captcha($request));
    }

    public function daftar(AwamDaftarRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'nokp' => $data['nokp'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'user_type' => User::TYPE_AWAM,
            'role' => User::TYPE_AWAM,
            'is_active' => true,
            'must_change_password' => false, // citizens never carry the legacy forced-change flag
        ]);
        $user->assignRole(User::TYPE_AWAM);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('awam.dashboard');
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        return view('awam.auth.login', $this->captcha($request));
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nokp' => ['required', 'string'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'integer'],
        ]);

        if ((int) $data['captcha'] !== (int) $request->session()->get('captcha_sum')) {
            return back()->withInput($request->only('nokp'))
                ->withErrors(['captcha' => 'Jawapan pengesahan salah. Cuba lagi.']);
        }

        if (! Auth::attempt([
            'nokp' => $data['nokp'],
            'password' => $data['password'],
            'user_type' => User::TYPE_AWAM,
            'is_active' => true,
        ])) {
            return back()->withInput($request->only('nokp'))
                ->withErrors(['nokp' => 'No. Kad Pengenalan atau kata laluan tidak sah.']);
        }

        $request->session()->regenerate();
        Auth::user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('awam.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('awam.login');
    }

    private function captcha(Request $request): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $request->session()->put('captcha_sum', $a + $b);

        return ['captchaA' => $a, 'captchaB' => $b];
    }
}
