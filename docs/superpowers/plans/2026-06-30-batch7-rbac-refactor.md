# Batch 7 — DB-driven RBAC (full-app refactor) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded role-const + `EnsureRole` authorization across the entire 2in1 app with Spatie laravel-permission DB-driven roles + permissions, with an admin UI, preserving today's behavior exactly (except the two intentional changes in the spec §1a).

**Architecture:** Install Spatie; add `HasRoles` to `User` while the custom `hasRole()` still overrides the trait (so nothing breaks); seed roles+permissions to mirror current access; backfill role assignments; establish a route×role regression test as a safety net; then migrate controllers → `can()`, routes → `permission:`, views → `@can`, and `CawanganScope` → permission-based; remove the custom `hasRole()` last. `admin` is a super-admin via `Gate::before`.

**Tech Stack:** Laravel 13.8, PHP 8.3, spatie/laravel-permission ^7.0, PHPUnit 12.5. Feature tests run against the live `iguaman_2in1` MySQL DB (repo convention), self-cleaning by tag.

**Spec:** `docs/superpowers/specs/2026-06-30-batch7-rbac-refactor-design.md` (read it; this plan implements it).

---

## Sequencing rationale (why this order is always-green)

`EnsureRole::handle` calls `$user->hasRole(...$roles)` (variadic). Spatie's `HasRoles` trait ships `hasRole($roles, $guard)`. A **class-defined method overrides a trait method** in PHP, so while `User::hasRole()` exists, the custom variadic behavior wins even after the trait is added — no collision, no behavior change. We therefore: add the trait + seed + backfill first (inert), lock in a regression test, migrate every variadic caller to `can()` or array form, and **remove the custom `hasRole()` only in the final code task**, at which point Spatie's trait method takes over for any residual array-form calls.

## File structure (created / modified)

**Created**
- `database/seeders/RolePermissionSeeder.php` — roles + permissions + matrix (idempotent).
- `app/Console/Commands/BackfillUserRoles.php` — assign Spatie roles to existing users.
- `app/Http/Controllers/RoleController.php` — peranan CRUD (admin UI).
- `app/Http/Controllers/RolePermissionController.php` — per-role permission matrix.
- `resources/views/peranan/index.blade.php`, `resources/views/peranan/form.blade.php`, `resources/views/peranan/akses.blade.php` — admin UI.
- `tests/Feature/Batch7RbacMatrixTest.php` — route×role regression matrix.
- `tests/Feature/Batch7SeederTest.php` — seeder + backfill assertions.
- `tests/Feature/Batch7ScopeTest.php` — CawanganScope behavior.
- `tests/Feature/Batch7AdminUiTest.php` — role/akses admin UI.
- `docs/superpowers/runbooks/2026-06-30-batch7-rbac-deploy.md` — webhook-safe rollout.

**Modified**
- `composer.json` / `composer.lock` — add spatie/laravel-permission.
- `config/permission.php` — published.
- `app/Models/User.php` — `HasRoles`; remove custom `hasRole()` (final task); keep consts/helpers.
- `app/Providers/AppServiceProvider.php` — `Gate::before` super-admin.
- `app/Models/Scopes/CawanganScope.php` — permission-based + memoized.
- `bootstrap/app.php` — Spatie middleware aliases; drop `role`→EnsureRole; `UnauthorizedException` → homeRoute.
- `routes/web.php` — `role:` → `permission:`.
- `app/Http/Controllers/KeputusanController.php`, `PermohonanPeguamController.php`, `AgihanSpineController.php`, `UserController.php` — `can()` swaps + dual-write + 8-role dropdown.
- `resources/views/...` (5 sites, §4d of spec) — `@can`.
- `database/seeders/TestUsersSeeder.php` — extend to 8 roles + assignRole.
- `database/seeders/DatabaseSeeder.php` — call RolePermissionSeeder.

---

## Task 1: Install Spatie laravel-permission

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/permission.php` (published)
- Create: Spatie migration set (published)

- [ ] **Step 1: Verify default guard is `web` (pre-flight)**

Run: `php artisan tinker --execute="echo config('auth.defaults.guard');"`
Expected: `web`. If anything else, STOP — Spatie role/permission lookups will silently deny (guard_name mismatch). Resolve before continuing.

- [ ] **Step 2: Require the package**

Run: `composer require spatie/laravel-permission:^7.0`
Expected: composer resolves ^7.x, updates `composer.json` + `composer.lock`. (Package auto-discovers its service provider.)

- [ ] **Step 3: Publish config + migrations**

Run: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
Expected: creates `config/permission.php` and `database/migrations/*_create_permission_tables.php`.

- [ ] **Step 4: Migrate (local dev MySQL)**

Run: `php artisan migrate`
Expected: creates `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/permission.php database/migrations
git commit -m "chore: add spatie/laravel-permission ^7 (config + tables)"
```

---

## Task 2: Add `HasRoles` trait + Gate::before super-admin

The trait is inert now (custom `User::hasRole()` overrides it). `Gate::before` makes `admin` pass every `can()`.

**Files:**
- Modify: `app/Models/User.php:16-19`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the trait to User**

In `app/Models/User.php`, add the import and the trait. Change:

```php
use Illuminate\Notifications\Notifiable;
```
to add below it:
```php
use Spatie\Permission\Traits\HasRoles;
```
And change:
```php
    use HasFactory, Notifiable;
```
to:
```php
    use HasFactory, Notifiable, HasRoles;
```

(Leave the custom `hasRole()` method in place for now — the class method overrides the trait method, preserving current behavior.)

- [ ] **Step 2: Add Gate::before in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add imports at top:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
```

In the `boot()` method, add:

```php
// admin is a super-admin: bypasses every permission check (mirrors the legacy
// "admin is in every role: list" behavior and future-proofs new modules).
// Return null (not false) so non-admins fall through to normal gate checks.
Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
```

- [ ] **Step 3: Verify nothing breaks**

Run: `php artisan test --filter=PermohonanTest`
Expected: PASS (behavior unchanged — custom hasRole still authoritative).

- [ ] **Step 4: Commit**

```bash
git add app/Models/User.php app/Providers/AppServiceProvider.php
git commit -m "feat(rbac): add HasRoles trait + admin Gate::before super-admin"
```

---

## Task 3: RolePermissionSeeder (roles + permissions + matrix)

Mirrors current access exactly. Idempotent. Admin row optional (Gate::before covers it) but we grant `urus.peranan` to admin explicitly for the matrix UI.

**Files:**
- Create: `database/seeders/RolePermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write the seeder**

Create `database/seeders/RolePermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds Spatie roles + permissions to mirror the pre-RBAC EnsureRole/role-const access.
 * Idempotent: safe to re-run every deploy. admin = super-admin via Gate::before, so it is
 * not enumerated per-permission (only granted urus.peranan so the matrix UI resolves).
 */
