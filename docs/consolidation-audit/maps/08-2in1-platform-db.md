# Map 08 — 2in1 Platform (Cross-Cutting) + Database

> **Audit scope:** the NEW consolidated Laravel app **"2in1"** at
> `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/2in1`.
> This map covers the **cross-cutting platform** (auth, RBAC, audit, branch isolation,
> chatbot proxy, middleware, security) and the **full database** (every migration,
> every table, every model). Domain feature flows (rekod-kes, agihan, khidmat nasihat,
> janji temu) are mapped elsewhere; here they appear only as table/permission inventory.
>
> READ-ONLY audit. Nothing in the source was modified.

---

## 0. Platform stack (as built)

| Layer | Choice | Evidence |
|---|---|---|
| Framework | Laravel 12/13 (slim skeleton, `bootstrap/app.php`) | `bootstrap/app.php` |
| PHP | 8.3 | `CLAUDE.md` |
| DB | MySQL 8.4 (Laragon local `iguaman_2in1`, root) | `CLAUDE.md` |
| Auth | Plain `Auth::attempt` + Blade (NO Filament/Breeze/Jetstream) | `SystemAuthController`, `PublicAuthController` |
| RBAC | **spatie/laravel-permission** (single guard `web`, teams OFF) | `config/permission.php:151 teams=false` |
| Views | Blade + vanilla JS | — |
| Sessions | DB driver (`sessions` table) | `0001_01_01_000000` |
| Queue/jobs | DB driver (`jobs`/`job_batches`/`failed_jobs`) — present but no jobs defined | `0001_01_01_000002` |
| PDF | dompdf (cetakan) | `CetakanController` |
| Excel | maatwebsite (KN reports, statistik) | routes batch 12 |

---

## 1. Authentication

Three login surfaces, **one `users` table**, **one `web` guard**. Landing area is decided
by `user_type` via `User::homeRoute()`.

| Surface | Controller | Login key | Routes | Notes |
|---|---|---|---|---|
| Staff + Lawyer | `SystemAuthController` | **email** + password + numeric captcha | `GET/POST /system/login`, `POST /logout` | `Auth::attempt(['email','password','is_active'=>true])`. Captcha sum stored in session `captcha_sum`. `last_login_at` stamped. `throttle:10,1` on attempt. |
| Citizen (Awam) | `Awam\PublicAuthController` | **nokp** (IC) + password + captcha | `GET/POST /awam/login`, `GET/POST /awam/daftar`, `POST /awam/logout` | `Auth::attempt(['nokp','password','user_type'=>'awam','is_active'=>true])`. Self-register creates `user_type=awam` + assigns role `awam`. Honeypot + captcha + `throttle:6,1`/`10,1`. |
| Password reset | `PasswordResetController` | email (Laravel Password broker) | `/password/forgot`, `/password/reset/{token}` | Standard `password_reset_tokens` table. |
| Forced change | `SystemAuthController::changePassword` | — | `/password/change` | Migrated accounts pinned here by `ForcePasswordChange` middleware until `must_change_password=false`. |

**`homeRoute()` dispatch** (`app/Models/User.php:74`):
`awam` → `awam.dashboard` · `lawyer` → `peguam.dashboard` · else (staff) → `system.utama`.

**Guest redirect** (`bootstrap/app.php:16`): `redirectGuestsTo(route('system.login'))` — there is
**no default `login` route**; everything points at `system.login`.

**Captcha:** trivial 2-number sum (`random_int(1,9)+random_int(1,9)`) stored in session. Legacy parity, weak.

---

## 2. RBAC — roles, permissions, full matrix

### 2.1 Engine

- **spatie/laravel-permission**, guard `web`, **teams = false** (`config/permission.php:151`).
- Permission tables created by `2026_06_29_194444_create_permission_tables` (stock spatie 6 migration):
  `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`.
- Middleware aliases registered in `bootstrap/app.php:18`: `role`, `permission`, `role_or_permission`
  → spatie `RoleMiddleware` / `PermissionMiddleware` / `RoleOrPermissionMiddleware`.
- **Super-admin shortcut:** `AppServiceProvider::boot()` →
  `Gate::before(fn($u) => $u->hasRole('admin') ? true : null)`. `admin` bypasses **every**
  permission check. It is therefore NOT enumerated per-permission in the seeder (only granted
  `urus.peranan` so the matrix UI resolves).
- **Unauthorized handling** (`bootstrap/app.php:35`): `UnauthorizedException` → API `403 JSON`;
  awam user hitting staff route → hard `abort(403)`; staff/lawyer → redirect to their own `homeRoute()`.

### 2.2 All roles (9)

| Role | `user_type` | Source / meaning |
|---|---|---|
| `admin` | staff | super-admin (Gate::before bypass) |
| `pengarah` | staff | Director — approves/rejects, supports assignment |
| `koordinator` | staff | Coordinator — branch-wide ops |
| `pegawai` | staff | front-line officer |
| `ppuu` | staff | Penolong Pegawai Undang-Undang — case distributor (peguam-panel legacy tier) |
| `pembantu_tadbir` | staff | clerk (peguam-panel legacy tier) |
| `ketua_pengarah` | staff | Director General — final approval (peguam-panel legacy tier) |
| `peguam` | lawyer | external panel lawyer |
| `awam` | awam | citizen portal (seeded separately by migration `130002`) |

