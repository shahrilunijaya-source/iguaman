# Batch 13 — Public Awam Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a public-facing citizen (`awam`) portal where citizens self-register, apply for Khidmat Nasihat (DIRI_SENDIRI), self-book/cancel/reschedule an appointment, submit feedback, and upload documents — feeding the existing Batch 11 officer queue unchanged.

**Architecture:** Same `users` table + default `web` guard; `awam` user_type + Spatie role, IC login. Citizen routes under `/awam` with a dedicated layout, owner-scoped by a policy. A new `KhidmatNasihatService` is extracted from the staff controller so staff + public share one creation/booking/cancel/reschedule path (DRY). Reuses `SlotAvailabilityService`, `KhidmatBayaran`, `uploaded_files`, captcha, `audit_trail`.

**Tech Stack:** Laravel 13 · MySQL · Blade · PHPUnit · spatie/laravel-permission · existing `app/Support` services.

**Spec:** `docs/superpowers/specs/2026-06-30-batch13-public-awam-portal-design.md`
**Branch:** `batch-13-public-awam-portal`

> **Spec deviation (locked):** the spec proposed a new `no_rujukan` column. The code already has `khidmat_nasihat.no_permohonan` (`KN/{kod}/{year}/{seq}`). **Reuse `no_permohonan` as the citizen reference — do NOT add `no_rujukan`.**

---

## File Structure

**Slice A — Auth foundation + layout**
- Create `database/migrations/2026_06_30_130001_add_awam_to_users_type.php` — add `awam` to `users.user_type` enum.
- Create `database/migrations/2026_06_30_130002_seed_awam_role_permission.php` — `awam` role + `awam.portal` permission (Spatie).
- Modify `app/Models/User.php` — `TYPE_AWAM` const, `isAwam()`, `homeRoute()`.
- Create `app/Http/Controllers/Awam/PublicAuthController.php` — register/login/logout (IC).
- Create `app/Http/Requests/Awam/AwamDaftarRequest.php` — register validation + captcha + honeypot.
- Create `resources/views/layouts/awam.blade.php` — dedicated public shell.
- Create `resources/views/awam/auth/daftar.blade.php`, `resources/views/awam/auth/login.blade.php`.
- Modify `routes/web.php` — `/awam` guest group.
- Test `tests/Feature/Awam/AwamAuthTest.php`.

**Slice B — Service extraction + citizen wizard + dashboard**
- Create `app/Support/KhidmatNasihatService.php` — `create`, `bookSlot`, `releaseSlot`, `reschedule`.
- Modify `app/Http/Controllers/KhidmatNasihatController.php` — delegate to the service (behavior-preserving).
- Create `app/Policies/KhidmatNasihatPolicy.php` — owner scope for `awam`.
- Modify `app/Providers/AppServiceProvider.php` (or `AuthServiceProvider`) — register the policy.
- Create `app/Http/Controllers/Awam/PortalController.php` — dashboard / my applications.
- Create `app/Http/Controllers/Awam/PermohonanController.php` — saringan, create, store, show.
- Create `app/Http/Requests/Awam/AwamPermohonanRequest.php`.
- Create `resources/views/awam/dashboard.blade.php`, `awam/permohonan/saringan.blade.php`, `awam/permohonan/form.blade.php`, `awam/permohonan/show.blade.php`.
- Modify `routes/web.php` — `/awam` auth group.
- Tests `tests/Feature/Khidmat/KhidmatNasihatServiceTest.php`, `tests/Feature/Awam/AwamPermohonanTest.php`.

**Slice C — Cancel / reschedule / feedback / upload**
- Create `database/migrations/2026_06_30_130003_create_maklumbalas_table.php`.
- Create `app/Models/MaklumBalas.php`.
- Create `app/Http/Controllers/Awam/MaklumBalasController.php`.
- Create `app/Http/Requests/Awam/AwamMaklumBalasRequest.php`, `app/Http/Requests/Awam/AwamRescheduleRequest.php`, `app/Http/Requests/Awam/AwamLampiranRequest.php`.
- Modify `app/Http/Controllers/Awam/PermohonanController.php` — `cancel`, `reschedule`, `upload`, `download`.
- Modify `app/Support/KhidmatNasihatService.php` — already has `releaseSlot`/`reschedule` from Slice B.
- Create `resources/views/awam/permohonan/maklumbalas.blade.php`; extend `show.blade.php` with lampiran + cancel/reschedule UI.
- Modify `routes/web.php` — add the action routes.
- Tests `tests/Feature/Awam/AwamLifecycleTest.php` (cancel/reschedule), `tests/Feature/Awam/AwamMaklumBalasTest.php`, `tests/Feature/Awam/AwamLampiranTest.php`.

---

## Conventions (read once)
- Run a single test: `php artisan test --filter=<TestClass or method>`.
- Full suite: `php artisan test`. **Must stay green after every slice.**
- Use existing patterns: captcha = `SystemAuthController::showLogin` (session `captcha_sum`); honeypot = `PeguamDaftarController` route throttle; audit = `App\Support\Audit::log(table, id, action, desc)`.
- Commit after each task with the message shown.

---

# Slice A — Auth foundation + layout

### Task A1: Add `awam` to the `users.user_type` enum

**Files:**
- Create: `database/migrations/2026_06_30_130001_add_awam_to_users_type.php`
- Test: `tests/Feature/Awam/AwamAuthTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Awam;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwamAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_awam_type(): void
    {
        $user = User::factory()->create(['user_type' => 'awam', 'nokp' => '900101015555']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'user_type' => 'awam']);
    }
}
```

- [ ] **Step 2: Run the test, verify it fails**

Run: `php artisan test --filter=test_user_can_be_created_with_awam_type`
Expected: FAIL — enum truncation / data-too-long for `user_type`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY user_type ENUM('staff','lawyer','awam') NOT NULL DEFAULT 'staff'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY user_type ENUM('staff','lawyer') NOT NULL DEFAULT 'staff'");
    }
};
```

- [ ] **Step 4: Migrate + run the test, verify it passes**

Run: `php artisan migrate && php artisan test --filter=test_user_can_be_created_with_awam_type`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_30_130001_add_awam_to_users_type.php tests/Feature/Awam/AwamAuthTest.php
git commit -m "feat(awam): add awam to users.user_type enum"
```

---

### Task A2: `awam` role + `awam.portal` permission

**Files:**
- Create: `database/migrations/2026_06_30_130002_seed_awam_role_permission.php`
- Test: `tests/Feature/Awam/AwamAuthTest.php`

- [ ] **Step 1: Add the failing test**

```php
    public function test_awam_role_and_permission_exist(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'awam']);
        $this->assertDatabaseHas('permissions', ['name' => 'awam.portal']);

        $role = \Spatie\Permission\Models\Role::findByName('awam');
        $this->assertTrue($role->hasPermissionTo('awam.portal'));
    }
```

- [ ] **Step 2: Run, verify it fails**

Run: `php artisan test --filter=test_awam_role_and_permission_exist`
Expected: FAIL — role `awam` does not exist.

- [ ] **Step 3: Write the migration** (mirrors the Batch 7 role/permission seed migrations)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'awam.portal', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'awam', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
    }

    public function down(): void
    {
        Role::where('name', 'awam')->delete();
        Permission::where('name', 'awam.portal')->delete();
    }
};
```

- [ ] **Step 4: Migrate + test, verify PASS**

Run: `php artisan migrate && php artisan test --filter=test_awam_role_and_permission_exist`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_30_130002_seed_awam_role_permission.php tests/Feature/Awam/AwamAuthTest.php
git commit -m "feat(awam): seed awam role + awam.portal permission"
```

---

### Task A3: `User` helpers — `TYPE_AWAM`, `isAwam()`, `homeRoute()`

**Files:**
- Modify: `app/Models/User.php:34-35` (add const), `app/Models/User.php:48-56` (add `isAwam`), `app/Models/User.php:68-71` (`homeRoute`)
- Test: `tests/Feature/Awam/AwamAuthTest.php`

- [ ] **Step 1: Add the failing test**

```php
    public function test_awam_user_home_route_is_awam_dashboard(): void
    {
        $user = User::factory()->create(['user_type' => 'awam']);

        $this->assertTrue($user->isAwam());
        $this->assertSame('awam.dashboard', $user->homeRoute());
    }
```

