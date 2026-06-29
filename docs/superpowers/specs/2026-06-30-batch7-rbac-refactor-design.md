# Batch 7 — DB-driven RBAC (full-app refactor) — Design Spec

**Date:** 2026-06-30
**Status:** Approved (design); hardened after adversarial review; pending final spec review
**Scope:** Replace the hardcoded role-const + `EnsureRole` authorization across the **entire** 2in1 app with Spatie laravel-permission DB-driven roles + permissions, plus an admin UI to manage them. Foundation for the iGuaman Janji Temu / Khidmat Nasihat port (batches 8–13).

> Part of the larger port plan: `context/port-plan-iguaman-janjitemu.md`. This spec covers **batch 7 only**.
> Hardened from a 4-critic adversarial review (29 findings: 3 critical, 9 high, 10 medium, 7 low) — resolutions folded in below; see §14 for the traceability list.

---

## 1. Goals / Non-goals

**Goals**
- Move every authorization decision (route gating + in-controller checks + view affordances + scope role-lists) from hardcoded `User::ROLE_*` consts to DB-managed Spatie roles + permissions.
- **Behavior-preserving at cutover**, with TWO documented, intentional exceptions (see §1a). Everything else identical to today.
- Admin UI: manage roles + per-role permission matrix (ports source `senarai-peranan` + `akses-pengguna`).
- Redesign `CawanganScope` so branch-bypass is a permission (`cawangan.view-all`), not a hardcoded role list.

**Non-goals (deferred)**
- `awam` public user_type / public portal → batch 13.
- New advisory permissions → added in their own batches.
- Dropping the `users.role` column → kept this release (display + denormalized query mirror + rollback fallback); removal is a later cleanup batch.
- Per-user direct (non-role) permissions.
- Teams / multi-guard Spatie features.
- Finer-grained per-action kes permissions (read vs write split) — mirror today's coarse gating.