Constants live in `User::ROLE_*`; `User::STAFF_ROLES` (7), `User::APPROVER_ROLES`
(`pengarah`, `ketua_pengarah`, `admin`). `RoleController::SYSTEM_ROLES` (8, excludes `awam`)
protects them from rename/delete in the UI.

> **Inconsistency:** the 8 system roles + permission matrix are seeded by **TWO** sources:
> `database/seeders/RolePermissionSeeder.php` (the canonical one, run by `DatabaseSeeder`) and
> migration `2026_06_30_130002_seed_awam_role_permission.php` (adds ONLY the `awam` role +
> `awam.portal` permission). The `awam` role is therefore **not** in `RolePermissionSeeder::ROLES`
> and **not** in `RoleController::SYSTEM_ROLES` — so an admin could rename/delete the `awam` role
> through the Peranan UI, which would break the citizen portal gate. (HIGH finding.)

### 2.3 All permissions (40) + role→permission seed mapping

Source of truth: `RolePermissionSeeder::MATRIX` (admin omitted = Gate::before) + migration `130002` (awam).
✓ = granted. `admin` = all (super-admin).

| Permission | pengarah | koordinator | pegawai | ppuu | pembantu_tadbir | ketua_pengarah | peguam | awam |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| `system.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `kes.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `kes.create` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `kes.update` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `kes.keputusan` | ✓ | | | | | ✓ | | |
| `pengantaraan.manage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `mahkamah.manage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `lampiran.manage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `cetakan.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `oyd.manage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `kpi.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `laporan.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `statistik.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `agihan.manage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `agihan.pengarah` | ✓ | | | | | | | |
| `agihan.ppuu` | | ✓ | | ✓ | | | | |
| `agihan.kp` | | | | | | ✓ | | |
| `khidmat.view` | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| `khidmat.manage` | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| `khidmat.proses` | ✓ | ✓ | ✓ | | | | | |
| `peguam_panel.manage` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `peguam.permohonan.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `peguam.semak` | | ✓ | | ✓ | ✓ | | | |
| `peguam.sokong` | ✓ | | | | | | | |
| `peguam.keputusan` | | | | | | ✓ | | |
| `selenggara.pegawai` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.poster` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.ref_kes` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.mahkamah_ref` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.cuti` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.cawangan` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.kategori_kn` | ✓ | ✓ | | | | ✓ | | |
| `selenggara.jawatan` | ✓ | ✓ | | | | ✓ | | |
| `slot.view` | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| `slot.manage` | ✓ | ✓ | | | ✓ | ✓ | | |
| `urus.pengguna` | ✓ | ✓ | | | | ✓ | | |
| `audit.view` | ✓ | ✓ | | | | ✓ | | |
| `menu.selenggara` | ✓ | ✓ | | | | | | |
| `cawangan.view-all` | | ✓ | | | | ✓ | | |
| `urus.peranan` | | | | | | | | | (admin only) |
| `lawyer.area` | | | | | | | ✓ | |
| `awam.portal` | | | | | | | | ✓ |

**Permission groups** (the Akses matrix UI groups by the prefix before the first `.` —
`RolePermissionController::edit`): `system`, `kes`, `pengantaraan`, `mahkamah`, `lampiran`,
`cetakan`, `oyd`, `kpi`, `laporan`, `statistik`, `agihan`, `khidmat`, `peguam_panel`, `peguam`,
`selenggara`, `slot`, `urus`, `audit`, `menu`, `cawangan`, `lawyer`, `awam`.

> **Observation — declared-but-unenforced permissions:** several permissions are seeded but
> **not used as route middleware** (route gating uses only a subset). Examples not found as
> `permission:` guards in `routes/web.php`: `kes.view`, `kes.create`, `kes.update`,
> `kes.keputusan`, `pengantaraan.manage`, `mahkamah.manage`, `lampiran.manage`, `cetakan.view`,
> `oyd.manage`, `kpi.view`, `peguam_panel.manage`, `peguam.permohonan.view`, `peguam.semak`,
> `peguam.sokong`, `peguam.keputusan`, `menu.selenggara`. Many of these case/mediation/court/OYD
> routes are gated only by the **outer** `permission:system.view` group and then role-checked
> (or permission-checked) **inside the controller**. The 3-tier agihan + tarik-diri + kemaskini-bidang
> actions use `role:` middleware (e.g. `role:pengarah|admin`) rather than permissions. So role
> gating is split across three mechanisms: route `permission:`, route `role:`, and in-controller
> checks. (MEDIUM — audit each controller for the in-method gate before trusting the matrix.)

### 2.4 RBAC management UI