- [ ] **Step 2: Run, verify FAIL** (`isAwam` undefined).

Run: `php artisan test --filter=test_awam_user_home_route_is_awam_dashboard`

- [ ] **Step 3: Edit `app/Models/User.php`**

Add after line 35 (`public const TYPE_LAWYER = 'lawyer';`):
```php
    public const TYPE_AWAM = 'awam';
```

Add after `isLawyer()` (line 56):
```php
    public function isAwam(): bool
    {
        return $this->user_type === self::TYPE_AWAM;
    }
```

Replace `homeRoute()` body:
```php
    public function homeRoute(): string
    {
        if ($this->isAwam()) {
            return 'awam.dashboard';
        }

        return $this->isLawyer() ? 'peguam.dashboard' : 'system.utama';
    }
```

- [ ] **Step 4: Run, verify PASS**

Run: `php artisan test --filter=test_awam_user_home_route_is_awam_dashboard`
Expected: FAIL on route-not-defined? No — `homeRoute()` returns a string, no route resolution here. Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Awam/AwamAuthTest.php
git commit -m "feat(awam): User::isAwam + awam home route"
```

---

### Task A4: Public layout shell

**Files:**
- Create: `resources/views/layouts/awam.blade.php`

- [ ] **Step 1: Create the layout** (own header/footer; reuse the theme tokens already loaded via Vite `theme.css`)

```blade
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Khidmat Nasihat') — JBG</title>
    @vite(['resources/css/theme.css', 'resources/css/system.css', 'resources/js/app.js'])
</head>
<body class="awam-shell">
    <header class="awam-header">
        <a href="{{ route('awam.dashboard') }}" class="awam-brand">JBG · Khidmat Nasihat</a>
        <nav class="awam-nav">
            @auth
                <a href="{{ route('awam.dashboard') }}">Permohonan Saya</a>
                <form method="POST" action="{{ route('awam.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="link-button">Log Keluar</button>
                </form>
            @else
                <a href="{{ route('awam.login') }}">Log Masuk</a>
                <a href="{{ route('awam.daftar') }}">Daftar</a>
            @endauth
        </nav>
    </header>

    <main class="awam-main">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="awam-footer">
        <p>&copy; {{ now()->year }} Jabatan Bantuan Guaman Malaysia</p>
    </footer>
</body>
</html>
```

- [ ] **Step 2: Commit** (no test — pure view; covered by route tests in A6)

```bash
git add resources/views/layouts/awam.blade.php
git commit -m "feat(awam): dedicated public layout shell"
```

---

### Task A5: `AwamDaftarRequest` (register validation + captcha + honeypot)

**Files:**
- Create: `app/Http/Requests/Awam/AwamDaftarRequest.php`
- Test: `tests/Feature/Awam/AwamAuthTest.php`

- [ ] **Step 1: Add failing tests**

```php
    public function test_register_creates_awam_account_and_logs_in(): void
    {
        $sum = $this->primeCaptcha();

        $response = $this->post('/awam/daftar', [
            'name' => 'Ali bin Abu',
            'nokp' => '900101015555',
            'password' => 'rahsia123',
            'password_confirmation' => 'rahsia123',
            'captcha' => $sum,
            'website' => '', // honeypot empty
        ]);

        $response->assertRedirect(route('awam.dashboard'));
        $this->assertAuthenticated();
        $user = User::where('nokp', '900101015555')->first();
        $this->assertSame('awam', $user->user_type);
        $this->assertTrue($user->hasRole('awam'));
    }

    public function test_register_rejects_filled_honeypot(): void
    {
        $sum = $this->primeCaptcha();

        $this->post('/awam/daftar', [
            'name' => 'Bot', 'nokp' => '900101015556',
            'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
            'captcha' => $sum, 'website' => 'http://spam',
        ])->assertSessionHasErrors('website');

        $this->assertGuest();
    }

    public function test_register_rejects_wrong_captcha(): void
    {
        $sum = $this->primeCaptcha();

        $this->post('/awam/daftar', [
            'name' => 'Ali', 'nokp' => '900101015557',
            'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
            'captcha' => $sum + 1, 'website' => '',
        ])->assertSessionHasErrors('captcha');
    }

    /** Seed the number-captcha session the controller verifies against. */
    private function primeCaptcha(): int
    {
        $this->withSession(['captcha_sum' => 7]);
        return 7;
    }
```

> Add `use App\Models\User;` (already present from A1).

- [ ] **Step 2: Run, verify FAIL** (route `/awam/daftar` not defined).

Run: `php artisan test --filter=AwamAuthTest`

- [ ] **Step 3: Create the FormRequest**

```php
<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public citizen registration. IC + password; email optional. Number captcha
 * (session 'captcha_sum') + honeypot ('website' must stay empty). authorize()=true
 * — this is a guest route, abuse control is captcha + throttle + honeypot.
 */
class AwamDaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nokp' => ['required', 'string', 'max:20', 'regex:/^[0-9-]+$/', 'unique:users,nokp'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'captcha' => ['required', 'integer'],
            // Honeypot — bots fill it; humans never see it. Must be empty/absent.
            'website' => ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'Permohonan tidak sah.',
            'nokp.unique' => 'No. Kad Pengenalan telah didaftarkan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int) $this->input('captcha') !== (int) $this->session()->get('captcha_sum')) {
                $validator->errors()->add('captcha', 'Jawapan pengesahan salah. Cuba lagi.');
            }
        });
    }
}
```

- [ ] **Step 4: (defer run to A6 — controller needed). Commit the request only.**

```bash
git add app/Http/Requests/Awam/AwamDaftarRequest.php
git commit -m "feat(awam): AwamDaftarRequest — captcha + honeypot + IC uniqueness"
```

---

### Task A6: `PublicAuthController` + guest routes + auth views

**Files:**
- Create: `app/Http/Controllers/Awam/PublicAuthController.php`
- Create: `resources/views/awam/auth/daftar.blade.php`, `resources/views/awam/auth/login.blade.php`
- Modify: `routes/web.php` (add `/awam` guest group — see Step 4)
- Test: `tests/Feature/Awam/AwamAuthTest.php`

- [ ] **Step 1: Create the controller**

```php
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
 * Public citizen (awam) authentication. Login is by No. KP (IC), not email —
 * the staff side keeps email login. Same users table + web guard; new accounts
 * get user_type=awam + role 'awam'. Captcha (session 'captcha_sum') guards both
 * forms; honeypot guards register. Routes are throttled in routes/web.php.
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

        // Resolve only awam accounts by IC. Generic error — no enumeration.
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

    /** Number-captcha pair; answer stored in session for the next POST. */
    private function captcha(Request $request): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $request->session()->put('captcha_sum', $a + $b);

        return ['captchaA' => $a, 'captchaB' => $b];
    }
}
```

- [ ] **Step 2: Create the views** (mirror `resources/views/system/login.blade.php` markup; use `@extends('layouts.awam')`)

`resources/views/awam/auth/login.blade.php`:
```blade
@extends('layouts.awam')
@section('title', 'Log Masuk')
@section('content')
<form method="POST" action="{{ route('awam.login') }}" class="awam-card">
    @csrf
    <h1>Log Masuk</h1>
    @error('nokp') <p class="form-error">{{ $message }}</p> @enderror
    <label>No. Kad Pengenalan
        <input name="nokp" value="{{ old('nokp') }}" required>
    </label>
    <label>Kata Laluan
        <input type="password" name="password" required>
    </label>
    <label>Pengesahan: {{ $captchaA }} + {{ $captchaB }} = ?
        <input type="number" name="captcha" required>
    </label>
    @error('captcha') <p class="form-error">{{ $message }}</p> @enderror
    <button type="submit">Log Masuk</button>
    <a href="{{ route('awam.daftar') }}">Belum berdaftar? Daftar di sini</a>