class RolePermissionSeeder extends Seeder
{
    /** All roles (must match User::ROLE_* + lawyer). */
    private const ROLES = [
        'admin', 'pengarah', 'koordinator', 'pegawai',
        'ppuu', 'pembantu_tadbir', 'ketua_pengarah', 'peguam',
    ];

    /** permission => roles granted (admin omitted — Gate::before). */
    private const MATRIX = [
        'system.view'            => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.view'               => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.create'             => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.update'             => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.keputusan'          => ['pengarah', 'ketua_pengarah'],
        'pengantaraan.manage'    => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'mahkamah.manage'        => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'lampiran.manage'        => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'cetakan.view'           => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'oyd.manage'             => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kpi.view'               => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'laporan.view'           => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'statistik.view'         => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'agihan.manage'          => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'agihan.pengarah'        => ['pengarah'],
        'agihan.ppuu'            => ['ppuu', 'koordinator'],
        'agihan.kp'              => ['ketua_pengarah'],
        'peguam_panel.manage'    => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'peguam.permohonan.view' => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'peguam.semak'           => ['ppuu', 'pembantu_tadbir', 'koordinator'],
        'peguam.sokong'          => ['pengarah'],
        'peguam.keputusan'       => ['ketua_pengarah'],
        'selenggara.pegawai'     => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.poster'      => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.ref_kes'     => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.mahkamah_ref'=> ['pengarah', 'koordinator', 'ketua_pengarah'],
        'urus.pengguna'          => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'audit.view'             => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'menu.selenggara'        => ['pengarah', 'koordinator'],
        'cawangan.view-all'      => ['koordinator', 'ketua_pengarah'],
        'urus.peranan'           => ['admin'],
        'lawyer.area'            => ['peguam'],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (array_keys(self::MATRIX) as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $registrar->forgetCachedPermissions();

        // Build per-role permission lists, then sync.
        $byRole = [];
        foreach (self::MATRIX as $perm => $roles) {
            foreach ($roles as $role) {
                $byRole[$role][] = $perm;
            }
        }
        foreach (self::ROLES as $role) {
            Role::findByName($role, 'web')->syncPermissions($byRole[$role] ?? []);
        }

        $registrar->forgetCachedPermissions();
    }
}
```

- [ ] **Step 2: Wire it into DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, change:

```php
        $this->call([
            DemoUserSeeder::class,
        ]);
```
to:
```php
        $this->call([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
        ]);
```

- [ ] **Step 3: Run the seeder**

Run: `php artisan db:seed --class=RolePermissionSeeder`
Expected: no error; `roles` has 8 rows, `permissions` has 32 rows.

- [ ] **Step 4: Verify counts**

Run: `php artisan tinker --execute="echo \Spatie\Permission\Models\Role::count().' roles, '.\Spatie\Permission\Models\Permission::count().' perms';"`
Expected: `8 roles, 32 perms`.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(rbac): RolePermissionSeeder mirroring legacy access matrix"
```

---

## Task 4: Backfill command (assign roles to existing users)

Assigns each user their Spatie role from the `role` column, fail-loud on unknown values.

**Files:**
- Create: `app/Console/Commands/BackfillUserRoles.php`

- [ ] **Step 1: Write the command**

Create `app/Console/Commands/BackfillUserRoles.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * One-time (idempotent) backfill: assign each user the Spatie role matching their
 * legacy `role` column. Unknown/empty role => safe fallback + loud log.
 */
class BackfillUserRoles extends Command
{
    protected $signature = 'rbac:backfill-roles {--dry}';
    protected $description = 'Assign Spatie roles to existing users from the legacy role column';

    public function handle(): int
    {
        $known = Role::pluck('name')->all();
        $assigned = 0; $fallback = 0;

        User::query()->chunkById(200, function ($users) use ($known, &$assigned, &$fallback) {
            foreach ($users as $user) {
                $role = $user->role;
                if (! $role || ! in_array($role, $known, true)) {
                    $safe = $user->user_type === User::TYPE_LAWYER ? User::ROLE_PEGUAM : User::ROLE_PEGAWAI;
                    $this->warn("User {$user->id} ({$user->email}) role='{$role}' unknown/empty -> fallback '{$safe}'");
                    $role = $safe; $fallback++;
                }
                if (! $this->option('dry')) {
                    $user->syncRoles([$role]);
                }
                $assigned++;
            }
        });

        $this->info("Backfill complete: {$assigned} users processed, {$fallback} fallbacks.".($this->option('dry') ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Dry-run**

Run: `php artisan rbac:backfill-roles --dry`
Expected: prints per-user fallbacks (if any) + summary; assigns nothing.

- [ ] **Step 3: Run for real (local dev)**

Run: `php artisan rbac:backfill-roles`
Expected: "Backfill complete: N users processed, M fallbacks."

- [ ] **Step 4: Verify a known user**

Run: `php artisan tinker --execute="\$u=\App\Models\User::where('user_type','staff')->first(); echo \$u->getRoleNames();"`
Expected: a collection containing the user's role.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillUserRoles.php
git commit -m "feat(rbac): rbac:backfill-roles command (legacy role -> Spatie role)"
```

---

## Task 5: Extend TestUsersSeeder to 8 roles + assign Spatie roles

The regression matrix needs a user per role, each with the Spatie role assigned.

**Files:**
- Modify: `database/seeders/TestUsersSeeder.php`

- [ ] **Step 1: Add the 3 missing roles + assignRole**

In `database/seeders/TestUsersSeeder.php`, replace the `$users` array with all 8 roles:

```php
        $users = [
            ['email' => 'admin@test.local',          'name' => 'Test Admin',          'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_ADMIN],
            ['email' => 'pengarah@test.local',       'name' => 'Test Pengarah',       'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PENGARAH],
            ['email' => 'koordinator@test.local',    'name' => 'Test Koordinator',    'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_KOORDINATOR],
            ['email' => 'pegawai@test.local',        'name' => 'Test Pegawai',        'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PEGAWAI],
            ['email' => 'ppuu@test.local',           'name' => 'Test PPUU',           'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PPUU],
            ['email' => 'pembantu@test.local',       'name' => 'Test Pembantu',       'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PEMBANTU_TADBIR],
            ['email' => 'kp@test.local',             'name' => 'Test Ketua Pengarah', 'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_KETUA_PENGARAH],
            ['email' => 'peguam@test.local',         'name' => 'Test Peguam',         'user_type' => User::TYPE_LAWYER, 'role' => User::ROLE_PEGUAM, 'id_peguam_panel' => $panelKp],
        ];
```

Then change the `foreach` body to also assign the Spatie role after upsert:

```php
        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                array_merge($u, [
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'must_change_password' => false,
                ])
            );
            $user->syncRoles([$u['role']]);
        }
```

- [ ] **Step 2: Verify it runs (needs roles seeded first)**

Run: `php artisan db:seed --class=RolePermissionSeeder && php artisan db:seed --class=TestUsersSeeder`
Expected: no error; 8 test users exist, each with a Spatie role.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/TestUsersSeeder.php
git commit -m "test(rbac): seed all 8 roles + assign Spatie roles to test users"
```

---

## Task 6: Route×role regression matrix test (the safety net)

Write it NOW, against today's behavior (EnsureRole + custom hasRole still live). It must be GREEN now and stay GREEN through every later task.

**Files:**
- Create: `tests/Feature/Batch7RbacMatrixTest.php`

- [ ] **Step 1: Write the matrix test**

Create `tests/Feature/Batch7RbacMatrixTest.php`. It runs against live MySQL (repo convention), seeds the 8 test users, and asserts allow/deny per role on representative routes for each granular gate. Deny semantics: wrong-area authenticated → redirect to homeRoute; KeputusanController denial → 403; Permohonan* denial → 302 (back) with errors.

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 7 RBAC route×role regression matrix. Runs against live iguaman_2in1 (repo convention).
 * GREEN both before and after the EnsureRole->Spatie swap (behavior-preserving).
 */
class Batch7RbacMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        (new TestUsersSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $map = [
            'admin' => 'admin@test.local', 'pengarah' => 'pengarah@test.local',
            'koordinator' => 'koordinator@test.local', 'pegawai' => 'pegawai@test.local',
            'ppuu' => 'ppuu@test.local', 'pembantu_tadbir' => 'pembantu@test.local',
            'ketua_pengarah' => 'kp@test.local', 'peguam' => 'peguam@test.local',
        ];
        return User::where('email', $map[$role])->firstOrFail();
    }

    /** GET routes: allowed => not a redirect to homeRoute; denied => redirect to homeRoute. */
    public static function getMatrix(): array
    {
        // [routeName, [allowed roles]]
        return [
            'system.utama'   => ['system.utama', ['admin','pengarah','koordinator','pegawai','ppuu','pembantu_tadbir','ketua_pengarah']],
            'kes.index'      => ['kes.index',    ['admin','pengarah','koordinator','pegawai','ppuu','pembantu_tadbir','ketua_pengarah']],
            'pegawai.index'  => ['pegawai.index',['admin','pengarah','koordinator','ketua_pengarah']],
            'pengguna.index' => ['pengguna.index',['admin','pengarah','koordinator','ketua_pengarah']],
            'audit.index'    => ['audit.index',  ['admin','pengarah','koordinator','ketua_pengarah']],
            'peguam.dashboard'=>['peguam.dashboard',['peguam']],
        ];
    }

    /** @dataProvider getMatrix */
    public function test_get_route_access(string $routeName, array $allowed): void
    {
        foreach (['admin','pengarah','koordinator','pegawai','ppuu','pembantu_tadbir','ketua_pengarah','peguam'] as $role) {
            $u = $this->user($role);
            $res = $this->actingAs($u)->get(route($routeName));
            if (in_array($role, $allowed, true)) {
                $this->assertNotEquals(302, $res->status(), "$role should access $routeName");
            } else {
                $res->assertRedirect(route($u->homeRoute()));
            }
        }
    }

    public function test_wrong_area_redirects_not_403(): void
    {
        // lawyer hitting a staff route -> redirect to peguam.dashboard (not 403)
        $this->actingAs($this->user('peguam'))->get(route('kes.index'))
            ->assertRedirect(route('peguam.dashboard'));
        // staff hitting lawyer route -> redirect to system.utama
        $this->actingAs($this->user('pegawai'))->get(route('peguam.dashboard'))
            ->assertRedirect(route('system.utama'));
    }
}
```

- [ ] **Step 2: Run it (GREEN against current EnsureRole)**

Run: `php artisan test --filter=Batch7RbacMatrixTest`
Expected: PASS. (If RED now, the matrix or expected access is wrong — fix before proceeding; this is the baseline.)

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Batch7RbacMatrixTest.php
git commit -m "test(rbac): route×role regression matrix (baseline green pre-swap)"
```

---

## Task 7: Swap in-controller role checks to permissions

Preserve deny contracts: Keputusan = 403; Permohonan* = soft 302 withErrors.

**Files:**
- Modify: `app/Http/Controllers/KeputusanController.php:19-26`
- Modify: `app/Http/Controllers/PermohonanPeguamController.php:50,67,88`
- Modify: `app/Http/Controllers/AgihanSpineController.php:159`

- [ ] **Step 1: KeputusanController gate → can('kes.keputusan')**

In `app/Http/Controllers/KeputusanController.php`, change the `gate()` method:

```php
    private function gate(Request $request): void
    {
        abort_unless(
            $request->user()->can('kes.keputusan'),
            403,
            'Hanya Pengarah / Ketua Pengarah boleh membuat keputusan ini.'
        );
    }
```

(The `use App\Models\User;` import may now be unused here — remove it only if no other reference remains in the file.)

- [ ] **Step 2: PermohonanPeguamController → can(), keep soft 302**

In `app/Http/Controllers/PermohonanPeguamController.php`:

Line ~50 (`semak`):
```php
        if (! $request->user()->can('peguam.semak')) {
            return back()->withErrors(['akses' => 'Hanya PPUU / Pembantu Tadbir boleh menyemak permohonan.']);
        }
```
Line ~67 (`sokong`):
```php
        if (! $request->user()->can('peguam.sokong')) {
            return back()->withErrors(['akses' => 'Hanya Pengarah boleh memberi sokongan.']);
        }
```
Line ~88 (`keputusan`):
```php
        if (! $request->user()->can('peguam.keputusan')) {
            return back()->withErrors(['akses' => 'Hanya Ketua Pengarah boleh membuat keputusan muktamad.']);
        }
```

- [ ] **Step 3: AgihanSpineController closure → array-form hasRole**

In `app/Http/Controllers/AgihanSpineController.php:159`, change:

```php
        $is = fn (...$roles) => $user->hasRole(...$roles);
```
to (array form, Spatie-trait-safe once custom method is removed; also works with custom method):
```php
        $is = fn (array $roles) => $user->hasRole($roles);
```
and update its 4 call sites (lines ~162-166) to pass arrays:
```php
            $status === StatusAgihan::BARU_PENGARAH && $is([User::ROLE_PENGARAH, User::ROLE_ADMIN]) => 'pengarah_baru',
            in_array($status, [StatusAgihan::DIAGIH_PPUU, StatusAgihan::PPUU_AGIH_SEMULA, StatusAgihan::KELULUSAN_KP_SEMULA], true)
                && $is([User::ROLE_PPUU, User::ROLE_KOORDINATOR, User::ROLE_ADMIN]) => 'ppuu_pilih',
            $status === StatusAgihan::SOKONGAN_PENGARAH && $is([User::ROLE_PENGARAH, User::ROLE_ADMIN]) => 'pengarah_sokong',
            $status === StatusAgihan::KELULUSAN_KP && $is([User::ROLE_KETUA_PENGARAH, User::ROLE_ADMIN]) => 'kp_keputusan',
```

(This is UI-affordance only; array form is sufficient and stays correct after the custom `hasRole()` is removed.)

- [ ] **Step 4: Run matrix + permohonan tests**

Run: `php artisan test --filter=Batch7RbacMatrixTest && php artisan test --filter=PermohonanTest`
Expected: PASS (admin still passes via Gate::before; approvers via `kes.keputusan`).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/KeputusanController.php app/Http/Controllers/PermohonanPeguamController.php app/Http/Controllers/AgihanSpineController.php
git commit -m "feat(rbac): controller checks use can() (keputusan 403, permohonan soft-302)"
```

---

## Task 8: Swap route gating + Spatie middleware + wrong-role redirect

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Register Spatie aliases, drop EnsureRole alias, render UnauthorizedException → homeRoute**

In `bootstrap/app.php`, replace the `withMiddleware` alias block:

```php
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
```
with:
```php
        $middleware->alias([
            'role'                => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'          => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'  => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
```

And in `withExceptions`, add an authenticated-user redirect for Spatie's UnauthorizedException (mirrors EnsureRole — wrong access → own dashboard, not a 403 page):

```php
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $user = $request->user();

            return $user
                ? redirect()->route($user->homeRoute())
                : redirect()->route('system.login');
        });
    })->create();
```

- [ ] **Step 2: Swap route middleware in web.php**

In `routes/web.php`:

Outer staff group (line ~60):
```php
Route::middleware(['auth', 'permission:system.view'])->group(function () {
```
Selenggara subgroup (line ~122) — replace the single `role:` wrapper with per-route permission middleware. Change the group to plain `Route::group` and add `->middleware('permission:...')` to each resource block, e.g.:
```php
    Route::middleware('permission:selenggara.pegawai')->group(function () {
        Route::get('/pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
        // ... pegawai create/store/edit/update/destroy ...
    });
    Route::middleware('permission:selenggara.poster')->group(function () { /* poster routes */ });
    Route::middleware('permission:selenggara.ref_kes')->group(function () { /* ref-kes routes */ });
    Route::middleware('permission:selenggara.mahkamah_ref')->group(function () { /* mahkamah-ref routes */ });
    Route::middleware('permission:urus.pengguna')->group(function () { /* pengguna routes */ });
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->middleware('permission:audit.view');
```
Agihan spine (lines ~172-176): change each `role:` to the matching permission (NOTE: Spatie uses `|` for multiple, but these are single permissions):
```php
    ...->middleware('permission:agihan.pengarah');   // pengarah-terima, pengarah-tolak, pengarah-keputusan
    ...->middleware('permission:agihan.ppuu');        // ppuu-pilih
    ...->middleware('permission:agihan.kp');          // kp-keputusan
```
Legacy AgihanController (form/store/beban) + agihan.maklumat: covered by the staff baseline (`system.view`); optionally add `->middleware('permission:agihan.manage')` for explicitness.
Lawyer area (line ~191):
```php
Route::middleware(['auth', 'permission:lawyer.area'])->prefix('peguam')->group(function () {
```

- [ ] **Step 3: Run the full matrix + permohonan**

Run: `php artisan test --filter=Batch7RbacMatrixTest && php artisan test --filter=PermohonanTest`
Expected: PASS. Wrong-area still redirects (UnauthorizedException render); selenggara gated correctly per permission.

- [ ] **Step 4: Commit**

```bash
git add bootstrap/app.php routes/web.php
git commit -m "feat(rbac): routes gate via permission: middleware; wrong-role redirects preserved"
```

---

## Task 9: CawanganScope → permission-based + memoized

**Files:**
- Modify: `app/Models/Scopes/CawanganScope.php`
- Create: `tests/Feature/Batch7ScopeTest.php`

- [ ] **Step 1: Write the scope test first**

Create `tests/Feature/Batch7ScopeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch7ScopeTest extends TestCase
{
    private const TAGA = 'PHPUNITA';
    private const TAGB = 'PHPUNITB';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        $this->cleanup();
        Form::create(['nama' => 'A', 'cawangan' => self::TAGA, 'diterima' => '', 'created_at' => now()]);
        Form::create(['nama' => 'B', 'cawangan' => self::TAGB, 'diterima' => '', 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        Form::whereIn('cawangan', [self::TAGA, self::TAGB])->delete();
        User::where('email', 'like', '%@scope.local')->delete();
    }

    private function makeUser(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => "Scope $role", 'email' => "$role@scope.local",
            'password' => Hash::make('x'), 'user_type' => 'staff',
            'role' => $role, 'cawangan' => $cawangan, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);
        return $u;
    }

    public function test_scoped_role_sees_only_own_branch(): void
    {
        $this->actingAs($this->makeUser('pegawai', self::TAGA));
        $rows = Form::whereIn('cawangan', [self::TAGA, self::TAGB])->get();
        $this->assertCount(1, $rows);
        $this->assertSame(self::TAGA, $rows->first()->cawangan);
    }

    public function test_view_all_role_sees_both(): void
    {
        $this->actingAs($this->makeUser('koordinator', self::TAGA));
        $rows = Form::whereIn('cawangan', [self::TAGA, self::TAGB])->get();
        $this->assertCount(2, $rows);
    }

    public function test_admin_super_sees_both(): void
    {
        $this->actingAs($this->makeUser('admin', self::TAGA));
        $this->assertCount(2, Form::whereIn('cawangan', [self::TAGA, self::TAGB])->get());
    }
}
```

- [ ] **Step 2: Run it — expect FAIL for view_all/admin**

Run: `php artisan test --filter=Batch7ScopeTest`
Expected: `test_scoped_role_sees_only_own_branch` PASS (current behavior); `test_view_all_role_sees_both` and `test_admin_super_sees_both` may PASS already (koordinator/admin are in current bypass list) — confirm baseline. (Today koordinator+admin bypass via SCOPED_ROLES exclusion, so all three likely PASS now. This locks behavior before the rewrite.)

- [ ] **Step 3: Rewrite CawanganScope to permission-based + memoized**

Replace `app/Models/Scopes/CawanganScope.php` body:

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Branch isolation (legacy: WHERE cawangan = session).
 * Staff with a branch see only their branch UNLESS they hold `cawangan.view-all`.
 * Lawyers / no-branch / view-all -> see everything. Permission resolved ONCE per request.
 */
class CawanganScope implements Scope
{
    /** Per-request memo of can('cawangan.view-all') keyed by user id. */
    private static array $viewAllMemo = [];

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->isStaff() && filled($user->cawangan) && ! $this->canViewAll($user)) {
            $builder->where($model->getTable().'.cawangan', $user->cawangan);
        }
    }

    private function canViewAll($user): bool
    {
        return self::$viewAllMemo[$user->id] ??= $user->can('cawangan.view-all');
    }
}
```

- [ ] **Step 4: Run scope + matrix + permohonan tests**

Run: `php artisan test --filter=Batch7ScopeTest && php artisan test --filter=Batch7RbacMatrixTest && php artisan test --filter=PermohonanTest`
Expected: PASS (behavior identical; admin via Gate::before makes `can('cawangan.view-all')` true).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scopes/CawanganScope.php tests/Feature/Batch7ScopeTest.php
git commit -m "feat(rbac): CawanganScope branch-bypass via cawangan.view-all (memoized)"
```