| Controller | Routes (all `permission:urus.peranan`, admin-only) | Action |
|---|---|---|
| `RoleController` | `/peranan` CRUD | create/rename/delete roles; system roles protected; writes `Audit::log('roles', …)` |
| `RolePermissionController` | `/peranan/{role}/akses` | per-role permission matrix `syncPermissions`; `Audit::log` |
| `UserController` | `/pengguna` CRUD (`permission:urus.pengguna`) | user CRUD; `syncRoles([role])`; `must_change_password=true` for new STAFF only; self-delete blocked |

---

## 3. Branch isolation (CawanganScope)

`app/Models/Scopes/CawanganScope.php` — global Eloquent scope.

- **Applied to EXACTLY ONE model:** `Form` (the `forms` case spine), via
  `Form::booted() → addGlobalScope(new CawanganScope())`. No other model carries it.
- Logic: if `user->isStaff()` AND `user->cawangan` is set AND user lacks `cawangan.view-all`
  → `WHERE forms.cawangan = user.cawangan`. Lawyers, no-branch users, and `cawangan.view-all`
  holders (koordinator, ketua_pengarah) see everything.
- `cawangan.view-all` is **memoized per-request per-user** (`$viewAllMemo`) to avoid repeated Gate calls.

> **GAP:** KN (`khidmat_nasihat`), `temu_janji`, `slot_temu_janji`, OYD (`butiran_oyd`), and the
> lawyer-panel tables have **no CawanganScope**. KN branch isolation is applied **manually** inside
> `LaporanKnService` / `KhidmatProsesService` (per the batch-12 route comment "KN has no CawanganScope").
> Branch enforcement is therefore inconsistent across the domain — only `forms` is auto-scoped.

---

## 4. Audit trail

- **Table:** `audit_trail` (legacy, imported verbatim). Model `App\Models\AuditTrail`
  (`$timestamps=false`, `$guarded=['id']`, `modified_date` cast datetime).
- **Writer:** `App\Support\Audit::log($table, $recordId, $action, $remarks, $by)` — record-level
  entries only (`field_name`/`old_value`/`new_value` always NULL). Actor defaults to
  `auth()->user()->name ?? 'sistem'`.
- **Actions enum:** `INSERT`, `UPDATE`, `DELETE`, `APPROVE`, `REJECT`.
- **Viewer:** `AuditController@index` → `/audit` (`permission:audit.view`). Filter by table/action/free-text; paginate 30.
- **Where audit rows are written** (grep `Audit::log` / `AuditTrail::`): 25+ call sites across
  controllers and services. By target `table_name`:
  - `forms` — AgihanService (6), TarikDiriService (3), LebihMasaService (1), AgihanController, KeputusanController (3)
  - `khidmat_nasihat` — KhidmatNasihatController (2), KhidmatProsesService (2), Awam\PermohonanController (3)
  - `temu_janji` — KhidmatProsesService (1)
  - `peguam_panel` — PeguamLifecycleService (2), PeguamPanelController (1)
  - `butiran_peguam_panel_2` — PermohonanPeguamController (approve/reject)
  - `users` — PermohonanPeguamController (lawyer login provisioning), UserController (CRUD)
  - `cawangan` / `bilik` — CawanganController, SlotGenerationController
  - `ref_cuti` — CutiController, CutiNegeriController
  - `ref_jawatan`, `ref_kes`, `ref_kategori_kn`, `ref_kategori_kes_kn`, `ref_subkategori_kn` — selenggara CRUD
  - `butiran_oyd`, `uploaded_files`, `posters`, `penutupan_operasi`, `slot_temu_janji`,
    `mahkamah_sivil`/`mahkamah_syariah`, `pegawai_jbg`, `roles` — respective controllers

> **Note:** `audit_trail.table_name`/`record_id` are free strings/ints with **no FK** — they are a
> denormalized log, not relational. `Audit::log('roles', 0, …)` and `Audit::log('users', 0, …)`
> use `record_id=0` placeholder in a few spots.

---

## 5. Middleware inventory

| Middleware | Type | Registered | Behavior |
|---|---|---|---|
| `SecurityHeaders` | web (append) | `bootstrap/app.php:26` | Adds `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`. **No CSP, no HSTS.** |
| `ForcePasswordChange` | web (append) | `bootstrap/app.php:27` | Pins `must_change_password` users to `/password/change`. Allowed routes: `password.change`, `password.change.update`, `system.logout`, `awam.logout`. |
| `role` (spatie) | alias | `bootstrap/app.php:18` | Role gate (used: agihan tarik-diri, kemaskini-bidang, peguam-panel lifecycle). |
| `permission` (spatie) | alias | `bootstrap/app.php:19` | Permission gate (primary route gating). |
| `role_or_permission` (spatie) | alias | `bootstrap/app.php:21` | Declared; usage rare. |

> **There is NO `CawanganScope` middleware** — branch isolation is an Eloquent global scope, not HTTP
> middleware. Only two custom HTTP middleware exist (`SecurityHeaders`, `ForcePasswordChange`).

