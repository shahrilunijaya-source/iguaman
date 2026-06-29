# Batch 7 — DB-driven RBAC (full-app refactor) — Design Spec

**Date:** 2026-06-30
**Status:** Approved (design); pending spec review
**Scope:** Replace the hardcoded role-const + `EnsureRole` authorization across the **entire** 2in1 app with Spatie laravel-permission DB-driven roles + permissions, plus an admin UI to manage them. Foundation for the iGuaman Janji Temu / Khidmat Nasihat port (batches 8–13).

> Part of the larger port plan: `context/port-plan-iguaman-janjitemu.md`. This spec covers **batch 7 only**.

---

## 1. Goals / Non-goals

**Goals**
- Move every authorization decision (route gating + in-controller checks + scope role-lists) from hardcoded `User::ROLE_*` consts to DB-managed Spatie roles + permissions.
- Zero behavior change at cutover: every role keeps exactly the access it has today.
- Admin UI: manage roles + per-role permission matrix (ports source `senarai-peranan` + `akses-pengguna`).
- Redesign `CawanganScope` so branch-bypass is a permission (`cawangan.view-all`), not a hardcoded role list.

**Non-goals (deferred)**
- `awam` public user_type / public portal → batch 13.
- New advisory permissions (khidmat nasihat, janji temu, etc.) → added in their own batches.
- Dropping the `users.role` column → kept this release (display + denormalized query mirror); removal is a later cleanup.
- Per-user direct permissions (Spatie supports it, but we gate via roles only for now).
- Teams/multi-guard features of Spatie.

---

## 2. Locked decisions (from brainstorm)

| # | Decision |
|---|---|
| RBAC model | DB-driven roles + permissions (not hardcoded consts) |
| RBAC reach | **Full-app refactor** — batches 1–6 + new modules all gate via DB |
| RBAC engine | **Spatie laravel-permission `^7.0`** (verified: supports Laravel 12/13, PHP 8.3+) |
| `users.role` | **Kept** as denormalized mirror + display; Spatie is source of truth for authz |
| `CawanganScope` | **Permission-based** — branch filter applies unless user has `cawangan.view-all` |
| Sequencing | RBAC is its own batch 7; masters move to batch 8; rest renumber |

---

## 3. Dependency

`composer require spatie/laravel-permission:^7.0`