---

## Task 10: Blade affordance migration + dual-write + 8-role dropdown

**Files:**
- Modify: `resources/views/permohonan-peguam/show.blade.php:50,74,92`
- Modify: `resources/views/kes/show.blade.php:166`
- Modify: `resources/views/layouts/staff.blade.php:74`
- Modify: `resources/views/system/utama.blade.php:6`
- Modify: `app/Http/Controllers/UserController.php` (ROLES const + store/update dual-write)
- Modify: `app/Http/Controllers/PermohonanPeguamController.php:~187` (provisionLogin dual-write)

- [ ] **Step 1: Convert view checks to @can**

`resources/views/permohonan-peguam/show.blade.php` — replace the three `@if(... hasRole(...))` (lines 50/74/92) with:
```blade
@can('peguam.semak')   {{-- line ~50 wrapper --}}
@can('peguam.sokong')  {{-- line ~74 wrapper --}}
@can('peguam.keputusan'){{-- line ~92 wrapper --}}
```
(keep the matching `@endif` → `@endcan`).

`resources/views/kes/show.blade.php:166` — replace `@if(auth()->user()->hasRole('pengarah','ketua_pengarah','admin'))` with `@can('kes.keputusan')` (+ `@endcan`).

`resources/views/layouts/staff.blade.php:74` — replace `@if(auth()->user()->hasRole('admin','pengarah','koordinator'))` (the Pentadbiran+Selenggara sidebar block) with `@can('menu.selenggara')` (+ `@endcan`).