**Throttling** (route-level `throttle:`): chatbot 20/min, peguam-daftar 6/min, awam-daftar 6/min,
awam-login 10/min, awam-permohonan-store 10/min, awam-lampiran 20/min, system-login 10/min,
maklum-balas 6/min.

---

## 6. Chatbot proxy (cross-cutting)

`ChatbotController@ask` → `POST /chatbot/ask` (PUBLIC, `throttle:20,1`).

- Server-side proxy to a standalone **Python/FastAPI** JBG chatbot microservice. Browser widget
  never sees bot credentials.
- Flow: `POST {base}/generate_token` (basic auth) → bearer token → `POST {base}/forward_message`
  `{message, session_id, user_name}`. Returns `content_raw`.
- Config: `config/services.php` → `services.chatbot.{url,user,pass,timeout}` (env-driven). If any of
  url/user/pass empty → `503` "Perkhidmatan chatbot belum dikonfigurasi."
- Per-session conversation id stored in session `chatbot_sid` (random int).
- Input validated: `message` required string max 1000. Graceful 502 on token/message failure.
- Detailed cross-cutting map: sibling `04-chat-cbjbg.md`.

---

## 7. DATABASE — full table inventory

### 7.1 How the schema is built

1. **Laravel platform tables** — standard migrations:
   `users`, `password_reset_tokens`, `sessions` (`0001_01_01_000000`); `cache`, `cache_locks`
   (`…000001`); `jobs`, `job_batches`, `failed_jobs` (`…000002`).
2. **Legacy domain tables (20)** — imported **verbatim from a raw mysqldump**:
   `2026_06_29_000001_import_legacy_domain_tables.php` runs `DB::unprepared()` on
   `database/schema/legacy-domain.sql` (`--no-data`, 20 `CREATE TABLE`s). **The schema for these
   tables lives in raw SQL, not in Blueprint migrations** — a key consolidation characteristic.
3. **Keys/FKs/indexes** added on top (`2026_06_29_000002`).
4. **New feature tables** (batches 8–13) — proper Blueprint migrations.

### 7.2 Full table list (47 tables)

Legend: **L** = legacy (raw SQL dump) · **N** = new Blueprint · **P** = Laravel platform · **S** = spatie.