</form>
@endsection
```

`resources/views/awam/auth/daftar.blade.php`:
```blade
@extends('layouts.awam')
@section('title', 'Daftar')
@section('content')
<form method="POST" action="{{ route('awam.daftar') }}" class="awam-card">
    @csrf
    <h1>Daftar Akaun</h1>
    <label>Nama Penuh <input name="name" value="{{ old('name') }}" required></label>
    @error('name') <p class="form-error">{{ $message }}</p> @enderror
    <label>No. Kad Pengenalan <input name="nokp" value="{{ old('nokp') }}" required></label>
    @error('nokp') <p class="form-error">{{ $message }}</p> @enderror
    <label>Emel (pilihan) <input type="email" name="email" value="{{ old('email') }}"></label>
    @error('email') <p class="form-error">{{ $message }}</p> @enderror
    <label>Kata Laluan <input type="password" name="password" required></label>
    @error('password') <p class="form-error">{{ $message }}</p> @enderror
    <label>Sahkan Kata Laluan <input type="password" name="password_confirmation" required></label>
    <label>Pengesahan: {{ $captchaA }} + {{ $captchaB }} = ? <input type="number" name="captcha" required></label>
    @error('captcha') <p class="form-error">{{ $message }}</p> @enderror
    {{-- Honeypot: hidden from humans via CSS; bots fill it. --}}
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    @error('website') <p class="form-error">{{ $message }}</p> @enderror
    <button type="submit">Daftar</button>
</form>
@endsection
```

- [ ] **Step 3: Add the guest routes** to `routes/web.php` (after the existing `peguam/daftar` public block, before the `guest` middleware group)

```php
use App\Http\Controllers\Awam\PublicAuthController;

// ---- Public Awam portal: guest auth (IC login). Captcha + throttle + honeypot. ----
Route::middleware('guest')->group(function () {
    Route::get('/awam/daftar', [PublicAuthController::class, 'showDaftar'])->name('awam.daftar');
    Route::post('/awam/daftar', [PublicAuthController::class, 'daftar'])
        ->middleware('throttle:6,1')->name('awam.daftar.store');

    Route::get('/awam/login', [PublicAuthController::class, 'showLogin'])->name('awam.login');
    Route::post('/awam/login', [PublicAuthController::class, 'login'])
        ->middleware('throttle:10,1')->name('awam.login.attempt');
});

Route::post('/awam/logout', [PublicAuthController::class, 'logout'])
    ->middleware('auth')->name('awam.logout');
```

> NOTE: the FormRequest `AwamDaftarRequest` posts to `awam.daftar.store`? No — the view posts to `route('awam.daftar')` (GET name). Fix: point the form `action` to a POST. Use **named POST** route `awam.daftar.store` in the daftar view `action="{{ route('awam.daftar.store') }}"` and login `action="{{ route('awam.login.attempt') }}"`. Update the two views accordingly before running tests. The A5 test posts to `/awam/daftar` (the URL), which matches the POST route URL — OK.

- [ ] **Step 4: Run the full AwamAuthTest, verify PASS**

Run: `php artisan test --filter=AwamAuthTest`
Expected: PASS (register creates awam + logs in; honeypot + captcha rejected; home route correct).

- [ ] **Step 5: Add the redirect-by-type guard test + dashboard placeholder**

Add to `AwamAuthTest`:
```php
    public function test_awam_cannot_reach_staff_area(): void
    {
        $user = User::factory()->create(['user_type' => 'awam']);
        $user->assignRole('awam');

        $this->actingAs($user)->get('/system')->assertStatus(403);
    }
```
This passes already because `/system` is gated `permission:system.view`, which `awam` lacks → 403. Confirm.

Run: `php artisan test --filter=AwamAuthTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Awam/PublicAuthController.php resources/views/awam/auth routes/web.php tests/Feature/Awam/AwamAuthTest.php
git commit -m "feat(awam): public IC auth (register/login/logout) + guest routes"
```

- [ ] **Step 7: Run the full suite — must stay green**

Run: `php artisan test`
Expected: all green (Slice A adds no staff-side change).

---

# Slice B — Service extraction + citizen wizard + dashboard

### Task B1: Extract `KhidmatNasihatService` (behavior-preserving)

**Files:**
- Create: `app/Support/KhidmatNasihatService.php`
- Modify: `app/Http/Controllers/KhidmatNasihatController.php` (replace inline txn + `bookSlot` + `nextNoPermohonan` with service calls)
- Test: `tests/Feature/Khidmat/KhidmatNasihatServiceTest.php`

- [ ] **Step 1: Write the failing service test** (locks the contract)

```php
<?php

namespace Tests\Feature\Khidmat;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\SlotTemuJanji;
use App\Support\KhidmatNasihatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhidmatNasihatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_assigns_no_permohonan(): void
    {
        $cawangan = Cawangan::factory()->create(['kod' => 'KUL']);
        $svc = app(KhidmatNasihatService::class);

        $kn = $svc->create([
            'nama_mangsa' => 'Siti',
            'cawangan_id' => $cawangan->id,
            'status_kn' => KhidmatNasihat::STATUS_DRAF,
            'jenis_permohonan' => 'DIRI_SENDIRI',
        ]);

        $this->assertNotNull($kn->no_permohonan);
        $this->assertStringContainsString('KN/KUL/', $kn->no_permohonan);
    }

    public function test_book_slot_links_both_ways_and_flags_slot(): void
    {
        $cawangan = Cawangan::factory()->create();
        $slot = SlotTemuJanji::factory()->create([
            'cawangan_id' => $cawangan->id,
            'tarikh_slot' => '2026-08-10',
            'masa_mula' => '09:00:00', 'masa_akhir' => '09:30:00',
            'is_temujanji' => false, 'status_aktif' => true,
        ]);
        $svc = app(KhidmatNasihatService::class);
        $kn = $svc->create([
            'nama_mangsa' => 'Siti', 'cawangan_id' => $cawangan->id,
            'status_kn' => KhidmatNasihat::STATUS_BAHARU, 'jenis_permohonan' => 'DIRI_SENDIRI',
        ]);

        $temu = $svc->bookSlot($kn, '2026-08-10', '09:00', 'Siti');

        $this->assertSame($kn->id, $temu->id_khidmat_nasihat);
        $this->assertTrue($slot->fresh()->is_temujanji);
        $this->assertSame($temu->id, $kn->fresh()->id_temu_janji);
    }
}
```

> If `SlotTemuJanji`/`Cawangan` factories do not exist, create minimal factories first (mirror an existing factory in `database/factories`). Verify with `ls database/factories`.

- [ ] **Step 2: Run, verify FAIL** (`KhidmatNasihatService` missing).

Run: `php artisan test --filter=KhidmatNasihatServiceTest`

- [ ] **Step 3: Create the service** — lift the exact logic out of the controller (`store` txn, `bookSlot`, `nextNoPermohonan`), add `releaseSlot` + `reschedule` for Slice C.

```php
<?php

namespace App\Support;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use Illuminate\Support\Facades\DB;

/**
 * KN creation + appointment slot lifecycle, shared by the staff wizard
 * (KhidmatNasihatController) and the public portal (Awam\PermohonanController).
 * Extracted from KhidmatNasihatController so both paths stay in lockstep.
 */
class KhidmatNasihatService
{
    /** Create a KN row and assign its running no_permohonan. */
    public function create(array $attributes): KhidmatNasihat
    {
        return DB::transaction(function () use ($attributes) {
            $kn = KhidmatNasihat::create($attributes);
            $kn->update(['no_permohonan' => $this->nextNoPermohonan($kn)]);

            return $kn;
        });
    }

    /**
     * Book the chosen open slot: lock it, create temu_janji (MENUNGGU), link both
     * ways, flip is_temujanji. Aborts 422 if the slot was taken concurrently.
     */
    public function bookSlot(KhidmatNasihat $khidmat, string $tarikh, string $masa, string $oleh): TemuJanji
    {
        return DB::transaction(function () use ($khidmat, $tarikh, $masa, $oleh) {
            $slot = SlotTemuJanji::query()
                ->where('cawangan_id', $khidmat->cawangan_id)
                ->whereDate('tarikh_slot', $tarikh)
                ->whereRaw("DATE_FORMAT(masa_mula, '%H:%i') = ?", [$masa])
                ->where('is_temujanji', false)
                ->where('status_aktif', true)
                ->lockForUpdate()
                ->first();

            abort_if($slot === null, 422, 'Slot temu janji tidak lagi tersedia. Sila pilih masa lain.');

            $temu = TemuJanji::create([
                'id_khidmat_nasihat' => $khidmat->id,
                'slot_temu_janji_id' => $slot->id,
                'cawangan_id' => $khidmat->cawangan_id,
                'tarikh_temu_janji' => $slot->tarikh_slot,
                'masa_mula' => $slot->masa_mula,
                'masa_akhir' => $slot->masa_akhir,
                'status' => 'MENUNGGU',
                'cipta_oleh' => $oleh,
            ]);

            $slot->update(['is_temujanji' => true]);
            $khidmat->update(['id_temu_janji' => $temu->id]);

            return $temu;
        });
    }