`resources/views/system/utama.blade.php:6` — replace the `$isSupervisor = ... hasRole('admin','pengarah','koordinator')` usage; gate the tiles with `@can('menu.selenggara')`. If line 6 computes a `$isSupervisor` variable used later, replace its later `@if($isSupervisor)` blocks with `@can('menu.selenggara')` and delete the variable.

Leave display-only `{{ strtoupper($user->role) }}` (staff.blade.php:24) and `$p->role` label (agihan/maklumat:65) untouched.

- [ ] **Step 2: UserController — 8-role dropdown + dual-write**

In `app/Http/Controllers/UserController.php`, replace the `ROLES` const with all 8 roles:
```php
    public const ROLES = [
        User::ROLE_ADMIN => 'Admin',
        User::ROLE_PENGARAH => 'Pengarah',
        User::ROLE_KOORDINATOR => 'Koordinator',
        User::ROLE_PEGAWAI => 'Pegawai',
        User::ROLE_PPUU => 'PPUU',
        User::ROLE_PEMBANTU_TADBIR => 'Pembantu Tadbir',
        User::ROLE_KETUA_PENGARAH => 'Ketua Pengarah',
        User::ROLE_PEGUAM => 'Peguam',
    ];
```
In `store()`, after `User::create([...])` (line ~57), add the Spatie assignment in the same flow:
```php
        $user->syncRoles([$data['role']]);
```
In `update()`, after `$user->update($payload);` (line ~88), add:
```php
        $user->syncRoles([$data['role']]);
```
(`UserRequest` must allow the new role values — see Step 3.)