| # | Table | Src | Model | Controller(s) | Key cols / PK | FKs / indexes | Notes |
|---|---|---|---|---|---|---|---|
| 1 | `users` | P | `User` | UserController, *AuthControllers | `id` PK; `email`(unique→nullable @130003), `username`, `password`, `user_type` enum(staff/lawyer/awam @130001), `role`, `cawangan`, `nokp`(unique @130004), `id_peguam_panel`, `is_active`, `must_change_password`(@000003), `last_login_at` | idx `(user_type,role)`, `username`, unique `nokp` | Unified auth: collapses legacy `users`+`users_peguam_panel_2/_3`. |
| 2 | `password_reset_tokens` | P | — | PasswordResetController | `email` PK, `token`, `created_at` | — | Stock. |
| 3 | `sessions` | P | — | — | `id` PK, `user_id`, `payload`, `last_activity` | idx user_id, last_activity | DB session driver. |
| 4 | `cache` | P | — | — | `key` PK | — | |
| 5 | `cache_locks` | P | — | — | `key` PK | — | |
| 6 | `jobs` | P | — | — | `id` PK, `queue` | idx queue | **No jobs/queue workers defined in app.** |
| 7 | `job_batches` | P | — | — | `id` PK | — | unused |
| 8 | `failed_jobs` | P | — | — | `id` PK, `uuid`(unique) | — | unused |
| 9 | `permissions` | S | (spatie) | RolePermissionController | `id` PK, `name`, `guard_name` | unique `(name,guard_name)` | 40 perms seeded. |
| 10 | `roles` | S | (spatie) | RoleController | `id` PK, `name`, `guard_name` | unique `(name,guard_name)` | 9 roles. |
| 11 | `model_has_permissions` | S | — | — | composite PK | FK→permissions | direct-grant pivot (unused in seed). |
| 12 | `model_has_roles` | S | — | — | composite PK | FK→roles | user↔role pivot. |
| 13 | `role_has_permissions` | S | — | — | `(permission_id,role_id)` PK | FK→both | role↔perm pivot. |
| 14 | **`forms`** | L | `Form` | KesController + ~10 case controllers | `id` PK; **98 cols** (78 base + `is_duplicate` + 4 drifted @000004 + …) — the monolith | idx `no_fail`,`nokp`,`cawangan`,`status` (@000002) | **The 78→98-col case-spine monolith.** `$timestamps=false`. CawanganScope applied. See §8. |
| 15 | `audit_trail` | L | `AuditTrail` | AuditController + Audit::log | `id` PK; `table_name`,`record_id`,`action_type`(enum),`field_name`,`old/new_value`,`remarks`,`modified_by`,`modified_date` | none | denormalized log, no FK. |
| 16 | `butiran_oyd` | L | `Oyd` (`$table='butiran_oyd'`) | OydController | `(id,kp_oyd)` PK; `kp_oyd` unique; 20 `*_oyd` cols | unique kp_oyd | victim/assisted-person registry. |
| 17 | `butiran_peguam_panel` | L | `ButiranPeguamPanel` | — (read-only?) | `id` PK; ~60 camelCase cols (firm/CSO/bank…) | — | legacy v1 lawyer application; superseded by `_2`. |
| 18 | `butiran_peguam_panel_2` | L | `ButiranPeguamPanel2` | PermohonanPeguamController | `id` PK; `kpBaru` unique; +`semakan_ppuu`/`ulasan`/`tarikh` (@000001) | unique kpBaru | active lawyer-application approval record. |
| 19 | `butiran_peguam_panel_3` | N | `ButiranPeguamPanel3` | (PeguamDaftar/PeguamPanel) | `id`(increments); `kpBaru`(idx); CLP/CSO1-5/YBGK/ADR/Sijil/eVendor | idx kpBaru | **reconstructed** (no dump) — qualifications. |
| 20 | `butiran_peguam_panel_4` | N | `ButiranPeguamPanel4` | " | `id`; `kpBaru`(idx); firm + indemnity insurance | idx kpBaru | **reconstructed** — firm/insurance. |
| 21 | `butiran_peguam_panel_5` | N | `ButiranPeguamPanel5` | " | `id`; `kpBaru`(idx); bank account | idx kpBaru | **reconstructed** — bank. |
| 22 | `butiran_peguam_panel_6` | N | `ButiranPeguamPanel6` | KemaskiniBidang/Peguam | `id`; `kpBaru`(idx); `category`,`checkbox_value`,`checkbox_value_status` | idx kpBaru | **reconstructed** — practice-area specialisation rows. |
| 23 | `items` | L | `Item` | — | `id` PK; `name`(unique),`description` | unique name | **Orphan/demo table — no controller, no routes. Candidate DEAD.** |
| 24 | `laporan_kes` | L | `LaporanKes` | MahkamahController (storeLaporan) | `id` PK; `id_kes`(string!),`no_fail`,`status_kes`,`fakta_ringkas`… | none (no FK) | court hearing reports child of `forms`; `id_kes` is `varchar(20)` not int — **no FK, type mismatch**. |
| 25 | `mahkamah_sivil` | L | `MahkamahSivil` | MahkamahRefController | `id` PK; nama/negeri/lokaliti/jenis | none | civil court reference. |
| 26 | `mahkamah_syariah` | L | `MahkamahSyariah` | MahkamahRefController | `id` PK; same shape | none | syariah court reference (duplicate structure of sivil). |
| 27 | `pegawai_jbg` | L | `PegawaiJbg` | PegawaiController | `id` PK; nama/cawangan/jawatan/bahagian/jenis_pegawai/status_aktif | none | JBG officer registry. |
| 28 | `peguam_panel` | L→N | `PeguamPanel` | PeguamPanelController, AgihanService | **PK added @000002** (`id` AUTO_INCREMENT FIRST); kp_peguam, firm; +`statusAktif`/`sebabTidakAktif`/`tarikhTidakAktif` (@000005) | none | legacy had **no PK**; now surrogate `id`. Lawyer master keyed by `kp_peguam`. |
| 29 | `posters` | L | `Poster` (`$table='posters'`) | PosterController | `id` PK; tajuk/details/status/image_path | none | e-Poster. **Mixed collation** `utf8mb4_0900_ai_ci` (others `_general_ci`). |
| 30 | `ref_cuti` | L | `RefCuti` | CutiController, CutiNegeriController | `id_cuti` PK; nama_cuti/tarikh_mula/tarikh_tamat/idnegeri | none | **CHARSET=latin1** (all others utf8mb4). Public-holiday master. |
| 31 | `ref_kes` | L | `RefKes` | RefKesController | `id` PK; id_kes/jenis_kes/kategori_kes/deskripsi/aktif_kes | none | litigation case-type taxonomy. **NOT the KN tree** (see memory note). |
| 32 | `ref_lokasi_berguam` | L | `RefLokasiBerguam` | — | `id` PK; `nama` | none | **No controller — read-only lookup. Candidate near-dead** (referenced by lawyer-panel forms only). |
| 33 | `ref_negeri` | L | `RefNegeri` | — (seeded by RefNegeriSeeder) | `id` PK; nama/aktif/kategori | none | state reference; `id` is legacy `int` so dependents use plain `unsignedInteger`+index, **no FK** by design. |
| 34 | `sejarah_pegawai` | L | `SejarahPegawai` | (case history) | `id` PK; `id_kes`(int) | **FK** `id_kes→forms.id` (in dump) | officer-change history. Only legacy table that **ships its FK**. |
| 35 | `sejarah_peguam_panel` | L→N | `SejarahPeguamPanel` | AgihanService, TarikDiri | `id` PK; `id_kes`; +`status_rekod`/`permohonan_kali`/tarik-diri cols (@000004) | **FK** `spp_id_kes_fk→forms.id` nullOnDelete (@000002), idx | lawyer-assignment history. |
| 36 | `sejarah_sidang` | L | `SejarahSidang` | PengantaraanController | `id` PK; `id_kes`(int, NOT NULL) | **FK** `ss_id_kes_fk→forms.id` restrictOnDelete (@000002), idx | hearing-postponement history. |
| 37 | `sejarah_ppuu` | N | `SejarahPpuu` | AgihanService (spine) | `id`(increments); `id_kes`(idx),`idPPUU`,`status_rekod`(idx); PPUU/Pengarah/KP decision cols | idx id_kes, idPPUU, status_rekod | **reconstructed** (no dump). 3-tier assignment spine. **No FK to forms** (plain unsignedInteger). |
| 38 | `uploaded_files` | L→N | `UploadedFile` | LampiranController, Awam\Permohonan | `id` PK; nama/file_*; +`kpBaru`/`doc_type`(@000003), `id_kes`(@000005), `id_khidmat`(@000010) — all idx, **no FK** | idx kpBaru,doc_type,id_kes,id_khidmat | polymorphic-ish: links to lawyer (kpBaru), case (id_kes), KN (id_khidmat) by **plain indexed cols, no FKs**. |
| 39 | `cawangan` | N | `Cawangan` | CawanganController, SlotGeneration | `id` PK; `jenis` enum(JBG/JKM/PENJARA)(idx), `nama`(unique = legacy branch string), `negeri_id`(idx, no FK); +session cfg `hari_minggu`/`masa_buka`/`masa_tutup`/`tempoh_slot_minit`(@120002) | idx jenis, unique nama, idx negeri_id | Branch master. `nama` matches legacy `forms.cawangan` string so CawanganScope keeps working. |
| 40 | `bilik` | N | `Bilik` | CawanganController | `id` PK; `cawangan_id`, `nama_bilik` | **FK** cawangan_id→cawangan cascadeOnDelete | rooms. |
| 41 | `ref_jawatan` | N | `RefJawatan` | JawatanController | `id` PK; `nama`(unique),`aktif` | unique nama | staff job-title master (normalises `pegawai_jbg.jawatan` free text). |
| 42 | `ref_kategori_kn` | N | `RefKategoriKn` | KategoriKnController | `id` PK; `jenis_kategori`,`aktif` | — | KN tree L1 (Jenis Khidmat). |
| 43 | `ref_kategori_kes_kn` | N | `RefKategoriKesKn` | KategoriKnController | `id` PK; `kategori_id`,`nama`,`aktif` | **FK** kategori_id→ref_kategori_kn cascade | KN tree L2. |
| 44 | `ref_subkategori_kn` | N | `RefSubkategoriKn` | KategoriKnController | `id` PK; `kategori_kes_id`,`nama`,`aktif` | **FK** kategori_kes_id→ref_kategori_kes_kn cascade | KN tree L3. |
| 45 | `khidmat_nasihat` | N | `KhidmatNasihat` | KhidmatNasihatController, KhidmatProses, Awam\Permohonan | `id` PK; `no_permohonan`(unique), `jenis_permohonan`/`jenis_wakil` enums, `status_kn` enum(DRAF/BAHARU/DALAM_PROSES/SELESAI/BATAL)(idx); FKs id_pengguna/id_pegawai_kn/cawangan_id/id_kategori/id_subkategori; +income/wakil/saringan/officer cols (@110002-110004) | **FKs** →users(2)/cawangan/ref_kategori_kn/ref_subkategori_kn nullOnDelete; idx status_kn, id_negeri | KN core record. `id_negeri`/`id_mahkamah`/`id_temu_janji`/`id_forms` are **no-FK** integer links. Policy-gated for awam (owns). |
| 46 | `slot_temu_janji` | N | `SlotTemuJanji` | SlotController, SlotGeneration | `id` PK; cawangan_id/bilik_id, tarikh_slot, masa_*, `is_temujanji`(booked flag) | **FK** cawangan_id cascade, bilik_id nullOnDelete; idx (cawangan_id,tarikh_slot) | generated appointment slots. |
| 47 | `temu_janji` | N | `TemuJanji` | KhidmatProses, JadualJanjiTemu | `id` PK; `id_khidmat_nasihat`(no FK), slot_temu_janji_id, cawangan_id, `status` enum(MENUNGGU/DISAHKAN/HADIR/TIDAK_HADIR/SELESAI/BATAL), `id_pegawai_kn`(no FK) | **FK** slot nullOnDelete, cawangan cascade; idx (cawangan_id,tarikh) | appointments. **`id_khidmat_nasihat` + `id_pegawai_kn` are no-FK** (built in parallel batch). |
| 48 | `penutupan_operasi` | N | `PenutupanOperasi` | PenutupanOperasiController | `id` PK; cawangan_id/bilik_id, tarikh_mula/tamat, sebab | **FK** cawangan cascade, bilik nullOnDelete; idx (cawangan_id,tarikh_mula) | operational closures (calendar exclusions). |
| 49 | `maklum_balas` | N | `MaklumBalas` | MaklumBalasController | `id` PK; `khidmat_nasihat_id`(**unique** FK), soalan_1a-e/1_lain, `soalan_2a` enum, soalan_cadangan, dihantar_dari_ip | **FK** khidmat_nasihat_id→khidmat_nasihat cascade **+ unique** | one feedback per KN. PUBLIC (no auth) submission. |