    /** Free the current appointment's slot and mark the temu_janji BATAL. */
    public function releaseSlot(KhidmatNasihat $khidmat): void
    {
        DB::transaction(function () use ($khidmat) {
            $temu = $khidmat->temuJanji()->first();
            if ($temu === null) {
                return;
            }

            SlotTemuJanji::whereKey($temu->slot_temu_janji_id)->update(['is_temujanji' => false]);
            $temu->update(['status' => 'BATAL']);
            $khidmat->update(['id_temu_janji' => null]);
        });
    }

    /** Release the old slot and book a new one atomically. */
    public function reschedule(KhidmatNasihat $khidmat, string $tarikh, string $masa, string $oleh): TemuJanji
    {
        return DB::transaction(function () use ($khidmat, $tarikh, $masa, $oleh) {
            $this->releaseSlot($khidmat);

            return $this->bookSlot($khidmat, $tarikh, $masa, $oleh);
        });
    }

    /** KN/{cawangan-kod}/{year}/{seq} — seq running per (cawangan, year). */
    private function nextNoPermohonan(KhidmatNasihat $khidmat): string
    {
        $cawangan = $khidmat->cawangan_id ? Cawangan::find($khidmat->cawangan_id) : null;
        $kod = $cawangan?->kod ?: 'JBG';
        $year = now()->year;

        $seq = KhidmatNasihat::where('cawangan_id', $khidmat->cawangan_id)
            ->whereYear('created_at', $year)
            ->where('id', '<=', $khidmat->id)
            ->count();

        return sprintf('KN/%s/%d/%04d', $kod, $year, max(1, $seq));
    }
}
```

- [ ] **Step 4: Refactor `KhidmatNasihatController` to delegate**

In `app/Http/Controllers/KhidmatNasihatController.php`:
- Add to constructor: `private readonly KhidmatNasihatService $service` (keep the existing `SlotAvailabilityService $slots`). Add `use App\Support\KhidmatNasihatService;`.
- Replace `store()` transaction body:
```php
    public function store(KhidmatNasihatRequest $request): RedirectResponse
    {
        $this->assertSaringanGate($request);

        $khidmat = $this->service->create($this->mapInput($request));
        if ($request->isHantar()) {
            $this->service->bookSlot(
                $khidmat,
                $request->validated()['tarikh_temu_janji'],
                $request->validated()['masa_temu_janji'],
                $request->user()->name,
            );
        }

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::INSERT,
            "Permohonan Khidmat Nasihat baharu: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        return redirect()->route('khidmat.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf disimpan.');
    }
```
- Replace `update()`'s booking branch:
```php
        DB::transaction(function () use ($request, $khidmat) {
            $khidmat->update($this->mapInput($request));
        });
        if ($request->isHantar() && $khidmat->id_temu_janji === null) {
            $this->service->bookSlot(
                $khidmat,
                $request->validated()['tarikh_temu_janji'],
                $request->validated()['masa_temu_janji'],
                $request->user()->name,
            );
        }
```
- Delete the private `bookSlot()` and `nextNoPermohonan()` methods (now in the service). Keep `mapInput`, `formData`, `assertSaringanGate`.

- [ ] **Step 5: Run the service test + the existing Batch 9 KN tests, verify all PASS**

Run: `php artisan test --filter=KhidmatNasihat`
Expected: PASS — new service tests green AND the existing staff-wizard tests still green (behavior preserved).

- [ ] **Step 6: Commit**

```bash
git add app/Support/KhidmatNasihatService.php app/Http/Controllers/KhidmatNasihatController.php tests/Feature/Khidmat/KhidmatNasihatServiceTest.php database/factories
git commit -m "refactor(khidmat): extract KhidmatNasihatService (create/book/release/reschedule)"
```

---

### Task B2: `KhidmatNasihatPolicy` — owner scope for awam

**Files:**
- Create: `app/Policies/KhidmatNasihatPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (register policy in `boot()`)
- Test: `tests/Feature/Awam/AwamPermohonanTest.php`

- [ ] **Step 1: Write the failing policy test**

```php
<?php

namespace Tests\Feature\Awam;

use App\Models\KhidmatNasihat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwamPermohonanTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_cannot_view_another_citizens_application(): void
    {
        [$a, $b] = [$this->awam(), $this->awam()];
        $kn = KhidmatNasihat::factory()->create(['id_pengguna' => $b->id]);

        $this->actingAs($a)->get("/awam/permohonan/{$kn->id}")->assertStatus(403);
    }

    private function awam(): User
    {
        $u = User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');
        return $u;
    }
}
```

> If `KhidmatNasihat::factory()` is absent, add a minimal factory mirroring an existing one.

- [ ] **Step 2: Run, verify FAIL** (route + policy missing).

- [ ] **Step 3: Create the policy**

```php
<?php

namespace App\Policies;

use App\Models\KhidmatNasihat;
use App\Models\User;

class KhidmatNasihatPolicy
{
    /** A citizen may act only on their own application. Staff use the staff area. */
    public function view(User $user, KhidmatNasihat $kn): bool
    {
        return $this->owns($user, $kn);
    }

    public function update(User $user, KhidmatNasihat $kn): bool
    {
        return $this->owns($user, $kn);
    }

    private function owns(User $user, KhidmatNasihat $kn): bool
    {
        return $user->isAwam() && (int) $kn->id_pengguna === (int) $user->id;
    }
}
```

- [ ] **Step 4: Register the policy** in `app/Providers/AppServiceProvider.php` `boot()`:

```php
use App\Models\KhidmatNasihat;
use App\Policies\KhidmatNasihatPolicy;
use Illuminate\Support\Facades\Gate;
// ...
Gate::policy(KhidmatNasihat::class, KhidmatNasihatPolicy::class);
```

> The route in B3 calls `$this->authorize('view', $khidmat)`. Test passes once B3's route exists. Keep this test in the file; it goes green at the end of B3.

- [ ] **Step 5: Commit**

```bash
git add app/Policies/KhidmatNasihatPolicy.php app/Providers/AppServiceProvider.php tests/Feature/Awam/AwamPermohonanTest.php database/factories
git commit -m "feat(awam): KhidmatNasihatPolicy owner scope"
```

---

### Task B3: Citizen wizard — saringan, create, store, show + dashboard

**Files:**
- Create: `app/Http/Controllers/Awam/PortalController.php`, `app/Http/Controllers/Awam/PermohonanController.php`
- Create: `app/Http/Requests/Awam/AwamPermohonanRequest.php`
- Create: `resources/views/awam/dashboard.blade.php`, `awam/permohonan/{saringan,form,show}.blade.php`
- Modify: `routes/web.php` (add `/awam` auth group)
- Test: `tests/Feature/Awam/AwamPermohonanTest.php`

- [ ] **Step 1: Add failing flow tests**