- [ ] **Step 3: Widen UserRequest role validation**

In `app/Http/Requests/UserRequest.php`, ensure the `role` rule accepts all 8 role consts. Change the role rule to:
```php
            'role' => ['required', Rule::in(array_keys(\App\Http\Controllers\UserController::ROLES))],
```
(Add `use Illuminate\Validation\Rule;` if missing.)

- [ ] **Step 4: PermohonanPeguamController::provisionLogin dual-write**

In `app/Http/Controllers/PermohonanPeguamController.php` near line 187 where the lawyer login is created with `'role' => User::ROLE_PEGUAM`, capture the created user and assign the Spatie role:
```php
        $login = User::create([
            // ... existing fields including 'user_type' => User::TYPE_LAWYER, 'role' => User::ROLE_PEGUAM,
        ]);
        $login->syncRoles([User::ROLE_PEGUAM]);
```

- [ ] **Step 5: Run full suite**

Run: `php artisan test`
Expected: PASS. (All prior batch-7 tests + existing Phase tests green.)

- [ ] **Step 6: Commit**

```bash
git add resources/views/permohonan-peguam/show.blade.php resources/views/kes/show.blade.php resources/views/layouts/staff.blade.php resources/views/system/utama.blade.php app/Http/Controllers/UserController.php app/Http/Requests/UserRequest.php app/Http/Controllers/PermohonanPeguamController.php
git commit -m "feat(rbac): @can view affordances + role dual-write + 8-role user dropdown"
```