> Table count is **49 rows above** (numbering continuous); the 20 legacy dump tables = #14–17,
> 18, 23–36, 38 minus the 4 reconstructed `_3.._6` and `sejarah_ppuu`. The 16 base legacy + the
> two legacy lawyer tables = 20 from `legacy-domain.sql`; the rest are platform/spatie/new.

### 7.3 Legacy source-of-truth dump

- `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/sistemspk.sql`
  — **51.7 MB** full data dump (NOT read whole). `CREATE TABLE` names extracted:
  `audit_trail`, `butiran_oyd`, `butiran_peguam_panel_2`, **`butiran_peguam_panel_2_original`**,
  `butiran_peguam_panel_3/4/5/6`, `forms`, `laporan_kes`, `mahkamah_sivil`, `mahkamah_syariah`,
  `pegawai_jbg`, `posters`, **`ref_cawangan`**, `ref_cuti`, `ref_kes`, `ref_lokasi_berguam`,
  `ref_negeri`, `sejarah_pegawai`, `sejarah_peguam_panel`, **`sejarah_ppuu`**, `sejarah_sidang`,
  `uploaded_files`, **`users_peguam_panel_2`**, `users`.
- **Reconciliation vs 2in1:**
  - The dump **DOES** contain `butiran_peguam_panel_3..6` and `sejarah_ppuu` — yet the 2in1
    migrations (`…000002`, `…000004`) claim these were "never dumped / reconstructed from source
    code". **The `_3..6`/`sejarah_ppuu` Blueprint shapes should be reconciled against this dump,
    not just the legacy PHP** — there may be column drift. (MEDIUM/HIGH finding.)
  - `ref_cawangan` (legacy) was **dropped** in favor of the new `cawangan` Blueprint table.
  - `users_peguam_panel_2` + legacy `users` were **collapsed** into the unified `users` table.
  - `butiran_peguam_panel_2_original` is a legacy backup table — **not** carried into 2in1.
  - `butiran_peguam_panel` (v1) is in `legacy-domain.sql` but NOT in the big dump's CREATE list →
    it exists in 2in1 (imported) but may be empty/superseded.