```php
    public function test_citizen_can_submit_diri_sendiri_application(): void
    {
        $u = $this->awam();
        $cawangan = \App\Models\Cawangan::factory()->create();
        \App\Models\SlotTemuJanji::factory()->create([
            'cawangan_id' => $cawangan->id, 'tarikh_slot' => now()->addWeekday()->addWeekdays(4)->toDateString(),
            'masa_mula' => '09:00:00', 'masa_akhir' => '09:30:00', 'is_temujanji' => false, 'status_aktif' => true,
        ]);
        $kategori = \App\Models\RefKategoriKn::factory()->create(['aktif' => true]);

        // Pass the saringan gate first.
        $this->actingAs($u)->post('/awam/permohonan/saringan', [
            'saringan_jenis' => 'sivil_syariah',
            'tiada_nasihat_terdahulu' => 'Ya',
            'tiada_perkara_dikecualikan' => 'Ya',
            'pendapatan_bawah_had' => 'Ya',
            'terima_terma' => '1',
        ])->assertRedirect(route('awam.permohonan.create'));

        $slotDate = \App\Models\SlotTemuJanji::first()->tarikh_slot;
        $response = $this->actingAs($u)->post('/awam/permohonan', [
            'aksi' => 'hantar',
            'nama_mangsa' => $u->name,
            'id_pengenalan_mangsa' => $u->nokp,
            'cawangan_id' => $cawangan->id,
            'id_kategori' => $kategori->id,
            'tarikh_temu_janji' => \Illuminate\Support\Carbon::parse($slotDate)->toDateString(),
            'masa_temu_janji' => '09:00',
            'perakuan' => '1',
        ]);

        $kn = KhidmatNasihat::where('id_pengguna', $u->id)->first();
        $this->assertNotNull($kn);
        $this->assertSame(KhidmatNasihat::STATUS_BAHARU, $kn->status_kn);
        $this->assertSame('DIRI_SENDIRI', $kn->jenis_permohonan);
        $response->assertRedirect(route('awam.permohonan.show', $kn));
    }

    public function test_submit_without_saringan_is_blocked(): void
    {
        $u = $this->awam();
        $cawangan = \App\Models\Cawangan::factory()->create();
        $kategori = \App\Models\RefKategoriKn::factory()->create(['aktif' => true]);

        $this->actingAs($u)->post('/awam/permohonan', [
            'aksi' => 'hantar', 'nama_mangsa' => $u->name, 'id_pengenalan_mangsa' => $u->nokp,
            'cawangan_id' => $cawangan->id, 'id_kategori' => $kategori->id,
            'tarikh_temu_janji' => now()->addWeekdays(6)->toDateString(), 'masa_temu_janji' => '09:00', 'perakuan' => '1',
        ])->assertStatus(403);
    }
```

- [ ] **Step 2: Run, verify FAIL.**

- [ ] **Step 3: Create `AwamPermohonanRequest`** (DIRI_SENDIRI only; reuses the staff rule shape, drops all wakil fields)

```php
<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Citizen KN application (DIRI_SENDIRI only). Mirrors KhidmatNasihatRequest minus
 * every SEBAGAI_WAKIL field — a citizen can never file on behalf of prison/JKM/court.
 */
class AwamPermohonanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

    public function isHantar(): bool
    {
        return $this->input('aksi') === 'hantar';
    }

    public function rules(): array
    {
        $hantar = $this->isHantar();
        $req = $hantar ? 'required' : 'nullable';

        return [
            'aksi' => ['nullable', Rule::in(['draf', 'hantar'])],
            'nama_mangsa' => ['required', 'string', 'max:255'],
            'id_pengenalan_mangsa' => [$req, 'string', 'max:255'],
            'jantina_mangsa' => ['nullable', 'in:Lelaki,Perempuan'],
            'umur_mangsa' => ['nullable', 'string', 'max:255'],
            'bangsa' => ['nullable', 'string', 'max:255'],
            'agama' => ['nullable', 'string', 'max:255'],
            'tarikh_lahir_mangsa' => ['nullable', 'date'],
            'alamat_surat1' => ['nullable', 'string', 'max:255'],
            'alamat_surat2' => ['nullable', 'string', 'max:255'],
            'alamat_surat3' => ['nullable', 'string', 'max:255'],
            'poskod' => ['nullable', 'string', 'max:10'],
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'id_kategori' => [$req, 'integer', 'exists:ref_kategori_kn,id'],
            'id_subkategori' => ['nullable', 'integer', 'exists:ref_subkategori_kn,id'],
            'id_negeri' => ['nullable', 'integer'],
            'jenis_kes' => ['nullable', 'string', 'max:255'],
            'ulasan_permohonan' => ['nullable', 'string', 'max:2000'],
            'jumlah_pendapatan' => ['nullable', 'numeric', 'min:0'],
            'tarikh_temu_janji' => [$hantar ? 'required' : 'nullable', 'date'],
            'masa_temu_janji' => [$hantar ? 'required' : 'nullable', 'date_format:H:i'],
            'perakuan' => [$hantar ? 'accepted' : 'nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create `PortalController`** (dashboard / my applications)

```php
<?php

namespace App\Http\Controllers\Awam;