- ~60M installs, actively maintained. **Not** Filament/Breeze/Jetstream → within project rules.
- Publishes `config/permission.php` + one migration set (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`).
- Adds `HasRoles` trait to `User`.
- Single guard: `web` (one `users` table; staff + lawyers + future awam all authenticate the same way).
- **Cost flagged:** +1 dependency, +5 tables, a published config, and a one-time migration of existing role assignments. Justified vs hand-rolling a security-critical authz cache.

---

## 4. Current authorization surface (what we are migrating)

Extracted from `routes/web.php`, `app/Http/Middleware/EnsureRole.php`, `app/Models/User.php`, controllers, and `CawanganScope`.

### 4a. Route gating today
| Group | Middleware | Members |
|---|---|---|
| Staff area (most of rekod-kes + panel) | `role:admin,pengarah,koordinator,pegawai,ppuu,pembantu_tadbir,ketua_pengarah` | all 7 staff roles |
| Selenggara subgroup (pegawai/poster/ref-kes/mahkamah-ref/pengguna/audit) | `role:admin,pengarah,koordinator,ketua_pengarah` | supervisory 4 (excludes pegawai, ppuu, pembantu_tadbir) |
| Agihan spine — pengarah terima/tolak/keputusan | `role:pengarah,admin` | |
| Agihan spine — ppuu pilih | `role:ppuu,koordinator,admin` | |
| Agihan spine — kp keputusan | `role:ketua_pengarah,admin` | |
| Lawyer area | `role:peguam` | peguam |
| Guest/public | `guest` / none (`peguam.daftar`, login, password reset) | — (unchanged, not gated by RBAC) |

### 4b. In-controller role checks today
| Location | Check | Becomes permission |
|---|---|---|
| `KeputusanController::__construct` | `hasRole(...User::APPROVER_ROLES)` (pengarah/ketua_pengarah/admin) | `kes.keputusan` |
| `PermohonanPeguamController::semak` | `hasRole(PPUU, PEMBANTU_TADBIR, KOORDINATOR, ADMIN)` | `peguam.semak` |
| `PermohonanPeguamController::sokong` | `hasRole(PENGARAH, ADMIN)` | `peguam.sokong` |
| `PermohonanPeguamController::keputusan` | `hasRole(KETUA_PENGARAH, ADMIN)` | `peguam.keputusan` |
| `AgihanSpineController` | `$is(...)` closure → `hasRole(...)` for UI affordance (which action button) | mirror with `can()` or keep role checks for display only |

### 4c. Direct `users.role` column queries (NOT authz, but role-coupled)
| Location | Query | Migration note |
|---|---|---|
| `AgihanSpineController:36` | `User::whereIn('role', [PPUU, KOORDINATOR, PEMBANTU_TADBIR, ADMIN])` (ppuuList) | keep (column retained) OR Spatie `role()` scope |
| `UserController:17-21` | filter by `role` | keep (column retained) |
| `SystemController:26` | count by `user_type` | unaffected (user_type, not role) |

### 4d. Scope today
`CawanganScope`: applies `WHERE cawangan = user.cawangan` when `user.isStaff() && filled(user.cawangan) && role ∈ {pegawai, pengarah, ppuu, pembantu_tadbir}`. HQ roles (admin, koordinator, ketua_pengarah), lawyers, no-branch → see all. Registered on `Form` via `addGlobalScope`.

---

## 5. Critical migration gotchas (must handle)

1. **`hasRole()` collision.** `User::hasRole(string ...$roles)` is custom (variadic, returns true if role ∈ args). Spatie's `HasRoles` trait provides `hasRole($roles, $guard = null)` — **second positional arg is the guard**. Every variadic caller like `hasRole(ROLE_A, ROLE_B)` would treat `ROLE_B` as a guard name and silently fail.
   - **Action:** remove the custom `hasRole`; convert ALL callers to array form `hasRole([ROLE_A, ROLE_B])`. Callers: `EnsureRole:28` (`...$roles` spread → pass `$roles` array), `KeputusanController:22`, `PermohonanPeguamController:50/67/88`, `AgihanSpineController:132`.
2. **`role` middleware alias collision.** Spatie registers `role`, `permission`, `role_or_permission` aliases. The app currently aliases `role` → `EnsureRole`. Spatie's `role:a,b` middleware is signature-compatible, but we are moving routes to `permission:` anyway. **Action:** drop the custom `role` alias + `EnsureRole` class once routes are swapped; rely on Spatie's middleware.
3. **`users.role` denormalization drift.** Column is kept for display + the role-column queries (4c). **Action:** on every role assignment/change (UserController store/update, PermohonanPeguam approval that sets `role => peguam`), write BOTH `assignRole()` and the `role` column in one transaction. Document column as "display mirror — authz source is Spatie."
4. **`STAFF_ROLES` / `APPROVER_ROLES` consts.** Still useful as seed definitions + the `users.role` queries. Keep the consts; stop using them for live authz (replaced by permissions). `homeRoute()` / `isStaff()` / `isLawyer()` stay (driven by `user_type`, not role).
5. **Permission cache.** Spatie caches the permission map. **Action:** `php artisan permission:cache-reset` in the seeder + deploy step; clear after the migration seeds. Note for Hostinger deploy (no queue/worker assumptions).
6. **Order of operations on deploy.** migrate (creates Spatie tables) → seed roles+permissions+matrix → backfill user role assignments → only then is permission-gated middleware safe. A half-applied deploy locks everyone out. **Action:** single deploy migration + seeder, idempotent, run before routes change is live. See §9 rollout.

---

## 6. Permission taxonomy (mirror current access exactly)

Naming: `modul.aksi` (Malay-consistent with codebase). Guard `web`.

| Permission | Granted to roles (mirror of today) |
|---|---|
| `system.view` | all 7 staff |
| `kes.view` | all 7 staff |
| `kes.create` | all 7 staff |
| `kes.update` | all 7 staff |
| `kes.keputusan` (lulus/tolak/tutup-fail) | pengarah, ketua_pengarah, admin |
| `pengantaraan.manage` | all 7 staff |
| `mahkamah.manage` (case court section + laporan_kes) | all 7 staff |
| `lampiran.manage` | all 7 staff |
| `cetakan.view` | all 7 staff |
| `oyd.manage` | all 7 staff |
| `kpi.view` | all 7 staff |
| `laporan.view` | all 7 staff |
| `statistik.view` | all 7 staff |
| `agihan.manage` (form/store/beban/maklumat) | all 7 staff |
| `agihan.pengarah` (terima/tolak/keputusan) | pengarah, admin |
| `agihan.ppuu` (pilih) | ppuu, koordinator, admin |
| `agihan.kp` (keputusan) | ketua_pengarah, admin |
| `peguam_panel.manage` (show/edit/update) | all 7 staff |
| `peguam.permohonan.view` | all 7 staff |
| `peguam.semak` | ppuu, pembantu_tadbir, koordinator, admin |
| `peguam.sokong` | pengarah, admin |
| `peguam.keputusan` | ketua_pengarah, admin |
| `selenggara.pegawai` | admin, pengarah, koordinator, ketua_pengarah |
| `selenggara.poster` | admin, pengarah, koordinator, ketua_pengarah |
| `selenggara.ref_kes` | admin, pengarah, koordinator, ketua_pengarah |
| `selenggara.mahkamah_ref` | admin, pengarah, koordinator, ketua_pengarah |
| `urus.pengguna` | admin, pengarah, koordinator, ketua_pengarah |
| `audit.view` | admin, pengarah, koordinator, ketua_pengarah |
| `urus.peranan` (RBAC admin UI) | admin (only) |
| `cawangan.view-all` (branch-bypass for scope) | admin, koordinator, ketua_pengarah |
| `lawyer.area` (peguam dashboard/kes/tawaran/profil/terima/tolak/laporan) | peguam |

Notes:
- The big staff group keeps a shared "staff baseline" set (`system.view`, `kes.*`, `pengantaraan`, `mahkamah`, `lampiran`, `cetakan`, `oyd`, `kpi`, `laporan`, `statistik`, `agihan.manage`, `peguam_panel.manage`, `peguam.permohonan.view`).
- `cawangan.view-all` exactly reproduces today's HQ bypass list (admin, koordinator, ketua_pengarah) — the scoped roles (pegawai, pengarah, ppuu, pembantu_tadbir) do NOT get it.
- `urus.peranan` restricted to `admin` to prevent privilege escalation via the matrix UI.

---

## 7. Implementation components

### 7a. Package + model
- Install Spatie; publish config + migrations; run migrate.
- `User`: add `use HasRoles;`. Remove custom `hasRole()`. Keep `isStaff/isLawyer/homeRoute`, `STAFF_ROLES/APPROVER_ROLES` consts (seed + queries only).

### 7b. Seeder — `RolePermissionSeeder` (idempotent)
- `firstOrCreate` all roles (the 8: 7 staff + peguam).
- `firstOrCreate` all permissions (§6).
- `syncPermissions` per role per the §6 matrix.
- `permission:cache-reset` at end.
- Re-runnable safely (deploy can call it every release).

### 7c. Data migration — assign roles to existing users
- Migration or console command: for each user, `$user->syncRoles([$user->role])` (role column → Spatie). Wrap in transaction; chunk for scale.
- Guard: skip users whose `role` is null/empty; log them.

### 7d. Route gating swap
- Replace `role:...` groups with `permission:...`:
  - Staff area → `permission:system.view` (baseline gate) — but individual routes need their own permission for granularity. **Decision:** keep ONE group gate that requires the baseline (`role_or_permission` not needed) and add per-route `permission:` where today has tighter role gates (selenggara subgroup, agihan spine). Simplest faithful mirror:
    - Outer staff group → `permission:system.view`.
    - Selenggara subgroup → `permission:urus.pengguna` is too narrow; instead gate the subgroup with a `role_or_permission` or split. **Chosen:** gate selenggara routes each by their specific permission (`selenggara.pegawai`, `selenggara.poster`, `selenggara.ref_kes`, `selenggara.mahkamah_ref`, `urus.pengguna`, `audit.view`).
    - Agihan spine routes → `permission:agihan.pengarah` / `agihan.ppuu` / `agihan.kp`.
    - `kes.lulus/tolak/tutup-fail` routes stay in staff group; controller enforces `kes.keputusan` (keep controller gate, swap to `can`).
  - Lawyer area → `permission:lawyer.area`.
- **Per-route granularity:** apply `permission:` middleware to route subgroups matching §6 (e.g. wrap kes CRUD writes vs reads if desired — for batch 7 we mirror today's coarser gating; finer splits are a later enhancement, noted not done).

### 7e. In-controller checks swap
- `KeputusanController`: `abort_unless($request->user()->can('kes.keputusan'), 403)`.
- `PermohonanPeguamController`: `can('peguam.semak'|'peguam.sokong'|'peguam.keputusan')`.
- `AgihanSpineController` UI-affordance closure: use `can()` for which buttons to show (keeps display consistent with route gating).

### 7f. `CawanganScope` redesign
```
apply(): if user && user.isStaff() && filled(user.cawangan) && ! user.can('cawangan.view-all')
         → where cawangan = user.cawangan
```
- Removes the hardcoded `SCOPED_ROLES` list. Branch-bypass now = `cawangan.view-all` permission (seeded to admin/koordinator/ketua_pengarah = today's behavior).
- Lawyers: `isStaff()` false → unaffected (see all), unchanged.

### 7g. Admin UI (Tetapan) — gated `permission:urus.peranan`
- `RoleController` (peranan): index (list roles + permission counts), create, edit, update, destroy. **Protect system roles** (the 8 seeded) from delete/rename — `is_system` guard (a config list, since Spatie roles have no such column; maintain an allowlist constant).
- `RolePermissionController` (akses): `edit(role)` shows permission matrix grouped by module; `update(role)` `syncPermissions(selected)` + cache reset.
- Blade under `layouts/staff.blade.php`; new sidebar entry "Peranan & Akses" (visible when `can('urus.peranan')`).
- Assigning roles to users stays in existing `UserController` (already supervisory-gated); add role select backed by Spatie roles (+ keep `role` column in sync, §5.3).

### 7h. Blade directive sweep
- Replace any `@if(auth()->user()->hasRole(...))` / role-string checks in views with `@can('permission')` / `@role('name')`. Grep `resources/views` for role checks during implementation.

---

## 8. Testing (target ≥80%)

### 8a. Route × role regression matrix (the safety net)
- Feature test: for each of the 8 roles, hit one representative route of every module and assert 200/redirect (allowed) or 403 (denied), matching §6.
- Must run GREEN before and after the swap (define expected matrix from today's behavior first).

### 8b. Unit / seeder
- Seeder test: all 8 roles + all §6 permissions exist; each role's permission set equals the matrix.
- `users.role` ↔ Spatie sync: assigning a role updates both; data-migration assigns correct Spatie role per legacy `role`.

### 8c. CawanganScope
- Scoped role (pegawai) with branch X → sees only branch-X `Form` rows.
- Role with `cawangan.view-all` (koordinator) → sees all.
- Lawyer → unaffected.
- Toggling `cawangan.view-all` on a scoped role flips visibility (proves permission-driven).

### 8d. Admin UI
- `urus.peranan` required (403 without).
- Permission matrix update persists + cache resets + takes effect on next request.
- System role delete/rename blocked.

---

## 9. Rollout / rollback

**Deploy order (single release, must be atomic-ish):**
1. `composer install` (Spatie present).
2. `php artisan migrate` (Spatie tables).
3. `php artisan db:seed --class=RolePermissionSeeder` (roles + perms + matrix, idempotent).
4. Run user role-assignment migration/command (backfill).
5. New code (permission middleware + controller `can()` + scope) goes live in the same release.
6. `php artisan permission:cache-reset` + `config:clear`.

> On Hostinger: steps 2–4,6 run via SSH (port 65002) per project deploy convention; webhook does pull + `composer install` only.

**Rollback:** revert the release commit (routes back to `role:` + `EnsureRole`, custom `hasRole` restored). Spatie tables can remain (unused) or be rolled back via `migrate:rollback`. `users.role` column untouched throughout → old gating fully functional on revert. This is why the column is retained this batch.

---

## 10. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Regression locks out a role on live features | High | mirror-seed exactly from §6; route×role matrix test GREEN pre/post; staged deploy |
| `hasRole()` signature collision silently mis-authorizes | High | remove custom method; convert all callers to array form; test covers each caller |
| Half-applied deploy (tables/seed missing) bricks auth | High | idempotent seeder; documented deploy order; rollback via column retention |
| `users.role` vs Spatie drift | Medium | dual-write in one transaction; column = display only; later cleanup batch |
| Permission cache stale after matrix edit | Medium | cache-reset on every `syncPermissions` |
| Privilege escalation via matrix UI | High | `urus.peranan` = admin only; system roles protected; audit-log matrix changes |
| Spatie v7 API drift vs assumptions | Low | verified L13/PHP8.3 support via docs; pin `^7.0` |

---

## 11. Out of this batch (explicit)
- New advisory permissions + modules (batches 8–13).
- Public `awam` portal + role (batch 13).
- Finer-grained per-action kes permissions (read vs write split) — mirror coarse today; refine later.
- Dropping `users.role` column.
- Per-user (non-role) direct permissions.

---

## 12. Revised batch sequence
**7** RBAC refactor (this) → **8** Masters (cawangan/jkm/penjara + 3-level kategori_kn + jawatan, gated by new perms) → **9** Khidmat Nasihat wizard → **10** Appointment/slot/calendar → **11** Officer processing → **12** Feedback + reports → **13** Public portal (awam).