---

## 8. The `forms` monolith (78 → 98 columns)

`forms` is the legal-aid **case spine** — one wide table covering application + means-test +
mediation + court + assignment + closure. Model docblock says "94 cols"; actual ≈ **98** after drift.

| Layer | Columns | Source |
|---|---|---|
| Base dump | 77 cols ending `…created_at` | `legacy-domain.sql:162` |
| + `is_duplicate` (tinyint) | 78th | dump |
| + 4 **drifted_forms** cols | `justifikasi_rujuk_pp`, `justifikasi_lain_rujuk_pp`, `status_rekod`, `tarikh_mohon_khidmat_pp` | `2026_06_29_000004_add_drifted_forms_columns.php` |
| + earlier drift already in dump | `pengantaraan_kategori_kes`, `pembatalan_borang_1`, `setuju_pengantara`, `nama_penjaga`, `nokp_penjaga`, `tarikh_daftar`, `tarikh_KPKemaskini`, `tarikh_pengarahKemaskini`, `sebab_tutup_fail`, `alasan_kesilapan_no_fail`, `alasan_pemindahan_fail`, `didaftarkan_oleh`, `jenis_sumbangan`, `kategori_kes_borang`, `agamaLain`, `sebab_menolak`, `sebab_Tidak_Diluluskan` | dump tail |

**Column-name smells inside `forms`:** mixed casing (`tarikh_KPKemaskini`, `sebab_Tidak_Diluluskan`,
`tarikh_pengarahKemaskini`) alongside snake_case; duplicate-intent fields (`kategori_kes`,
`kategori_kes2`, `kategori_kes_borang`, `jenis_kategori`, `pengantaraan_kategori_kes`);
denormalized actor strings (`nama_pegawai`, `nama_pegawai_yang_dapat_kes`, `didaftarkan_oleh`).
The model docblock flags "decompose into Case + detail tables in a later phase" — **not yet done**.

> `forms.id` is the de-facto FK target for `sejarah_*` (int) and `uploaded_files.id_kes`, but
> `laporan_kes.id_kes` is **`varchar(20)`** — a type mismatch that prevents a real FK.

---

## 9. Data-integrity findings

### Duplicate / overlapping tables
- **`butiran_peguam_panel` (v1) vs `_2`** — two lawyer-application tables; v1 superseded, likely dead data.
- **`mahkamah_sivil` vs `mahkamah_syariah`** — identical structure; could be one table + `jenis` discriminator (mirrors the `cawangan.jenis` pattern adopted for branches).
- **Lawyer profile split across `_2`/`_3`/`_4`/`_5`/`_6`** keyed by `kpBaru` string — a 1-row entity fragmented into 5 tables with **no FKs**, joined by IC string. Fragile.
- **`ref_kes` (litigation) vs `ref_kategori_kn` tree (advisory)** — intentionally separate (per memory note "ref_kes is not the KN tree"), correct but easy to confuse.