use App\Http\Controllers\Controller;
use App\Models\KhidmatNasihat;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(): View
    {
        $khidmat = KhidmatNasihat::query()
            ->where('id_pengguna', auth()->id())
            ->with(['cawangan', 'temuJanji'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('awam.dashboard', ['khidmat' => $khidmat]);
    }
}
```

- [ ] **Step 5: Create `PermohonanController`** (citizen wizard; reuses the saringan + service)

```php
<?php

namespace App\Http\Controllers\Awam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Awam\AwamPermohonanRequest;
use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\RefNegeri;
use App\Support\Audit;
use App\Support\KhidmatBayaran;
use App\Support\KhidmatNasihatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermohonanController extends Controller
{
    public function __construct(private readonly KhidmatNasihatService $service) {}

    /** Eligibility screening — same 3-modal gate as staff, awam-owned session. */
    public function saringan(): View
    {
        return view('awam.permohonan.saringan', ['outcome' => session('awam_saringan')]);
    }

    public function saringanSemak(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'saringan_jenis' => ['required', 'in:sivil_syariah,pendamping_jenayah'],
            'tiada_nasihat_terdahulu' => ['required', 'in:Ya,Tidak'],
            'tiada_perkara_dikecualikan' => ['required', 'in:Ya,Tidak'],
            'pendapatan_bawah_had' => ['nullable', 'in:Ya,Tidak'],
            'terima_terma' => ['accepted'],
        ]);

        $jenis = $data['saringan_jenis'] === 'pendamping_jenayah'
            ? KhidmatNasihat::SARINGAN_PENDAMPING
            : KhidmatNasihat::SARINGAN_SIVIL_SYARIAH;
        $isSivilSyariah = $jenis === KhidmatNasihat::SARINGAN_SIVIL_SYARIAH;

        $eligible = $data['tiada_nasihat_terdahulu'] === 'Ya' && $data['tiada_perkara_dikecualikan'] === 'Ya';
        if (! $eligible) {
            return redirect()->route('awam.permohonan.saringan')
                ->with('saringan_gagal', 'Anda tidak layak memohon kerana tidak memenuhi syarat kelayakan.');
        }

        $request->session()->put('awam_saringan', [
            'jenis' => $jenis,
            'lulus' => true,
            'sumbangan' => $isSivilSyariah && ($data['pendapatan_bawah_had'] ?? 'Ya') === 'Tidak',
        ]);

        return redirect()->route('awam.permohonan.create');
    }

    public function create(): View
    {
        return view('awam.permohonan.form', [
            'outcome' => session('awam_saringan'),
            'cawanganList' => Cawangan::where('status_aktif', true)->orderBy('nama')->get(['id', 'nama', 'kod', 'negeri_id']),
            'kategoriList' => RefKategoriKn::where('aktif', true)->orderBy('jenis_kategori')->get(),
            'negeriList' => RefNegeri::orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }

    public function store(AwamPermohonanRequest $request): RedirectResponse
    {
        // Hard gate: a final submit requires a session-side saringan pass (tamper-proof).
        if ($request->isHantar()) {
            abort_unless(session('awam_saringan.lulus') === true, 403, 'Saringan kelayakan diperlukan.');
        }

        $khidmat = $this->service->create($this->mapInput($request));

        if ($request->isHantar()) {
            $this->service->bookSlot(
                $khidmat,
                $request->validated()['tarikh_temu_janji'],
                $request->validated()['masa_temu_janji'],
                $request->user()->name,
            );
            $request->session()->forget('awam_saringan');
        }

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::INSERT,
            "Permohonan awam: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        return redirect()->route('awam.permohonan.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf disimpan.');
    }

    public function show(KhidmatNasihat $khidmat): View
    {
        $this->authorize('view', $khidmat);
        $khidmat->load(['cawangan', 'kategori', 'temuJanji']);

        return view('awam.permohonan.show', ['khidmat' => $khidmat]);
    }

    /** Validated input → khidmat_nasihat columns for a citizen (DIRI_SENDIRI). */
    private function mapInput(AwamPermohonanRequest $request): array
    {
        $v = $request->validated();
        $saringan = session('awam_saringan');
        $kategori = RefKategoriKn::find($v['id_kategori'] ?? null);

        // Citizen path: never wakil, never is_percuma. Fee from KhidmatBayaran.
        $fee = KhidmatBayaran::kira($kategori?->jenis_kategori, $v['jumlah_pendapatan'] ?? null, false, null);

        return [
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna' => $request->user()->id,
            'saringan_jenis' => $saringan['jenis'] ?? null,
            'saringan_lulus' => (bool) ($saringan['lulus'] ?? false),
            'is_laluan_sumbangan' => (bool) ($saringan['sumbangan'] ?? false),
            'nama_mangsa' => $v['nama_mangsa'],
            'id_pengenalan_mangsa' => $v['id_pengenalan_mangsa'] ?? $request->user()->nokp,
            'jantina_mangsa' => $v['jantina_mangsa'] ?? null,
            'umur_mangsa' => $v['umur_mangsa'] ?? null,
            'bangsa' => $v['bangsa'] ?? null,
            'agama' => $v['agama'] ?? null,
            'tarikh_lahir_mangsa' => $v['tarikh_lahir_mangsa'] ?? null,
            'alamat_surat1' => $v['alamat_surat1'] ?? null,
            'alamat_surat2' => $v['alamat_surat2'] ?? null,
            'alamat_surat3' => $v['alamat_surat3'] ?? null,
            'poskod' => $v['poskod'] ?? null,
            'cawangan_id' => $v['cawangan_id'],
            'id_kategori' => $v['id_kategori'] ?? null,
            'id_subkategori' => $v['id_subkategori'] ?? null,
            'id_negeri' => $v['id_negeri'] ?? null,
            'jenis_kes' => $v['jenis_kes'] ?? null,
            'jumlah_pendapatan' => $v['jumlah_pendapatan'] ?? null,
            'ulasan_permohonan' => $v['ulasan_permohonan'] ?? null,
            'jumlah_bayaran' => $fee,
            'is_percuma' => false,
            'perakuan' => $request->isHantar() ? $request->boolean('perakuan') : false,
            'status_kn' => $request->isHantar() ? KhidmatNasihat::STATUS_BAHARU : KhidmatNasihat::STATUS_DRAF,
            'cipta_oleh' => $request->user()->name,
            'kemaskini_oleh' => $request->user()->name,
        ];
    }
}
```

- [ ] **Step 6: Add the `/awam` auth route group** to `routes/web.php`

```php
use App\Http\Controllers\Awam\PortalController;
use App\Http\Controllers\Awam\PermohonanController;

// ---- Public Awam portal: authenticated citizen area ----
Route::middleware(['auth', 'permission:awam.portal'])->prefix('awam')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('awam.dashboard');

    Route::get('/permohonan/saringan', [PermohonanController::class, 'saringan'])->name('awam.permohonan.saringan');
    Route::post('/permohonan/saringan', [PermohonanController::class, 'saringanSemak'])->name('awam.permohonan.saringan.semak');
    Route::get('/permohonan/baharu', [PermohonanController::class, 'create'])->name('awam.permohonan.create');
    Route::post('/permohonan', [PermohonanController::class, 'store'])->middleware('throttle:10,1')->name('awam.permohonan.store');
    Route::get('/permohonan/{khidmat}', [PermohonanController::class, 'show'])->name('awam.permohonan.show')->whereNumber('khidmat');
});
```

> Route-model binding: `{khidmat}` → `KhidmatNasihat`. The store route name is `awam.permohonan.store` but the test posts to `/awam/permohonan` (URL) — matches. The saringan test redirects to `route('awam.permohonan.create')` — matches.

- [ ] **Step 7: Create the views** — `dashboard`, `saringan`, `form`, `show`, all `@extends('layouts.awam')`.

`awam/permohonan/form.blade.php` — mirror `resources/views/khidmat-nasihat/form.blade.php` but: DIRI_SENDIRI only (drop the wakil/mahkamah blocks), hidden `aksi`, branch `<select>` from `$cawanganList`, kategori from `$kategoriList`, and the slot date/time pickers calling the existing JSON endpoints `route('slot.tarikh')`/`route('slot.masa')`. **Reuse the staff form's slot-picker JS verbatim.**
`awam/permohonan/saringan.blade.php` — 3 questions (saringan_jenis, tiada_nasihat_terdahulu, tiada_perkara_dikecualikan, pendapatan_bawah_had, terima_terma checkbox), POST to `awam.permohonan.saringan.semak`; show `session('saringan_gagal')`.
`awam/dashboard.blade.php` — table of `$khidmat`: `no_permohonan`, status, appointment date, link to `awam.permohonan.show`. Button to `awam.permohonan.saringan` ("Mohon Baharu").
`awam/permohonan/show.blade.php` — status, `no_permohonan`, fee, appointment, (Slice C adds cancel/reschedule/feedback/upload controls).

> The slot JSON endpoints are gated `permission:slot.view`, which `awam` lacks. **Add `awam.portal` as an accepted permission on those two routes** OR add a thin awam-scoped slot endpoint. Simplest: change the two `/slot/tarikh` + `/slot/masa` routes to `->middleware('permission:slot.view|awam.portal')`? Spatie multi-permission uses `|`. Apply that.

Edit `routes/web.php` slot block:
```php
    Route::middleware('permission:slot.view')->group(function () {
        Route::get('/slot/tarikh', [SlotController::class, 'availability'])->name('slot.tarikh');
        Route::get('/slot/masa', [SlotController::class, 'times'])->name('slot.masa');
    });
```
→ move these two OUT of the staff-only `permission:system.view` group into a shared group gated `permission:slot.view|awam.portal` so citizens can read availability. (They already require `auth`.) Keep read-only.

- [ ] **Step 8: Run the Awam flow tests + policy test, verify PASS**

Run: `php artisan test --filter=AwamPermohonanTest`
Expected: PASS — submit creates a BAHARU DIRI_SENDIRI row owned by the citizen; no-saringan submit 403; cross-citizen view 403.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Awam/PortalController.php app/Http/Controllers/Awam/PermohonanController.php app/Http/Requests/Awam/AwamPermohonanRequest.php resources/views/awam routes/web.php tests/Feature/Awam/AwamPermohonanTest.php database/factories
git commit -m "feat(awam): citizen KN wizard (saringan + apply + book) + dashboard"
```

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: all green (staff KN tests still pass via the service; slot endpoint change is additive).

---

# Slice C — Cancel / reschedule / feedback / upload

### Task C1: Cancel + reschedule appointment