---

## Task 11: Remove custom `User::hasRole()` (Spatie trait takes over)

Do this only after Tasks 7–10 (no variadic callers remain).

**Files:**
- Modify: `app/Models/User.php:57-60`

- [ ] **Step 1: Confirm no variadic callers remain**

Run: `rg "hasRole\(" app resources | rg -v "hasRole\(\[" | rg -v "->can\("`
Expected: no variadic `hasRole('a','b')` results remain (only array-form `hasRole([...])` or none). If any remain, convert them first.

- [ ] **Step 2: Remove the custom method**

In `app/Models/User.php`, delete the custom method:
```php
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
```
(Spatie's `HasRoles::hasRole()` now applies — array-form calls remain valid.)

- [ ] **Step 3: Run full suite**

Run: `php artisan test`
Expected: PASS — Spatie's trait `hasRole()` now authoritative; matrix + scope + permohonan all green.

- [ ] **Step 4: Commit**

```bash
git add app/Models/User.php
git commit -m "refactor(rbac): remove custom hasRole(); Spatie trait is authoritative"
```

---

## Task 12: Admin UI — Peranan (roles) + Akses (permission matrix)

**Files:**
- Create: `app/Http/Controllers/RoleController.php`
- Create: `app/Http/Controllers/RolePermissionController.php`
- Create: `resources/views/peranan/index.blade.php`, `form.blade.php`, `akses.blade.php`
- Modify: `routes/web.php` (add gated routes)
- Modify: `resources/views/layouts/staff.blade.php` (sidebar entry)
- Create: `tests/Feature/Batch7AdminUiTest.php`

- [ ] **Step 1: Write the admin-UI test first**

Create `tests/Feature/Batch7AdminUiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch7AdminUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        (new TestUsersSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Role::where('name', 'like', 'ujian_%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function admin(): User { return User::where('email', 'admin@test.local')->firstOrFail(); }
    private function pegawai(): User { return User::where('email', 'pegawai@test.local')->firstOrFail(); }

    public function test_non_admin_cannot_reach_peranan(): void
    {
        $this->actingAs($this->pegawai())->get(route('peranan.index'))
            ->assertRedirect(route('system.utama'));
    }

    public function test_admin_sees_peranan(): void
    {
        $this->actingAs($this->admin())->get(route('peranan.index'))->assertOk();
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $id = Role::findByName('pegawai', 'web')->id;
        $this->actingAs($this->admin())->delete(route('peranan.destroy', $id))
            ->assertSessionHasErrors();
        $this->assertNotNull(Role::find($id));
    }

    public function test_matrix_update_changes_permissions(): void
    {
        $role = Role::findByName('koordinator', 'web');
        $perms = $role->permissions->pluck('name')->reject(fn ($p) => $p === 'audit.view')->values()->all();
        $this->actingAs($this->admin())->put(route('peranan.akses.update', $role->id), ['permissions' => $perms])
            ->assertRedirect();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(Role::findByName('koordinator', 'web')->hasPermissionTo('audit.view'));
        // restore
        (new RolePermissionSeeder())->run();
    }
}
```

- [ ] **Step 2: Run — expect FAIL (routes/controllers missing)**

Run: `php artisan test --filter=Batch7AdminUiTest`
Expected: FAIL (`Route [peranan.index] not defined`).

- [ ] **Step 3: RoleController**

Create `app/Http/Controllers/RoleController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Peranan (role) management. Gated permission:urus.peranan (admin-only via seeder + Gate::before).
class RoleController extends Controller
{
    /** Seeded system roles — cannot be renamed/deleted via UI. */
    public const SYSTEM_ROLES = [
        'admin', 'pengarah', 'koordinator', 'pegawai',
        'ppuu', 'pembantu_tadbir', 'ketua_pengarah', 'peguam',
    ];

    public function index(): View
    {
        return view('peranan.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    public function create(): View
    {
        return view('peranan.form', ['role' => new Role(), 'mode' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50', 'unique:roles,name']]);
        Role::findOrCreate($data['name'], 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', 0, Audit::INSERT, "Peranan ditambah: {$data['name']}");

        return redirect()->route('peranan.index')->with('status', 'Peranan ditambah.');
    }

    public function edit(Role $role): View
    {
        return view('peranan.form', ['role' => $role, 'mode' => 'edit']);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->withErrors(['name' => 'Peranan sistem tidak boleh dinamakan semula.']);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:50', "unique:roles,name,{$role->id}"]]);
        $role->update(['name' => $data['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('peranan.index')->with('status', 'Peranan dikemaskini.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->withErrors(['peranan' => 'Peranan sistem tidak boleh dipadam.']);
        }
        if ($role->users()->exists()) {
            return back()->withErrors(['peranan' => 'Peranan ini masih digunakan oleh pengguna.']);
        }
        $name = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', 0, Audit::DELETE, "Peranan dipadam: {$name}");

        return redirect()->route('peranan.index')->with('status', 'Peranan dipadam.');
    }
}
```

- [ ] **Step 4: RolePermissionController**

Create `app/Http/Controllers/RolePermissionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Akses — per-role permission matrix. Gated permission:urus.peranan.
class RolePermissionController extends Controller
{
    public function edit(Role $role): View
    {
        $all = Permission::orderBy('name')->get();
        $grouped = $all->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('peranan.akses', [
            'role' => $role,
            'grouped' => $grouped,
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', $role->id, Audit::UPDATE, "Akses peranan dikemaskini: {$role->name}");

        return redirect()->route('peranan.index')->with('status', 'Akses peranan dikemaskini.');
    }
}
```

- [ ] **Step 5: Routes (gated permission:urus.peranan)**

In `routes/web.php`, inside the authenticated staff area, add a gated group:

```php
    Route::middleware('permission:urus.peranan')->group(function () {
        Route::get('/peranan', [\App\Http\Controllers\RoleController::class, 'index'])->name('peranan.index');
        Route::get('/peranan/create', [\App\Http\Controllers\RoleController::class, 'create'])->name('peranan.create');
        Route::post('/peranan', [\App\Http\Controllers\RoleController::class, 'store'])->name('peranan.store');
        Route::get('/peranan/{role}/edit', [\App\Http\Controllers\RoleController::class, 'edit'])->name('peranan.edit')->whereNumber('role');
        Route::put('/peranan/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('peranan.update')->whereNumber('role');
        Route::delete('/peranan/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('peranan.destroy')->whereNumber('role');
        Route::get('/peranan/{role}/akses', [\App\Http\Controllers\RolePermissionController::class, 'edit'])->name('peranan.akses.edit')->whereNumber('role');
        Route::put('/peranan/{role}/akses', [\App\Http\Controllers\RolePermissionController::class, 'update'])->name('peranan.akses.update')->whereNumber('role');
    });
```

(`{role}` resolves the Spatie `Role` model via implicit binding by id.)

- [ ] **Step 6: Views**

Create `resources/views/peranan/index.blade.php` — extends `layouts.staff`, table of roles (name, permission count, user count) with Edit / Akses / Delete actions (hide delete + rename for `systemRoles`), a "Tambah Peranan" button, and the `@error`/`session('status')` flash. Create `resources/views/peranan/form.blade.php` — name field (+ CSRF, `@method('PUT')` in edit). Create `resources/views/peranan/akses.blade.php` — a form posting to `peranan.akses.update` with a checkbox per permission grouped by `$grouped` module key, pre-checked from `$assigned`. Follow the existing `pengguna/index.blade.php` + `pengguna/form.blade.php` markup conventions (same `ws-*` classes, layout, buttons).

Example `akses.blade.php` body:
```blade
@extends('layouts.staff')
@section('content')
<h1 class="ws-title">Akses Peranan: {{ ucfirst($role->name) }}</h1>
<form method="POST" action="{{ route('peranan.akses.update', $role) }}">
    @csrf @method('PUT')
    @foreach($grouped as $module => $perms)
        <fieldset class="ws-card">
            <legend>{{ strtoupper($module) }}</legend>
            @foreach($perms as $perm)
                <label>
                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                        @checked(in_array($perm->name, $assigned, true))>
                    {{ $perm->name }}
                </label>
            @endforeach
        </fieldset>
    @endforeach
    <button type="submit" class="ws-btn ws-btn--primary">Simpan Akses</button>
</form>
@endsection
```

- [ ] **Step 7: Sidebar entry**

In `resources/views/layouts/staff.blade.php`, inside the `@can('menu.selenggara')` block (or a new `@can('urus.peranan')` block), add a nav link:
```blade
@can('urus.peranan')
    <a href="{{ route('peranan.index') }}" class="ws-nav__link">Peranan &amp; Akses</a>
@endcan
```

- [ ] **Step 8: Run admin-UI test + full suite**

Run: `php artisan test --filter=Batch7AdminUiTest && php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/RoleController.php app/Http/Controllers/RolePermissionController.php resources/views/peranan routes/web.php resources/views/layouts/staff.blade.php tests/Feature/Batch7AdminUiTest.php
git commit -m "feat(rbac): Peranan + Akses admin UI (role CRUD + permission matrix)"
```

---

## Task 13: Seeder/backfill test + deployment runbook

**Files:**
- Create: `tests/Feature/Batch7SeederTest.php`
- Create: `docs/superpowers/runbooks/2026-06-30-batch7-rbac-deploy.md`

- [ ] **Step 1: Seeder/backfill assertions**

Create `tests/Feature/Batch7SeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch7SeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        User::where('email', 'like', '%@seedtest.local')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_all_roles_and_permissions_exist(): void
    {
        $this->assertSame(8, Role::count());
        $this->assertSame(32, Permission::count());
    }

    public function test_admin_can_everything_via_gate_before(): void
    {
        $admin = User::create([
            'name' => 'A', 'email' => 'admin@seedtest.local', 'password' => Hash::make('x'),
            'user_type' => 'staff', 'role' => 'admin', 'is_active' => true,
        ]);
        $admin->syncRoles(['admin']);
        foreach (Permission::pluck('name') as $perm) {
            $this->assertTrue($admin->can($perm), "admin should pass $perm via Gate::before");
        }
    }

    public function test_approver_permissions_match_matrix(): void
    {
        $this->assertTrue(Role::findByName('pengarah', 'web')->hasPermissionTo('kes.keputusan'));
        $this->assertTrue(Role::findByName('ketua_pengarah', 'web')->hasPermissionTo('peguam.keputusan'));
        $this->assertFalse(Role::findByName('pegawai', 'web')->hasPermissionTo('kes.keputusan'));
        $this->assertFalse(Role::findByName('pembantu_tadbir', 'web')->hasPermissionTo('selenggara.pegawai'));
    }

    public function test_backfill_assigns_and_falls_back(): void
    {
        $known = User::create([
            'name' => 'K', 'email' => 'pengarah@seedtest.local', 'password' => Hash::make('x'),
            'user_type' => 'staff', 'role' => 'pengarah', 'is_active' => true,
        ]);
        $unknown = User::create([
            'name' => 'U', 'email' => 'weird@seedtest.local', 'password' => Hash::make('x'),
            'user_type' => 'staff', 'role' => 'legacy_unknown', 'is_active' => true,
        ]);

        $this->artisan('rbac:backfill-roles')->assertSuccessful();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($known->fresh()->hasRole('pengarah'));
        $this->assertTrue($unknown->fresh()->hasRole('pegawai')); // safe fallback
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=Batch7SeederTest`
Expected: PASS.

- [ ] **Step 3: Write the deployment runbook**

Create `docs/superpowers/runbooks/2026-06-30-batch7-rbac-deploy.md` documenting the webhook-safe rollout from spec §9: `php artisan down` (SSH) → push (webhook pull + composer install) → `php artisan migrate` → `php artisan db:seed --class=RolePermissionSeeder` → `php artisan permission:cache-reset` → `php artisan rbac:backfill-roles` → `php artisan permission:cache-reset` → `php artisan config:clear` (+ `route:clear`/`view:clear`; rebuild `config:cache` only if used) → `php artisan up`. Include rollback: revert release commit (legacy `role:`/EnsureRole + custom hasRole restored; `users.role` is the always-valid fallback). Note `composer.json` + `composer.lock` must be committed together; Hostinger SSH port 65002.

- [ ] **Step 4: Run the FULL suite (final gate)**

Run: `php artisan test`
Expected: ALL PASS (Batch7 matrix/scope/seeder/adminui + existing Phase/Permohonan/Hardening tests).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Batch7SeederTest.php docs/superpowers/runbooks/2026-06-30-batch7-rbac-deploy.md
git commit -m "test(rbac): seeder/backfill assertions + deploy runbook"
```

---

## Self-review (author checklist — completed)

**Spec coverage:** §3 dep → T1; §7a HasRoles/Gate::before/guard → T1,T2; §7b seeder → T3; §7c backfill → T4; §8a 8-role fixtures + matrix → T5,T6; §7e controller swaps → T7; §7d routes + UnauthorizedException + pipe → T8; §7f scope → T9; §4d/§7h views + §5.5 dual-write + §1a/#19 dropdown → T10; §5.1 remove hasRole → T11; §7g admin UI + role-delete guard → T12; §8b seeder tests + §9 runbook → T13. All spec sections mapped.

**Placeholder scan:** the only deliberately non-code prose is the views markup in T12 Step 6 (index/form follow the existing `pengguna/*` blade conventions; akses.blade.php shown in full). All code steps show real code.

**Type/name consistency:** role names, permission keys, and `User::ROLE_*` consts match across seeder (T3), command (T4), tests (T6/T13), and controllers. `menu.selenggara`, `cawangan.view-all`, `urus.peranan`, `kes.keputusan`, `peguam.semak/sokong/keputusan`, `agihan.pengarah/ppuu/kp` used identically in seeder + routes + views + tests. `homeRoute()`, `isStaff()`, `syncRoles()`, `can()` signatures verified against the real files.

**Known runtime note:** an auto-commit hook in this environment may bundle Write/Edit changes into commits with generated messages — the explicit `git commit` steps still document intended atomic boundaries; verify history after execution.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-30-batch7-rbac-refactor.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, two-stage review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session with checkpoints.

Which approach?