### Missing FKs (links exist as plain indexed columns)
- `khidmat_nasihat`: `id_negeri`, `id_mahkamah`, `id_temu_janji`, `id_forms` — no FK.
- `temu_janji`: `id_khidmat_nasihat`, `id_pegawai_kn` — no FK (parallel-batch build).
- `uploaded_files`: `id_kes`, `id_khidmat`, `kpBaru`, `doc_type` — no FK.
- `sejarah_ppuu`: `id_kes`, `idPPUU` — no FK (plain unsignedInteger).
- `laporan_kes.id_kes` — `varchar`, **type-mismatched**, no FK possible without migration.
- All lawyer `_3.._6` ↔ `_2` joins on `kpBaru` string — no FK.
- `cawangan.negeri_id` → `ref_negeri.id` — no FK (legacy `int` PK, deliberate).
- `audit_trail.record_id` — denormalized, no FK (by design).

### Inconsistent naming / charset
- camelCase columns in legacy lawyer + reconstructed tables (`kpBaru`, `noTelBimbit`, `statusAktif`) vs snake_case elsewhere.
- `ref_cuti` is **`CHARSET=latin1`**; `posters` is **`utf8mb4_0900_ai_ci`**; everything else `utf8mb4_general_ci` — collation mismatch risk on joins/`LIKE`.
- PK naming varies: `id`, `id_cuti`, `(id,kp_oyd)` composite, `increments` vs `bigIncrements`.
- Mixed integer width: legacy tables use `int`; new tables use `bigint` (`foreignId`) — FK/type friction between legacy and new domains.

### Tables with no model and/or no controller (candidate dead)
| Table | Model? | Controller? | Verdict |
|---|---|---|---|
| `items` | `Item` (exists) | **none** | demo/scaffold table — **DEAD candidate** (no routes reference it). |
| `ref_lokasi_berguam` | `RefLokasiBerguam` | **none** | lookup only (lawyer-panel form data) — near-dead. |
| `butiran_peguam_panel` (v1) | `ButiranPeguamPanel` | **none** active | superseded by `_2` — likely dead. |
| `jobs`/`job_batches`/`failed_jobs` | none | none | infra present, **no queue usage** in app. |
| `model_has_permissions` | (spatie) | none | direct-grant pivot — **unused** (seed grants via roles only). |
| `cache`/`cache_locks` | none | none | only used if cache driver=database. |

### Other
- **`awam` role drift** (§2.2): seeded by migration, missing from `RolePermissionSeeder::ROLES` and `RoleController::SYSTEM_ROLES` → renamable/deletable, would break citizen gate. **HIGH.**
- **Permission/route gating split** across `permission:`, `role:`, and in-controller checks (§2.3) — verify each controller before trusting the matrix.
- **CawanganScope only on `forms`** (§3) — KN/janji-temu branch isolation is manual and inconsistent.
- **No CSP / HSTS** in `SecurityHeaders` (§5).
- Reconstructed `_3.._6`/`sejarah_ppuu` should be reconciled against the **actual dump** that DOES contain them (§7.3).

---

## 10. File index (read during this audit)

| Concern | File |
|---|---|
| Routing/middleware bootstrap | `bootstrap/app.php` |
| Routes | `routes/web.php` |
| Staff/lawyer auth | `app/Http/Controllers/SystemAuthController.php` |
| Citizen auth | `app/Http/Controllers/Awam/PublicAuthController.php` |
| Password reset | `app/Http/Controllers/PasswordResetController.php` |
| User CRUD | `app/Http/Controllers/UserController.php` |
| Role CRUD | `app/Http/Controllers/RoleController.php` |
| Permission matrix | `app/Http/Controllers/RolePermissionController.php` |
| Audit viewer | `app/Http/Controllers/AuditController.php` |
| Chatbot proxy | `app/Http/Controllers/ChatbotController.php` |
| Super-admin gate | `app/Providers/AppServiceProvider.php` |
| User model | `app/Models/User.php` |
| Case spine model | `app/Models/Form.php` |
| Audit writer | `app/Support/Audit.php` |
| Audit model | `app/Models/AuditTrail.php` |
| Branch scope | `app/Models/Scopes/CawanganScope.php` |
| KN policy | `app/Policies/KhidmatNasihatPolicy.php` |
| Middleware | `app/Http/Middleware/SecurityHeaders.php`, `ForcePasswordChange.php` |
| RBAC seed | `database/seeders/RolePermissionSeeder.php` |
| Awam seed | `database/migrations/2026_06_30_130002_seed_awam_role_permission.php` |
| Legacy schema | `database/schema/legacy-domain.sql` |
| All migrations | `database/migrations/*` (28 files) |
| All models | `app/Models/*` (37 models) |
| Legacy dump (names only) | `../sistemspk.sql` (51.7 MB) |
</content>
</invoke>