**Files:**
- Modify: `app/Http/Controllers/Awam/PermohonanController.php` (`cancel`, `reschedule`)
- Create: `app/Http/Requests/Awam/AwamRescheduleRequest.php`
- Modify: `routes/web.php`
- Extend: `resources/views/awam/permohonan/show.blade.php`
- Test: `tests/Feature/Awam/AwamLifecycleTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Awam;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Models\User;
use App\Support\KhidmatNasihatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwamLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_cancel_future_appointment(): void
    {
        [$u, $kn, $slot] = $this->booked();

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/batal")
            ->assertRedirect(route('awam.permohonan.show', $kn));

        $this->assertFalse($slot->fresh()->is_temujanji);
        $this->assertSame(KhidmatNasihat::STATUS_BATAL, $kn->fresh()->status_kn);
    }

    public function test_citizen_can_reschedule_to_new_slot(): void
    {
        [$u, $kn, $slot] = $this->booked();
        $newDate = now()->addWeekdays(8)->toDateString();
        $new = SlotTemuJanji::factory()->create([
            'cawangan_id' => $kn->cawangan_id, 'tarikh_slot' => $newDate,
            'masa_mula' => '10:00:00', 'masa_akhir' => '10:30:00', 'is_temujanji' => false, 'status_aktif' => true,
        ]);

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/jadual-semula", [
            'tarikh_temu_janji' => $newDate, 'masa_temu_janji' => '10:00',
        ])->assertRedirect(route('awam.permohonan.show', $kn));

        $this->assertTrue($new->fresh()->is_temujanji);
        $this->assertFalse($slot->fresh()->is_temujanji);
    }

    public function test_cannot_cancel_other_citizens_appointment(): void
    {
        [, $kn] = $this->booked();
        $intruder = User::factory()->create(['user_type' => 'awam']);
        $intruder->assignRole('awam');

        $this->actingAs($intruder)->post("/awam/permohonan/{$kn->id}/batal")->assertStatus(403);
    }

    /** @return array{0: User, 1: KhidmatNasihat, 2: SlotTemuJanji} */
    private function booked(): array
    {
        $u = User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');
        $cawangan = Cawangan::factory()->create();
        $date = now()->addWeekdays(6)->toDateString();
        $slot = SlotTemuJanji::factory()->create([
            'cawangan_id' => $cawangan->id, 'tarikh_slot' => $date,
            'masa_mula' => '09:00:00', 'masa_akhir' => '09:30:00', 'is_temujanji' => false, 'status_aktif' => true,
        ]);
        $kn = KhidmatNasihat::factory()->create([
            'id_pengguna' => $u->id, 'cawangan_id' => $cawangan->id, 'status_kn' => KhidmatNasihat::STATUS_BAHARU,
        ]);
        app(KhidmatNasihatService::class)->bookSlot($kn, $date, '09:00', $u->name);

        return [$u, $kn->fresh(), $slot->fresh()];
    }
}
```

- [ ] **Step 2: Run, verify FAIL** (routes missing).

- [ ] **Step 3: Create `AwamRescheduleRequest`**

```php
<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

class AwamRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

    public function rules(): array
    {
        return [
            'tarikh_temu_janji' => ['required', 'date'],
            'masa_temu_janji' => ['required', 'date_format:H:i'],
        ];
    }
}
```

- [ ] **Step 4: Add `cancel` + `reschedule` to `PermohonanController`**

```php
use App\Http\Requests\Awam\AwamRescheduleRequest;

    /** Citizen cancels their own appointment while it is still future + not attended. */
    public function cancel(KhidmatNasihat $khidmat): RedirectResponse
    {
        $this->authorize('update', $khidmat);
        $this->assertCancellable($khidmat);

        $this->service->releaseSlot($khidmat);
        $khidmat->update(['status_kn' => KhidmatNasihat::STATUS_BATAL]);

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE, "Permohonan dibatalkan oleh pemohon: {$khidmat->no_permohonan}");

        return redirect()->route('awam.permohonan.show', $khidmat)->with('status', 'Temu janji dibatalkan.');
    }

    public function reschedule(AwamRescheduleRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $this->authorize('update', $khidmat);
        $this->assertCancellable($khidmat);

        $this->service->reschedule(
            $khidmat,
            $request->validated()['tarikh_temu_janji'],
            $request->validated()['masa_temu_janji'],
            $request->user()->name,
        );

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE, "Temu janji dijadual semula: {$khidmat->no_permohonan}");

        return redirect()->route('awam.permohonan.show', $khidmat)->with('status', 'Temu janji dijadual semula.');
    }

    /** Self-service cancel/reschedule allowed only before attendance + on a future date. */
    private function assertCancellable(KhidmatNasihat $khidmat): void
    {
        $temu = $khidmat->temuJanji()->first();
        abort_if($temu === null, 422, 'Tiada temu janji untuk diubah.');
        abort_if(in_array($temu->status, ['HADIR', 'TIDAK_HADIR', 'SELESAI', 'BATAL'], true), 422, 'Temu janji ini tidak boleh diubah.');
        abort_if(\Illuminate\Support\Carbon::parse($temu->tarikh_temu_janji)->isPast(), 422, 'Temu janji lampau tidak boleh diubah.');
    }
```

- [ ] **Step 5: Add the routes** to the `/awam` auth group in `routes/web.php`

```php
    Route::post('/permohonan/{khidmat}/batal', [PermohonanController::class, 'cancel'])->name('awam.permohonan.batal')->whereNumber('khidmat');
    Route::post('/permohonan/{khidmat}/jadual-semula', [PermohonanController::class, 'reschedule'])->name('awam.permohonan.reschedule')->whereNumber('khidmat');
```

- [ ] **Step 6: Run the lifecycle tests, verify PASS.**

Run: `php artisan test --filter=AwamLifecycleTest`

- [ ] **Step 7: Add cancel/reschedule controls to `show.blade.php`** (cancel = POST button; reschedule = slot-picker mini-form reusing the JSON endpoints). Only render when `$khidmat->temuJanji` is future + status in MENUNGGU/DISAHKAN.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Awam/PermohonanController.php app/Http/Requests/Awam/AwamRescheduleRequest.php routes/web.php resources/views/awam/permohonan/show.blade.php tests/Feature/Awam/AwamLifecycleTest.php
git commit -m "feat(awam): citizen cancel + reschedule appointment"
```

---

### Task C2: Feedback (`maklumbalas`)

**Files:**
- Create: `database/migrations/2026_06_30_130003_create_maklumbalas_table.php`
- Create: `app/Models/MaklumBalas.php`
- Create: `app/Http/Controllers/Awam/MaklumBalasController.php`, `app/Http/Requests/Awam/AwamMaklumBalasRequest.php`
- Create: `resources/views/awam/permohonan/maklumbalas.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Awam/AwamMaklumBalasTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Awam;

use App\Models\KhidmatNasihat;
use App\Models\MaklumBalas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwamMaklumBalasTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_submit_feedback_after_selesai(): void
    {
        [$u, $kn] = $this->selesai();

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/maklumbalas", [
            'kepuasan' => 5, 'komen' => 'Bagus',
        ])->assertRedirect(route('awam.permohonan.show', $kn));

        $this->assertDatabaseHas('maklumbalas', ['id_khidmat' => $kn->id, 'kepuasan' => 5]);
    }

    public function test_feedback_blocked_before_selesai(): void
    {
        $u = User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');
        $kn = KhidmatNasihat::factory()->create(['id_pengguna' => $u->id, 'status_kn' => KhidmatNasihat::STATUS_BAHARU]);

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/maklumbalas", ['kepuasan' => 5])->assertStatus(422);
    }

    public function test_feedback_only_once(): void
    {
        [$u, $kn] = $this->selesai();
        MaklumBalas::create(['id_khidmat' => $kn->id, 'kepuasan' => 4]);

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/maklumbalas", ['kepuasan' => 5])->assertStatus(422);
    }

    /** @return array{0: User, 1: KhidmatNasihat} */
    private function selesai(): array
    {
        $u = User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');
        $kn = KhidmatNasihat::factory()->create(['id_pengguna' => $u->id, 'status_kn' => KhidmatNasihat::STATUS_SELESAI]);

        return [$u, $kn];
    }
}
```

- [ ] **Step 2: Run, verify FAIL.**

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maklumbalas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_khidmat')->unique()->constrained('khidmat_nasihat')->cascadeOnDelete();
            $table->unsignedTinyInteger('kepuasan'); // 1-5 overall satisfaction
            $table->unsignedTinyInteger('kemudahan')->nullable();
            $table->unsignedTinyInteger('layanan')->nullable();
            $table->text('komen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maklumbalas');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_khidmat', 'kepuasan', 'kemudahan', 'layanan', 'komen'])]
class MaklumBalas extends Model
{
    protected $table = 'maklumbalas';

    public function khidmat(): BelongsTo
    {
        return $this->belongsTo(KhidmatNasihat::class, 'id_khidmat');
    }
}
```

- [ ] **Step 5: Create `AwamMaklumBalasRequest`**

```php
<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

class AwamMaklumBalasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

    public function rules(): array
    {
        return [
            'kepuasan' => ['required', 'integer', 'between:1,5'],
            'kemudahan' => ['nullable', 'integer', 'between:1,5'],
            'layanan' => ['nullable', 'integer', 'between:1,5'],
            'komen' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 6: Create `MaklumBalasController`**

```php
<?php