**1a. Intentional behavior changes (only these two)**
1. **UserController role dropdown** newly exposes the 3 tier roles (ppuu, pembantu_tadbir, ketua_pengarah) as assignable (today only 5 are selectable). Deliberate — they are live authz roles. (§7g, finding #19)
2. Nothing else. Wrong-role redirect, soft-deny contracts, and branch-bypass set are all **preserved** (§7d, §7e, §7f).

---

## 2. Locked decisions (from brainstorm)

| # | Decision |
|---|---|
| RBAC model | DB-driven roles + permissions (not hardcoded consts) |
| RBAC reach | **Full-app refactor** — batches 1–6 + new modules all gate via DB |
| RBAC engine | **Spatie laravel-permission `^7.0`** (verified: Laravel 12/13, PHP 8.3+) |
| `users.role` | **Kept** as denormalized mirror + display + rollback fallback; Spatie is authz source of truth |
| `CawanganScope` | **Permission-based** — branch filter applies unless user has `cawangan.view-all` |
| admin role | **Super-admin via `Gate::before`** — admin bypasses all permission checks (§7a) |
| Sequencing | RBAC is its own batch 7; masters move to batch 8; rest renumber |

---

## 3. Dependency

`composer require spatie/laravel-permission:^7.0`

- ~60M installs, actively maintained. **Not** Filament/Breeze/Jetstream → within project rules.
- Publishes `config/permission.php` + one migration set (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`).
- Adds `HasRoles` trait to `User`.
- Single guard: `web`. **Pre-flight (finding #16):** confirm `config('auth.defaults.guard') === 'web'` and the `users` provider uses the `web` guard. If not, Spatie's `can()/hasRole()` silently deny everything (guard_name mismatch). All seeded roles/permissions use `guard_name = 'web'`.
- **Commit `composer.json` AND `composer.lock` together** (finding #29). composer.json currently has no spatie entry. The Hostinger webhook runs `composer install` automatically on push; a missing/mismatched lock risks a half-updated `vendor/` while new `HasRoles`-referencing code is already live → fatal. See §9 for the webhook-safe rollout.
- **Cost flagged:** +1 dependency, +5 tables, published config, one-time role-assignment backfill. Justified vs hand-rolling a security-critical authz cache.

---

## 4. Current authorization surface (what we are migrating)

Extracted and verified against `routes/web.php`, `EnsureRole.php`, `User.php`, `CawanganScope.php`, controllers, views, and seeders.

### 4a. Route gating today
| Group | Middleware (current) | Members |
|---|---|---|
| Staff area (rekod-kes + panel) | `role:admin,pengarah,koordinator,pegawai,ppuu,pembantu_tadbir,ketua_pengarah` | all 7 staff roles |
| Selenggara subgroup (pegawai/poster/ref-kes/mahkamah-ref/pengguna/audit) | `role:admin,pengarah,koordinator,ketua_pengarah` | supervisory 4 |
| Agihan spine — pengarah terima/tolak/keputusan | `role:pengarah,admin` | |
| Agihan spine — ppuu pilih | `role:ppuu,koordinator,admin` | |
| Agihan spine — kp keputusan | `role:ketua_pengarah,admin` | |
| Agihan (legacy `AgihanController`: form/store/beban, web.php:166-168) | none beyond staff group | all 7 staff |
| Lawyer area | `role:peguam` | peguam |
| Guest/public (`peguam.daftar`, login, password reset) | `guest` / none | unaffected by RBAC |

**Wrong-role behavior today (critical, finding #1):** `EnsureRole.php:28-30` does NOT 403 an authenticated wrong-role user — it `redirect()->route($user->homeRoute())`. Spatie's middleware throws `UnauthorizedException` → 403. This MUST be preserved (§7d).

### 4b. In-controller checks today
| Location | Check | Deny style today | Becomes |
|---|---|---|---|
| `KeputusanController::__construct/gate` | `hasRole(...APPROVER_ROLES)` | **abort 403** | `can('kes.keputusan')`, keep 403 |
| `PermohonanPeguamController::semak` (:50) | `hasRole(PPUU,PEMBANTU_TADBIR,KOORDINATOR,ADMIN)` | **soft 302 `back()->withErrors`** | `can('peguam.semak')`, **keep soft 302** |
| `PermohonanPeguamController::sokong` (:67) | `hasRole(PENGARAH,ADMIN)` | soft 302 | `can('peguam.sokong')`, keep soft 302 |
| `PermohonanPeguamController::keputusan` (:88) | `hasRole(KETUA_PENGARAH,ADMIN)` | soft 302 | `can('peguam.keputusan')`, keep soft 302 |
| `AgihanSpineController` (closure `$is`, **line 159**, used 162-166) | `hasRole(...$roles)` | UI-affordance only (which tier form) | array-form `hasRole([...])` or `can()` per branch |

### 4c. Direct `users.role` column queries (role-coupled, NOT authz)
| Location | Query | Decision |
|---|---|---|
| `AgihanSpineController:36` | `User::whereIn('role', [PPUU,KOORDINATOR,PEMBANTU_TADBIR,ADMIN])` (ppuuList) | **keep column query** for batch 7 (finding #25) — avoid a 2nd source of truth mid-cutover; Spatie `role()` scope deferred to column-removal batch |
| `UserController:17-21` | filter by `role` | keep column query |
| `SystemController:26` | count by `user_type` | unaffected |

### 4d. View-layer role checks (variadic `hasRole()` — ALL break on collision, finding #2/#7/#27)
| View | Line | Check | Target |
|---|---|---|---|
| `permohonan-peguam/show.blade.php` | 50, 74, 92 | tier checks | `@can('peguam.semak'/'peguam.sokong'/'peguam.keputusan')` |
| `kes/show.blade.php` | 166 | `hasRole('pengarah','ketua_pengarah','admin')` | `@can('kes.keputusan')` |
| `layouts/staff.blade.php` | 74 | `hasRole('admin','pengarah','koordinator')` (wraps Pentadbiran+Selenggara sidebar) | `@can('menu.selenggara')` (NEW perm, §6) |
| `system/utama.blade.php` | 6 | `$isSupervisor` = `hasRole('admin','pengarah','koordinator')` (hides Pegawai/Audit tiles) | `@can('menu.selenggara')` |
| `layouts/staff.blade.php` | 24 | `strtoupper(user->role)` | display only — leave (column retained) |
| `agihan/maklumat.blade.php` | 65 | renders `$p->role` label | display only — leave |

**The 3-role supervisor set `{admin,pengarah,koordinator}` exists in views but NOWHERE in route gating** (routes use the 4-role set incl. ketua_pengarah). A new permission `menu.selenggara` (§6) seeded to exactly those 3 reproduces today's sidebar/dashboard visibility without changing ketua_pengarah's affordance.

### 4e. Scope today
`CawanganScope` applies `WHERE cawangan = user.cawangan` when `user.isStaff() && filled(user.cawangan) && role ∈ {pegawai, pengarah, ppuu, pembantu_tadbir}`. HQ (admin, koordinator, ketua_pengarah), lawyers, no-branch → see all. Registered on `Form` via `addGlobalScope` (Form.php:22).

### 4f. Legacy data reality (findings #10/#18)
- `ImportLegacyData::staffRole()` maps legacy peranan to ONLY `admin`(1) / `pengarah`(2) / `pegawai`(default); lawyers → `peguam`. **koordinator, ppuu, pembantu_tadbir, ketua_pengarah are NEVER produced by the ETL** — they exist as consts/fixtures only, populated via manual UserController creation.
- `DemoUserSeeder` creates `demo@example.com` with no role → DB column default `pegawai`.
→ Backfill (§7c) must handle: 4 roles with zero population, and column-default users.

---

## 5. Critical migration gotchas (must handle)

1. **`hasRole()` collision (findings #2/#6/#7/#23/#26/#27).** `User::hasRole(string ...$roles)` is custom (variadic). Spatie's `HasRoles::hasRole($roles, $guard = null)` treats the 2nd positional arg as the **guard name**. Removing the custom method silently breaks every variadic caller.
   - **Convert ALL callers to array form `hasRole([...])` (or `@can`) in the SAME release:**
     - PHP: `EnsureRole.php:28` (`...$roles` spread → pass `$roles` array), `KeputusanController.php:22`, `PermohonanPeguamController.php:50/67/88`, `AgihanSpineController.php:159` (closure; **not** line 132 — that was a mis-citation).
     - Blade: the 5 view sites in §4d.
2. **`role` middleware alias collision.** Spatie registers `role`, `permission`, `role_or_permission` aliases. Drop the custom `role`→`EnsureRole` alias once routes swap; rely on Spatie's.
3. **Spatie middleware delimiter is PIPE `|`, not comma (finding #17).** `role:a|b`, `permission:x|y`. Comma denotes the guard (`role:manager,api` = role manager on guard api). Batch 7 mostly uses single-permission gates, but any multi-value / `role_or_permission` gate MUST use `|`.
4. **Wrong-role = redirect, not 403 (finding #1).** Preserve via an `UnauthorizedException` render closure (§7d).
5. **`users.role` denormalization drift (findings #5.3/#21).** Column kept for display + queries (§4c) + rollback. On EVERY role mutation, dual-write column + Spatie in one transaction. Sites: `UserController::store` (:51), `UserController::update` (:77), `PermohonanPeguamController::provisionLogin` (:187, sets `peguam`).
6. **Permission cache (findings #12/#15).** Production `CACHE_STORE=database` (.env). Seeder must `forgetCachedPermissions()` at START and after permission creation, plus `permission:cache-reset` at end. Deploy resets cache after seed AND after backfill, not only at the end (§9). Also clear compiled config if `config:cache` is used.
7. **Deploy order vs Hostinger webhook (findings #3/#29).** Webhook auto-pulls + `composer install` on push → new code live before manual SSH migrate/seed → lockout window. Mitigated by graceful fallback + maintenance window (§9).
8. **`hasRole()`/consts retained for non-authz use.** Keep `STAFF_ROLES`/`APPROVER_ROLES` consts (seed definitions + §4c queries), `isStaff/isLawyer/homeRoute` (driven by `user_type`). Only the authz path moves to Spatie.

---

## 6. Permission taxonomy (mirror current access exactly)

Naming `modul.aksi`, guard `web`. admin omitted from per-permission grants because **`Gate::before` makes admin a super-admin** (§7a) — but admin is still explicitly granted `urus.peranan` and assigned the `admin` role so the matrix UI and role queries behave.

| Permission | Granted to roles (mirror of today; admin = all via Gate::before) |
|---|---|
| `system.view` | pengarah, koordinator, pegawai, ppuu, pembantu_tadbir, ketua_pengarah |
| `kes.view` / `kes.create` / `kes.update` | all 6 non-admin staff |
| `kes.keputusan` | pengarah, ketua_pengarah |
| `pengantaraan.manage` | all 6 non-admin staff |
| `mahkamah.manage` | all 6 non-admin staff |
| `lampiran.manage` | all 6 non-admin staff |
| `cetakan.view` | all 6 non-admin staff |
| `oyd.manage` | all 6 non-admin staff |
| `kpi.view` | all 6 non-admin staff |
| `laporan.view` | all 6 non-admin staff |
| `statistik.view` | all 6 non-admin staff |
| `agihan.manage` (incl. legacy AgihanController form/store/beban, finding #22) | all 6 non-admin staff |
| `agihan.pengarah` | pengarah |
| `agihan.ppuu` | ppuu, koordinator |
| `agihan.kp` | ketua_pengarah |
| `peguam_panel.manage` | all 6 non-admin staff |
| `peguam.permohonan.view` | all 6 non-admin staff |
| `peguam.semak` | ppuu, pembantu_tadbir, koordinator |
| `peguam.sokong` | pengarah |
| `peguam.keputusan` | ketua_pengarah |
| `selenggara.pegawai` / `selenggara.poster` / `selenggara.ref_kes` / `selenggara.mahkamah_ref` | pengarah, koordinator, ketua_pengarah |
| `urus.pengguna` | pengarah, koordinator, ketua_pengarah |
| `audit.view` | pengarah, koordinator, ketua_pengarah |
| `menu.selenggara` (view-layer sidebar/dashboard supervisor block, §4d) | pengarah, koordinator |
| `cawangan.view-all` (branch-bypass) | koordinator, ketua_pengarah |
| `urus.peranan` (RBAC admin UI) | — (admin only, via Gate::before + explicit grant) |
| `lawyer.area` | peguam |

Notes:
- Because admin is super-admin via `Gate::before`, the non-admin grants above fully define the matrix; admin is intentionally absent from each row but passes every check.
- `cawangan.view-all` mirrors today's HQ bypass (admin via Gate::before; koordinator, ketua_pengarah explicit). Scoped roles (pegawai, pengarah, ppuu, pembantu_tadbir) do NOT get it — matches `SCOPED_ROLES` exactly.
- `menu.selenggara` = 3-role supervisor set minus admin (admin via Gate::before) → reproduces views §4d without granting ketua_pengarah the sidebar block.
- `urus.peranan` admin-only prevents privilege escalation through the matrix UI; Gate::before guarantees admin always reaches it (no self-lockout).

---

## 7. Implementation components

### 7a. Package + model + super-admin (findings #4/#16)
- Install Spatie; publish config + migrations; migrate.
- `User`: `use HasRoles;`. **Remove custom `hasRole()`.** Keep `isStaff/isLawyer/homeRoute`, `STAFF_ROLES/APPROVER_ROLES` consts.
- **`Gate::before`** in `AppServiceProvider::boot` (or a dedicated `AuthServiceProvider`): `Gate::before(fn (User $u) => $u->hasRole('admin') ? true : null);` → admin bypasses all permission checks. Closest to today's "admin is in every list", future-proofs batches 8–13, and prevents admin self-lockout from the matrix UI. (Returning `null`, not `false`, so non-admins fall through to normal checks.)
- Pre-flight: assert `config('auth.defaults.guard') === 'web'`.

### 7b. Seeder — `RolePermissionSeeder` (idempotent)
- `app(PermissionRegistrar::class)->forgetCachedPermissions()` at start.
- `firstOrCreate` all 8 roles + all permissions (§6). `forgetCachedPermissions()` again after permission creation.
- `syncPermissions` per role per §6 matrix (admin row optional — Gate::before covers it — but assign `admin` the `urus.peranan` perm explicitly for clarity/defensive depth).
- `permission:cache-reset` at end. Re-runnable safely every release.

### 7c. Backfill — role assignment for existing users (findings #10/#18)
- Console command / migration: for each user `syncRoles([$user->role])` in a transaction, chunked.
- **Guard:** if `$user->role` is null/empty OR not in the seeded role set → log loudly and assign a safe fallback (`pegawai` for staff, `peguam` for lawyer) or skip-with-alert (decide: fallback for staff, since column-default is already `pegawai`). Covers the demo user.
- Note: post-backfill, koordinator/ppuu/pembantu_tadbir/ketua_pengarah will have zero users (ETL never emits them); their workflow tiers need accounts created via UserController. Not an error — documented expectation.

### 7d. Route gating swap (findings #1/#3/#22, locked per #7d ambiguity)
- Outer staff group → `permission:system.view` baseline.
- Selenggara subgroup → gate **each route by its specific §6 permission** (`selenggara.pegawai`, `selenggara.poster`, `selenggara.ref_kes`, `selenggara.mahkamah_ref`, `urus.pengguna`, `audit.view`). Seeder grants all six to exactly {pengarah, koordinator, ketua_pengarah} (+ admin via Gate::before) — no more, no less. (No `role_or_permission`/split alternatives — single locked approach.)
- Agihan spine → `permission:agihan.pengarah` / `agihan.ppuu` / `agihan.kp`. Legacy `AgihanController` (form/store/beban) → `permission:agihan.manage` (explicit, finding #22).
- `kes.lulus/tolak/tutup-fail` stay in staff group; controller enforces `kes.keputusan`.
- Lawyer area → `permission:lawyer.area`.
- **Preserve wrong-role redirect (finding #1):** register an `UnauthorizedException` render closure in `bootstrap/app.php` `withExceptions()` that, for authenticated users, `redirect()->route($user->homeRoute())` (mirroring EnsureRole) instead of a 403 page. Guests → login.
- Multi-value gates use `|` (finding #17).

### 7e. In-controller checks swap (findings #8/#13)
- `KeputusanController`: `abort_unless($u->can('kes.keputusan'), 403)` — keeps today's 403.
- `PermohonanPeguamController` semak/sokong/keputusan: **preserve soft contract** — `if (! $u->can('peguam.semak')) return back()->withErrors([...]);` (302 + flash, NOT 403). Do not unify with KeputusanController's 403.
- `AgihanSpineController` `$is` closure (line 159): array-form `hasRole([...])` (UI affordance); optionally `can()` mapped to `agihan.*` for consistency.

### 7f. `CawanganScope` redesign (findings #5/#14)
```
apply():
  $u = auth()->user();
  if ($u && $u->isStaff() && filled($u->cawangan) && ! $this->canViewAll($u))
      $builder->where($table.'.cawangan', $u->cawangan);
```
- `canViewAll($u)`: **memoize per request** (e.g. static/request-scoped cache keyed by user id) so `can('cawangan.view-all')` is resolved ONCE, not per Form query. The scope must not issue a DB/permission lookup on every Form query (today it's a pure `in_array` on a loaded column).
- Removes hardcoded `SCOPED_ROLES`. Lawyers (`isStaff()` false) unaffected → see all.
- Matrix edits to `cawangan.view-all` call `permission:cache-reset` (via §7g) so the memoized value is correct on next request.

### 7g. Admin UI (Tetapan) — gated `permission:urus.peranan`
- `RoleController` (peranan): index/create/edit/update/destroy. **Protect the 8 seeded system roles** (allowlist const) from rename/delete. **Also block deleting ANY role that still has assigned users** (`->users()->exists()`) to avoid orphaning authz via `model_has_roles` cascade (finding #24).
- `RolePermissionController` (akses): `edit(role)` → permission matrix grouped by module; `update(role)` → `syncPermissions(selected)` + `permission:cache-reset`. Audit-log every matrix change.
- `UserController` role select: **enumerate all 8 Spatie roles** (today's `ROLES` const has only 5) — newly exposes ppuu/pembantu_tadbir/ketua_pengarah as assignable (intentional, §1a #19). Dual-write column + role per §5.5.
- Blade under `layouts/staff.blade.php`; sidebar entry "Peranan & Akses" visible when `can('urus.peranan')`.

### 7h. Blade affordance migration (findings #2/#7/#27)
- Convert the 5 variadic `hasRole()` view sites in §4d to `@can(...)` (or array `@role`) in the SAME release as the model change — not a deferred grep. Display-only `$user->role` echoes (staff.blade.php:24, agihan/maklumat:65) left as-is (column retained).

### 7i. Middleware ordering (finding #28)
- Confirm `ForcePasswordChange` (appended in `bootstrap/app.php`) runs ahead of / independently of Spatie permission middleware so a `must_change_password` user is redirected to `password.change`, not handed a 403 on a gated route.

---

## 8. Testing (target ≥80%)

### 8a. Route × role regression matrix — the safety net (findings #9/#11/#20)
- **Fixtures: all 8 roles.** Extend `TestUsersSeeder` (today seeds only 5: admin/pengarah/koordinator/pegawai/peguam) to add ppuu, pembantu_tadbir, ketua_pengarah — exactly the tiers whose access differs.
- **Cell-by-cell, not one-route-per-module.** Generate expected outcomes programmatically from the §6 matrix so a seed drift fails the test. Cover each granular gate on a route gated by ONLY that permission: `kes.keputusan`, `agihan.pengarah`, `agihan.ppuu`, `agihan.kp`, `peguam.semak/sokong/keputusan`, all six selenggara.*/`urus.pengguna`/`audit.view`, `menu.selenggara`, `lawyer.area`.
- **Deny-style assertions per contract:** wrong-role authenticated → **redirect to homeRoute (302), NOT 403** (finding #1); KeputusanController denial → 403; Permohonan* denial → 302+withErrors (finding #8). Do not assert a blanket "denied == 403".
- Cross-area: peguam → staff route → redirect to `peguam.dashboard`; staff → lawyer route → redirect to `system.utama`.
- Must be GREEN against the EXPECTED matrix (define from today's behavior first).

### 8b. Seeder / backfill
- All 8 roles + all §6 permissions exist; each role's permission set equals the matrix; **admin holds every permission OR Gate::before short-circuits** (assert admin `can()` everything).
- Backfill: every distinct `users.role` value maps to a seeded role (fail loud on unmapped/legacy/unknown); demo/column-default `pegawai` user is assigned.
- Dual-write parity (finding #21): after `UserController::store`, `::update`, and `PermohonanPeguamController::provisionLogin`, the `role` column and the Spatie role agree.

### 8c. CawanganScope
- Scoped role (pegawai, branch X) → only branch-X `Form` rows; `cawangan.view-all` role (koordinator) → all; lawyer → all; admin (Gate::before) → all.
- Toggling `cawangan.view-all` on a role + cache reset flips visibility within the request (proves permission-driven + memoization/cache correctness).
- Perf: assert the scope does not add a permission query per Form query (memoized).

### 8d. Admin UI
- `urus.peranan` required (403/redirect without; admin always in via Gate::before).
- Matrix update persists + cache resets + takes effect on next request **under the database cache store** (not array) — mirror prod `CACHE_STORE`.
- System role rename/delete blocked; role-with-users delete blocked.

### 8e. Test harness strategy (finding #20)
- The repo convention is live-MySQL feature tests with tag-scoped self-cleanup; `phpunit.xml` defaults to sqlite `:memory:` + `CACHE_STORE=array`. **Decision:** for the route×role matrix use the existing live-MySQL pattern BUT scope Spatie role/permission seeding + teardown so it cannot clobber real data; call `permission:cache-reset` / `forgetCachedPermissions()` in `setUp/tearDown` so Spatie's static permission cache does not leak between tests. (Spatie migrations are MySQL/sqlite-safe; a sqlite+RefreshDatabase variant is acceptable for pure-RBAC unit tests, but the regression matrix runs against the real schema.)

---

## 9. Rollout / rollback (webhook-safe, findings #3/#12/#15/#28/#29)

**Reality:** the Hostinger webhook does `git pull` + `composer install` automatically on push; migrate/seed run manually via SSH (port 65002). So code goes live the moment the push lands — BEFORE SSH steps. A naive "code + manual migrate later" bricks auth.

**Chosen strategy: graceful fallback + maintenance window.**
1. **Graceful fallback in code:** the new permission path falls back to the legacy `EnsureRole`/`users.role` check when Spatie tables are absent/empty (e.g. wrap the swap so that if `roles` table is empty, legacy gating applies). This makes the post-push pre-seed window non-fatal. (If a clean fallback is impractical for a given gate, the maintenance window below covers it.)
2. **Maintenance window:** `php artisan down` via SSH **before** the push (or immediately after), then:
   - `composer install` (lock committed, §3),
   - `php artisan migrate` (Spatie tables),
   - `php artisan db:seed --class=RolePermissionSeeder` → `permission:cache-reset` immediately,
   - run backfill command → `permission:cache-reset` again,
   - `php artisan config:clear` (+ `route:clear`/`view:clear`; rebuild `config:cache` only if used) so the new `permission.php` config + middleware aliases load,
   - `php artisan up`.
3. Commit `composer.json` + `composer.lock` together.

**Rollback:** revert the release commit (routes back to `role:` + `EnsureRole`; custom `hasRole` restored). `users.role` is dual-written throughout, so the column is always a faithful fallback → old gating works immediately on revert. Spatie tables can remain (unused) or `migrate:rollback`. This is why the column is retained this batch.

---

## 10. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Webhook puts permission code live before seed → prod lockout | Critical | graceful fallback + `down`/`up` window (§9) |
| `hasRole()` collision silently mis-authorizes (PHP + Blade) | Critical | remove custom method; convert all §5.1 callers incl. 5 views; cell-by-cell test |
| Wrong-role 403 instead of redirect (UX regression) | High | UnauthorizedException render → homeRoute (§7d); test asserts redirect |
| admin loses access to future modules / self-lockout from matrix | High | `Gate::before` super-admin (§7a) |
| Mis-seed grants more/less than today | High | mirror §6 exactly; programmatic cell-by-cell matrix from §6 (§8a) |
| Soft-deny contract changed to 403 | High | preserve `back()->withErrors` for Permohonan* (§7e) |
| CawanganScope per-query permission lookup (perf) / cache leak | High | memoize per request; cache-reset on matrix edit (§7f) |
| Backfill misses legacy/unknown role values, demo user | Medium | fail-loud guard + safe fallback (§7c); assertion (§8b) |
| guard_name mismatch denies everything | Medium | pre-flight guard check (§3/§7a) |
| Permission cache stale (database store) | Medium | forgetCachedPermissions start+after-create; cache-reset after seed+backfill; config:clear (§9) |
| Privilege escalation via matrix UI | High | `urus.peranan` admin-only; system roles + roles-with-users protected; audit-logged |
| composer install fails on webhook (half vendor) | Medium | commit lock; maintenance window; SSH pre-run if needed |

---

## 11. Out of this batch (explicit)
- New advisory permissions + modules (batches 8–13).
- Public `awam` portal + role (batch 13).
- Finer per-action kes permissions; dropping `users.role`; Spatie `role()` scope for §4c queries (column-removal batch).
- Per-user (non-role) direct permissions.

---

## 12. Revised batch sequence
**7** RBAC refactor (this) → **8** Masters (cawangan/jkm/penjara + 3-level kategori_kn + jawatan, gated by new perms) → **9** Khidmat Nasihat wizard → **10** Appointment/slot/calendar → **11** Officer processing → **12** Feedback + reports → **13** Public portal (awam).

---

## 13. File-level change map (implementation checklist)
- `composer.json` + `composer.lock` — add spatie/laravel-permission ^7.0.
- `config/permission.php` — published.
- migrations — Spatie set; (no new app tables this batch).
- `app/Models/User.php` — `HasRoles`; remove custom `hasRole`; keep consts/helpers.
- `app/Providers/AppServiceProvider.php` (or AuthServiceProvider) — `Gate::before` super-admin.
- `app/Models/Scopes/CawanganScope.php` — permission-based + memoized.
- `bootstrap/app.php` — drop `role`→EnsureRole alias; add `UnauthorizedException` render→homeRoute; verify ForcePasswordChange order.
- `app/Http/Middleware/EnsureRole.php` — remove (after route swap) — or keep as fallback shim per §9.1.
- `routes/web.php` — swap `role:` → `permission:` per §7d.
- `app/Http/Controllers/KeputusanController.php`, `PermohonanPeguamController.php` (:50/67/88/187), `AgihanSpineController.php` (:159, :36), `UserController.php` (:51/77 + ROLES dropdown) — per §7c/e/g.
- Views §4d (5 sites) — `@can`.
- `database/seeders/RolePermissionSeeder.php` (new), `TestUsersSeeder.php` (extend to 8), backfill command.
- Tests — route×role matrix, seeder/backfill, CawanganScope, admin UI.

## 14. Review-finding traceability
Critical: #1 (§4a/§7d/§8a), #2 (§4d/§5.1/§7h), #3 (§9). High: #4 (§7a), #5 (§7f), #6/#23/#26 (§4b/§5.1 line 159), #7 (§4d/§6 menu.selenggara), #8/#13 (§7e/§8a), #9/#11/#20 (§8a/§8e), #10/#18 (§4f/§7c), #12 (§6/§9), #16 (§3/§7a), #19 (§1a/§7g), #21 (§5.5/§7g/§8b), #22 (§4a/§6/§7d). Medium/low: #15 (§7b/§9), #17 (§5.3/§7d), #24 (§7g), #25 (§4c), #28 (§7i), #29 (§3/§9).