namespace App\Http\Controllers\Awam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Awam\AwamMaklumBalasRequest;
use App\Models\KhidmatNasihat;
use App\Models\MaklumBalas;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MaklumBalasController extends Controller
{
    public function create(KhidmatNasihat $khidmat): View
    {
        $this->authorize('view', $khidmat);

        return view('awam.permohonan.maklumbalas', ['khidmat' => $khidmat]);
    }

    public function store(AwamMaklumBalasRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $this->authorize('update', $khidmat);
        abort_unless($khidmat->status_kn === KhidmatNasihat::STATUS_SELESAI, 422, 'Maklum balas hanya selepas temu janji selesai.');
        abort_if(MaklumBalas::where('id_khidmat', $khidmat->id)->exists(), 422, 'Maklum balas telah dihantar.');

        MaklumBalas::create($request->validated() + ['id_khidmat' => $khidmat->id]);

        return redirect()->route('awam.permohonan.show', $khidmat)->with('status', 'Terima kasih atas maklum balas anda.');
    }
}
```

- [ ] **Step 7: Add routes** to the `/awam` group

```php
use App\Http\Controllers\Awam\MaklumBalasController;
// inside the /awam group:
    Route::get('/permohonan/{khidmat}/maklumbalas', [MaklumBalasController::class, 'create'])->name('awam.maklumbalas.create')->whereNumber('khidmat');
    Route::post('/permohonan/{khidmat}/maklumbalas', [MaklumBalasController::class, 'store'])->name('awam.maklumbalas.store')->whereNumber('khidmat');
```

- [ ] **Step 8: Create the view** `awam/permohonan/maklumbalas.blade.php` (1–5 radios for kepuasan/kemudahan/layanan + komen textarea, POST to `awam.maklumbalas.store`).

- [ ] **Step 9: Migrate + run tests, verify PASS**

Run: `php artisan migrate && php artisan test --filter=AwamMaklumBalasTest`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_06_30_130003_create_maklumbalas_table.php app/Models/MaklumBalas.php app/Http/Controllers/Awam/MaklumBalasController.php app/Http/Requests/Awam/AwamMaklumBalasRequest.php resources/views/awam/permohonan/maklumbalas.blade.php routes/web.php tests/Feature/Awam/AwamMaklumBalasTest.php
git commit -m "feat(awam): satisfaction feedback (maklumbalas) submit, once per KN"
```

---

### Task C3: Document upload + owner-gated download

**Files:**
- Create: `app/Http/Requests/Awam/AwamLampiranRequest.php`
- Modify: `app/Http/Controllers/Awam/PermohonanController.php` (`upload`, `download`)
- Modify: `routes/web.php`
- Extend: `resources/views/awam/permohonan/show.blade.php`
- Test: `tests/Feature/Awam/AwamLampiranTest.php`

> Reuse the existing case-attachment pattern in `LampiranController` (private disk, auth-streamed). Verify the upload table/disk it uses: `grep -n "disk\|Storage\|uploaded_files\|private" app/Http/Controllers/LampiranController.php`. Mirror its disk + validation approach for the citizen polymorphic owner (`khidmat_nasihat`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Awam;

use App\Models\KhidmatNasihat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AwamLampiranTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_upload_pdf_to_own_application(): void
    {
        Storage::fake('local');
        $u = $this->awam();
        $kn = KhidmatNasihat::factory()->create(['id_pengguna' => $u->id]);

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/lampiran", [
            'fail' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('awam.permohonan.show', $kn));

        $this->assertDatabaseCount('uploaded_files', 1);
    }

    public function test_rejects_disallowed_mime(): void
    {
        Storage::fake('local');
        $u = $this->awam();
        $kn = KhidmatNasihat::factory()->create(['id_pengguna' => $u->id]);

        $this->actingAs($u)->post("/awam/permohonan/{$kn->id}/lampiran", [
            'fail' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('fail');
    }

    private function awam(): User
    {
        $u = User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');
        return $u;
    }
}
```

> Confirm the upload table name from `LampiranController` (assumed `uploaded_files`). Adjust `assertDatabaseCount`/`assertDatabaseHas` to the real table if it differs.

- [ ] **Step 2: Run, verify FAIL.**

- [ ] **Step 3: Create `AwamLampiranRequest`**

```php
<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

class AwamLampiranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

    public function rules(): array
    {
        return [
            'fail' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 MB
        ];
    }
}
```

- [ ] **Step 4: Add `upload` + `download` to `PermohonanController`** — mirror `LampiranController`'s disk + model. Pseudocode contract to match the existing attachment code:

```php
use App\Http\Requests\Awam\AwamLampiranRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

    public function upload(AwamLampiranRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $this->authorize('update', $khidmat);

        // Mirror LampiranController::store: store on the private disk + persist a row
        // linked to this khidmat_nasihat (owner_type/owner_id or the existing FK shape).
        // Use the SAME disk + table the case attachments use.
        // ... (copy LampiranController::store's persistence, swapping the owner to $khidmat) ...

        return redirect()->route('awam.permohonan.show', $khidmat)->with('status', 'Dokumen dimuat naik.');
    }

    public function download(KhidmatNasihat $khidmat, int $fail): StreamedResponse
    {
        $this->authorize('view', $khidmat);
        // Load the file row, assert it belongs to $khidmat, stream from the private disk.
        // Mirror LampiranController::download exactly (owner check + Storage::download).
    }
```

> **Implementation note for the executor:** open `app/Http/Controllers/LampiranController.php` and copy its `store`/`download` body, changing only the owner from `kes` to `khidmat_nasihat` and the route names. Keep the same disk, table, and streaming. Do NOT invent a new uploads table if one exists.

- [ ] **Step 5: Add routes** to the `/awam` group

```php
    Route::post('/permohonan/{khidmat}/lampiran', [PermohonanController::class, 'upload'])->name('awam.lampiran.store')->whereNumber('khidmat');
    Route::get('/permohonan/{khidmat}/lampiran/{fail}/muat-turun', [PermohonanController::class, 'download'])->name('awam.lampiran.download')->whereNumber('khidmat')->whereNumber('fail');
```

- [ ] **Step 6: Run upload tests, verify PASS.**

Run: `php artisan test --filter=AwamLampiranTest`

- [ ] **Step 7: Add an upload form + file list to `show.blade.php`.**

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Awam/AwamLampiranRequest.php app/Http/Controllers/Awam/PermohonanController.php routes/web.php resources/views/awam/permohonan/show.blade.php tests/Feature/Awam/AwamLampiranTest.php
git commit -m "feat(awam): document upload + owner-gated download"
```

---

### Task C4: Full-suite green + landing entry

**Files:**
- Modify: `resources/views/welcome.blade.php` (add a "Khidmat Nasihat — Mohon Dalam Talian" link to `awam.login`/`awam.daftar`)

- [ ] **Step 1: Add the public entry link** on the landing page pointing to `route('awam.login')`.

- [ ] **Step 2: Run the full suite**

Run: `php artisan test`
Expected: ALL green.

- [ ] **Step 3: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat(awam): link public KN portal from landing page"
```

---

## Self-Review checklist (completed during planning)

- **Spec coverage:** auth (A5/A6), DIRI_SENDIRI wizard + saringan (B3), slot booking (B1/B3), my-applications (B3/PortalController), cancel + reschedule (C1), feedback (C2), upload (C3), owner policy (B2), dedicated layout (A4), security controls (captcha/honeypot/throttle A5/A6, owner policy B2, upload validation C3, tamper-proof saringan B3), testing (every task). ✅
- **Spec deviation:** `no_rujukan` dropped in favour of the existing `no_permohonan` (documented at top). ✅
- **Type consistency:** service methods `create/bookSlot/releaseSlot/reschedule` used identically across B1/C1; `KhidmatBayaran::kira(?string,$,bool,?string)`, `SlotAvailabilityService::availableDates/availableTimes`, `KhidmatNasihat::STATUS_*`, `TemuJanji::STATUS` all match the real source read during planning. ✅
- **Open items to verify during execution (flagged inline):** existence of `Cawangan`/`SlotTemuJanji`/`KhidmatNasihat`/`RefKategoriKn` factories (create minimal ones if missing); the real uploads table/disk in `LampiranController`; that moving the slot JSON endpoints to `permission:slot.view|awam.portal` doesn't break the staff wizard. ✅
