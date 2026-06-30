# Phase 7 — User Roles & Access Control (Consolidation Audit)

> **Scope.** The consolidated Laravel app **2in1** at
> `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/2in1`,
> cross-referenced against the four origin systems (legacy `sistem-peguam-panel`,
> legacy `sistem-rekod-kes`, `iGuaman` advisory/janji-temu, chat `cbjbg`).
> **READ-ONLY.** No source modified. Every claim is traced to a real file/line.
>
> Engine: **spatie/laravel-permission**, single guard `web`, **teams = false**
> (`config/permission.php`). Super-admin shortcut: `Gate::before(fn($u)=>$u->hasRole('admin')?true:null)`
> (`app/Providers/AppServiceProvider.php:29`).

---

## 1. Role lineage across the four systems

The 2in1 role set is a **union** of three legacy role models flattened onto one `users.role`
string column + spatie roles. Lineage:

| 2in1 role | `user_type` | peguam-panel (`peranan` int) | rekod-kes (`peranan` int) | iGuaman advisory (Identity string) | Meaning |
|---|---|---|---|---|---|
| `admin` | staff | `5` IT UTM JBG (superadmin) | `1` Admin | `SUPERADMIN` | super-admin, `Gate::before` bypass |
| `ketua_pengarah` | staff | `4` Ketua Pengarah | — | — | final approver (3-tier top) |
| `pengarah` | staff | `3` Pengarah | `2` Pengarah/Ketua Cawangan | — | director: approve/reject/close/sokong |
| `koordinator` | staff | — | — | — | **new in 2in1** — cross-branch ops; holds `cawangan.view-all` |
| `pegawai` | staff | — | `0` Pegawai | `PEGAWAI KHIDMAT NASIHAT` (PKN) | front-line officer + KN processing |
| `ppuu` | staff | `2` PPUU | — | — | case distributor (agihan tier 2) |
| `pembantu_tadbir` | staff | `0` Pembantu Tadbir | — | `PEMBANTU TADBIR` | clerk / counter |
| `peguam` | lawyer | `1` Peguam Panel | (read-only display) | — | external panel lawyer |
| `awam` | awam | — | — | `PELANGGAN` | citizen portal (IC login) |

**Roles in origin systems NOT carried into 2in1 (intentionally or as gaps):**

| Origin role | System | Disposition in 2in1 |
|---|---|---|
| `PENGURUSAN` (reports-only) | iGuaman | **dropped** — no equivalent; report access folded into staff roles. |
| `PEGAWAI PENJARA` / `PEGAWAI JKM` | iGuaman | **dropped as roles** — but `cawangan.jenis` enum still has `JKM`/`PENJARA`, and KN `jenis_wakil` paths exist. Prison/welfare lodging-on-behalf has no role. |
| `PPUU SA` special account | peguam-panel | not modelled (was a hard-coded name exclusion in legacy queries). |
| rekod-kes "JBG WP PUTRAJAYA = HQ" rule | rekod-kes | **replaced** by `cawangan.view-all` permission (koordinator/ketua_pengarah). Cleaner. |

> Total **9 roles** seeded (8 by `RolePermissionSeeder` + `awam` by migration). Matches map 08.

---

## 2. The seeded role → permission matrix (verified against code)

**Source of truth:** `database/seeders/RolePermissionSeeder.php` (`MATRIX`, 40 permission keys;
`admin` omitted because of `Gate::before`) **plus** migration
`database/migrations/2026_06_30_130002_seed_awam_role_permission.php` (adds only the `awam` role
+ `awam.portal` permission).

`✓` = granted in the seed. `admin` = ALL (super-admin). Verified by reading the seeder array directly.

| Permission | pengarah | koordinator | pegawai | ppuu | pembantu_tadbir | ketua_pengarah | peguam | awam |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| system.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| kes.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| kes.create | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| kes.update | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| kes.keputusan | ✓ | | | | | ✓ | | |
| pengantaraan.manage | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| mahkamah.manage | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| lampiran.manage | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| cetakan.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| oyd.manage | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| kpi.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| laporan.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| statistik.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| agihan.manage | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| agihan.pengarah | ✓ | | | | | | | |
| agihan.ppuu | | ✓ | | ✓ | | | | |
| agihan.kp | | | | | | ✓ | | |
| khidmat.view | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| khidmat.manage | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| khidmat.proses | ✓ | ✓ | ✓ | | | | | |
| peguam_panel.manage | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| peguam.permohonan.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| peguam.semak | | ✓ | | ✓ | ✓ | | | |
| peguam.sokong | ✓ | | | | | | | |
| peguam.keputusan | | | | | | ✓ | | |
| selenggara.pegawai | ✓ | ✓ | | | | ✓ | | |
| selenggara.poster | ✓ | ✓ | | | | ✓ | | |
| selenggara.ref_kes | ✓ | ✓ | | | | ✓ | | |
| selenggara.mahkamah_ref | ✓ | ✓ | | | | ✓ | | |
| selenggara.cuti | ✓ | ✓ | | | | ✓ | | |
| selenggara.cawangan | ✓ | ✓ | | | | ✓ | | |
| selenggara.kategori_kn | ✓ | ✓ | | | | ✓ | | |
| selenggara.jawatan | ✓ | ✓ | | | | ✓ | | |
| slot.view | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| slot.manage | ✓ | ✓ | | | ✓ | ✓ | | |
| urus.pengguna | ✓ | ✓ | | | | ✓ | | |
| audit.view | ✓ | ✓ | | | | ✓ | | |
| menu.selenggara | ✓ | ✓ | | | | | | |
| cawangan.view-all | | ✓ | | | | ✓ | | |
| urus.peranan | | | | | | | | | (admin only) |
| lawyer.area | | | | | | | ✓ | |
| awam.portal | | | | | | | | ✓ |

**Permission group prefixes** (Akses matrix UI groups by the substring before the first `.`,
`RolePermissionController::edit`): system, kes, pengantaraan, mahkamah, lampiran, cetakan, oyd,
kpi, laporan, statistik, agihan, khidmat, peguam_panel, peguam, selenggara, slot, urus, audit,
menu, cawangan, lawyer, awam.

---

## 3. Enforcement matrix — verified at EVERY layer

Five enforcement mechanisms exist; gating is split inconsistently across them:

1. **Route `permission:` middleware** (primary)
2. **Route `role:` middleware** (agihan/tarik-diri/kemaskini lifecycle actions)
3. **In-controller `->can()` / `abort_unless`** (KeputusanController, PermohonanPeguamController)
4. **Policy** (`KhidmatNasihatPolicy` — awam ownership) + `Gate::authorize` calls
5. **`@can` in Blade** (UI-only menu hiding — NOT a security boundary)
6. **Global Eloquent scope** (`CawanganScope` — branch isolation on `forms` only)

### 3.1 Which seeded permissions are actually enforced where

Cross-checked `grep -oE "permission:..." routes/web.php` against the 40 seeded perms and against
in-controller `->can()` usage. **17 of 40 permissions never appear as route middleware:**

| Permission | Route middleware? | In-controller `can()`? | Policy/Scope? | UI `@can`? | **Real enforcement?** |
|---|:--:|:--:|:--:|:--:|---|
| kes.view | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** Reads gated only by outer `system.view`. |
| kes.create | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| kes.update | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| kes.keputusan | ❌ | ✓ `KeputusanController::gate()` L20-25 | — | ✓ `kes/show.blade.php:166` | YES (controller). |
| pengantaraan.manage | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| mahkamah.manage | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| lampiran.manage | ❌ | ❌ | (ownership only) | ❌ | **NONE — decorative.** (file-ID/case match only.) |
| cetakan.view | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| oyd.manage | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| kpi.view | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| peguam_panel.manage | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| peguam.permohonan.view | ❌ | ❌ | ❌ | ❌ | **NONE — decorative.** |
| peguam.semak | ❌ | ✓ `PermohonanPeguamController:50` | — | — | YES (controller). |
| peguam.sokong | ❌ | ✓ `PermohonanPeguamController:67` | — | — | YES (controller). |
| peguam.keputusan | ❌ | ✓ `PermohonanPeguamController:88` | — | — | YES (controller). |
| menu.selenggara | ❌ | ❌ | ❌ | ✓ `staff.blade.php:124`, `utama.blade.php:6,114` | **UI-ONLY.** |
| cawangan.view-all | ❌ | ✓ `CawanganScope:30`, `KhidmatProsesService:60` | scope | — | YES (scope). |
| system.view | ✓ L123 | — | — | — | YES — but it's the broad gate everything else hides behind. |
| agihan.manage | ✓ L342 | — | — | — | YES. |
| agihan.pengarah | ✓ L351-354 (×3) | — | — | — | YES. |
| agihan.ppuu | ✓ L353 | — | — | — | YES. |
| agihan.kp | ✓ L355 | — | — | — | YES. |
| khidmat.view | ✓ L385 | — | — | ✓ nav | YES. |
| khidmat.manage | ✓ L391 | — | — | — | YES. |
| khidmat.proses | ✓ L426 | — | — | ✓ nav | YES. |
| laporan.view | ✓ L180,443 | — | — | ✓ nav | YES. |
| statistik.view | ✓ L191 | — | — | — | YES. |
| selenggara.* (8) | ✓ each group | — | — | partial nav | YES. |
| slot.view | ✓ L97,417 | — | — | ✓ nav | YES. |
| slot.manage | ✓ L278 | — | — | ✓ nav | YES. |
| urus.pengguna | ✓ L315 | — | — | — | YES (but see §4.1). |
| audit.view | ✓ L337 | — | — | — | YES. |
| urus.peranan | ✓ L325 | — | — | ✓ nav | YES. |
| lawyer.area | ✓ L476 | — | — | — | YES. |
| awam.portal | ✓ L82 | — | policy | — | YES. |

### 3.2 Layer-by-layer findings

**Route middleware (`bootstrap/app.php`, `routes/web.php`):**
- Aliases `role`, `permission`, `role_or_permission` registered (`bootstrap/app.php:18-22`).
- The entire staff area sits inside ONE outer group `['auth','permission:system.view']`
  (`web.php:123`). Inside it, only a subset of sub-groups add a finer `permission:` — the rest
  (Rekod Kes CRUD, pengantaraan, mahkamah, OYD, cetakan, KPI, agihan list/show, tarik-diri
  list/show, kemaskini-bidang list, permohonan-peguam list/show) inherit only `system.view`.
- `role:` is used on 7 lifecycle POST actions only (tarik-diri ppuu/pengarah/kp,
  kemaskini-bidang pengarah/kp, peguam-panel nyahaktif/aktif-semula). All correctly pipe-delimited
  (`|`) — `Batch7RbacMatrixTest::test_no_comma_delimited_spatie_middleware` guards this regression.

**Controller authorization:**
- `KeputusanController` (lulus/tolak/tutupFail) → `abort_unless(can('kes.keputusan'))` ✓.
- `PermohonanPeguamController` (semak/sokong/keputusan) → per-action `can()` ✓.
- `AgihanSpineController::pengarahTerima/...` — POSTs are route-`permission:`-gated; the `stage()`
  method (L157-169) only decides which form renders, not authorization. **`senarai`/`show` (L31,L51)
  have NO permission/role check** beyond `agihan.manage` (so any agihan.manage holder reads any
  branch's queue — read leak).
- `TarikDiriController::senarai/show` and `KemaskiniBidangController::index` — **no per-permission
  guard at all**; visible to every `system.view` holder.

**Policy / Gate:**
- `KhidmatNasihatPolicy` (view/update) → `owns()`: `isAwam() && id_pengguna === user.id`
  (`KhidmatNasihatPolicy:20-23`). Enforced via `Gate::authorize('view'|'update', $khidmat)` in
  `Awam\PermohonanController` (show L107, update L119, download L141, cancel L152, reschedule L165).
  Citizen document **download** is owner-gated (L141) + file scoped to `id_khidmat` (L143). ✓
- Awam wizard requires `session('awam_saringan.lulus')===true` before create
  (`Awam\PermohonanController:77`).

**Model/query scope — `CawanganScope`:**
- Applied to **EXACTLY ONE model: `Form`** (`forms`). No other model carries it.
- Logic (`CawanganScope:19-26`): staff + has `cawangan` + lacks `cawangan.view-all`
  → `WHERE forms.cawangan = user.cawangan`. Memoized per-request.
- **GAP:** `khidmat_nasihat`, `temu_janji`, `slot_temu_janji`, `butiran_oyd`, all `peguam_panel`/
  lawyer-panel tables have **no scope**. KN branch isolation is applied manually inside
  `KhidmatProsesService:60` / `LaporanKnService`. OYD registry (`butiran_oyd`) has **no branch
  isolation at all** — every staff member with `system.view` sees every branch's assisted-persons.

**UI-only hiding (Blade `@can`):**
- The "Panel Peguam" nav block (`staff.blade.php:106-122`) — Permohonan Peguam, **Agihan Kes,
  Beban Tugas Peguam, Permohonan Tarik Diri, Kemaskini Bidang** — has **NO `@can`**; every staff
  role sees and can open these. The action POSTs are role-gated, but reads are not.
- The "Rekod Kes" block (`staff.blade.php:40-69`) — Senarai Kes, Permohonan Baharu, OYD, Fail
  Tutup, Statistik, Statistik SLA/Kesilapan/Pengantaraan, KPI, Laporan — **no `@can`**; gated only
  by `system.view`. The seeded `kes.*`, `oyd.manage`, `kpi.view`, `cetakan.view`, `pengantaraan.manage`,
  `mahkamah.manage` permissions are **decorative**.
- `menu.selenggara` is enforced **only** in Blade (`@can('menu.selenggara')`); the underlying
  selenggara routes are each independently `permission:selenggara.*`-gated, so this one is
  cosmetic-but-backed. The Pengguna/Pegawai/Audit links inside the `menu.selenggara` block,
  however, point to routes gated by their own perms (urus.pengguna, selenggara.pegawai, audit.view),
  EXCEPT there is no `selenggara.*` perm on the Selenggara sub-links that lack a wrapping `@can`
  (ref-kes/mahkamah/cuti) — those rely on the route `permission:` (OK).

---

## 4. Findings — gaps, duplications, excess, conflicts

### 4.1 CRITICAL — privilege escalation: non-admin can mint an `admin`
- Route `permission:urus.pengguna` (`web.php:315`) is held by **pengarah, koordinator,
  ketua_pengarah** (seeder L62) — NOT just admin.
- `UserRequest::authorize()` returns `true` with the **false comment** "route gated to admin role"
  (`UserRequest:13-16`).
- `UserRequest` validates `role` as `Rule::in(array_keys(UserController::ROLES))` and
  `UserController::ROLES` **includes `admin`** (`UserController:116-125`).
- `UserController::store/update` blindly `syncRoles([$data['role']])` (L59,L92).
- **Result:** any pengarah / koordinator / ketua_pengarah can create OR promote a user to `admin`,
  gaining full `Gate::before` super-admin. This is the single highest-severity access-control defect.
- Secondary: no `role↔user_type` consistency rule — a `staff` user can be assigned role `peguam`,
  or a `lawyer` assigned `pengarah`. `user_type` is restricted to staff/lawyer (can't mint awam
  here), which is fine.

### 4.2 CRITICAL — `RolePermissionController` lets admin re-grant `urus.peranan`/`urus.pengguna`
- `RolePermissionController::update` (`:28-39`) `syncPermissions()` on **any** role with **no
  protection of sensitive permissions**. Although the route is admin-only, an admin can grant
  `urus.peranan` or `urus.pengguna` to (e.g.) `pegawai`, turning the matrix into a persistence
  mechanism for escalation, and can strip `urus.peranan` from `admin` (cosmetic only, since
  `Gate::before` still bypasses — a confusing trap). No allowlist/denylist of editable permissions.

### 4.3 HIGH — `awam` role/permission drift (renamable/deletable citizen gate)
- `awam` is seeded by **migration `130002`**, NOT by `RolePermissionSeeder::ROLES`, and is **absent
  from `RoleController::SYSTEM_ROLES`** (`RoleController:16-19` lists only 8, no awam).
- `RoleController::update/destroy` protect only `SYSTEM_ROLES`. So an admin can **rename or delete
  the `awam` role** via `/peranan`, which silently breaks the entire citizen portal
  (`permission:awam.portal` group, `web.php:82`) and the KN policy gate.
- Same for the `awam.portal` permission — not in the seeder's permission list, so a matrix resync
  via the canonical seeder won't recreate it; only re-running migration `130002` would.

### 4.4 HIGH — 11 seeded permissions are decorative (declared, never enforced)
`kes.view`, `kes.create`, `kes.update`, `pengantaraan.manage`, `mahkamah.manage`, `lampiran.manage`,
`cetakan.view`, `oyd.manage`, `kpi.view`, `peguam_panel.manage`, `peguam.permohonan.view` are seeded
and shown in the Akses matrix UI but enforced **nowhere**. An admin editing the matrix would believe
revoking `kes.create` from `pegawai` blocks case creation — it does not (route only checks
`system.view`). **The matrix UI lies about real access.** This is the most dangerous kind of RBAC
gap: false confidence.

### 4.5 HIGH — read-side leaks on lifecycle queues (no branch/role gate)
- `agihan.senarai`/`agihan.maklumat`, `tarikdiri.senarai`/`tarikdiri.maklumat`,
  `kemaskini-bidang.index`, `permohonan-peguam.index`/`show` carry **no `permission:`/`role:`** —
  only outer `system.view`. Every staff role (incl. `pegawai`, `pembantu_tadbir`) reads every
  branch's assignment/withdrawal/specialisation/lawyer-application queues. None of these models is
  `CawanganScope`-covered either, so there is **no branch isolation** on them.
- `butiran_oyd` (assisted-persons PII) — no scope, no per-perm gate beyond `system.view`. Cross-branch
  PII exposure.

### 4.6 MEDIUM — overlapping / redundant permissions
- `peguam_panel.manage` + `peguam.permohonan.view` — both granted to all 6 non-admin staff roles,
  both unenforced. Redundant pair.
- `kes.view`/`kes.create`/`kes.update` + `pengantaraan.manage`/`mahkamah.manage`/`lampiran.manage`/
  `cetakan.view`/`oyd.manage`/`kpi.view` — all 9 granted identically to all 6 staff roles AND all
  unenforced. Effectively one capability ("staff can touch cases") split into 9 dead flags.
- `khidmat.view` vs `khidmat.manage` — identical role set (pembantu_tadbir, pegawai, koordinator,
  pengarah, ketua_pengarah). Two perms, one audience.
- `selenggara.*` (8 perms) — all 8 granted to the identical {pengarah, koordinator, ketua_pengarah}
  set. Could be one `selenggara.manage` unless per-area delegation is genuinely planned.

### 4.7 MEDIUM — conflicting / surprising grants
- `agihan.ppuu` granted to **koordinator** as well as ppuu (seeder L41). Koordinator can pick
  lawyers — intentional cross-branch ops, but worth confirming against legacy (legacy PPUU=peranan 2
  only).
- `khidmat.proses` granted to **pengarah** but **NOT ketua_pengarah** (seeder L70). KP can view/manage
  KN (`khidmat.view`/`manage`) but cannot process appointments — asymmetric vs the rest of the KP's
  top-tier authority. Likely an oversight.
- `agihan.manage` (the read gate for the agihan UI) is granted to ALL staff incl. pembantu_tadbir/
  pegawai, but the **action** perms (agihan.pengarah/ppuu/kp) are narrow. So clerks see the agihan
  workspace fully but can do nothing — UI noise + info leak.

### 4.8 LOW — roles/permissions no longer relevant or thin
- `koordinator` — new role with **no legacy lineage**; its grants nearly duplicate ketua_pengarah +
  ppuu. Confirm it's a real org position, not a convenience bucket.
- `pembantu_tadbir` — holds case + slot + peguam-panel-semak perms but is excluded from KN
  processing and approvals; verify the clerk actually needs `agihan.manage` read access.
- `menu.selenggara` — pure UI flag (pengarah, koordinator only); not a real boundary.

### 4.9 Captcha / auth weaknesses (context, not RBAC core)
- Trivial 2-number-sum captcha (session `captcha_sum`) on all login/register surfaces — legacy
  parity, weak (map 08 §1). Not a role issue but a public-surface auth issue tied to `awam`/`peguam`
  self-registration.

---

## 5. Test coverage of access control (what's actually verified)

| Test | Covers | Gap |
|---|---|---|
| `Batch7RbacMatrixTest` | GET access for **6 routes** (system.utama, kes.index, pegawai.index, pengguna.index, audit.index, peguam.dashboard) across 8 roles; wrong-area redirect; no-comma middleware. | Does NOT test write actions, the agihan/tarik-diri/kemaskini read leaks, OR the §4.1 escalation. |
| `Batch7SeederTest` | seeder idempotency + role/perm counts (inferred). | — |
| `Batch7ScopeTest` | CawanganScope on `forms`. | No test that KN/OYD/temu_janji LACK scope (the gap is untested). |
| `HardeningTest` | ForcePasswordChange + security headers. | — |
| `Awam/*` | awam auth + ownership (download/lifecycle). | — |

**No test asserts that a non-admin cannot create an admin user.** Add one before any cleanup.

---

## 6. Proposed clean, minimal role + permission structure

### 6.1 Roles (keep 9; tighten semantics)
Keep all 9 — they map to real org positions — but:
- **Add `awam` to `RolePermissionSeeder::ROLES` and `RoleController::SYSTEM_ROLES`** (fixes §4.3).
  Move `awam.portal` into the seeder MATRIX; delete migration `130002`'s seeding role (keep only as
  a no-op or fold into seeder).
- **Confirm `koordinator`** is a real position; if it's just "ppuu + view-all", consider folding.

### 6.2 Permissions — collapse 40 → ~24 real capabilities
Drop the decorative split; one permission per *enforced* capability. Proposed set:

| Group | Permission | Granted to | Enforced at |
|---|---|---|---|
| area | `staff.area` (replaces `system.view`) | all 7 staff | outer route group |
| area | `lawyer.area` | peguam | route |
| area | `awam.portal` | awam | route + policy |
| kes | `kes.manage` (folds view/create/update/pengantaraan/mahkamah/lampiran/cetakan/oyd/kpi) | role-scoped (see below) | **route group on /kes, /oyd, /kpi, /cetak** |
| kes | `kes.keputusan` | pengarah, ketua_pengarah | controller `can()` (keep) |
| agihan | `agihan.view` (read queues) | pengarah, koordinator, ppuu, ketua_pengarah | **route on senarai/maklumat** |
| agihan | `agihan.pengarah` / `agihan.ppuu` / `agihan.kp` | as today | route (keep) |
| khidmat | `khidmat.view` / `khidmat.manage` / `khidmat.proses` | as today (add KP to proses) | route (keep) |
| peguam_panel | `peguam_panel.view` (read applications/queues) | pengarah, koordinator, ppuu, pembantu_tadbir, ketua_pengarah | **route on permohonan-peguam, kemaskini-bidang, tarik-diri list** |
| peguam_panel | `peguam.semak` / `peguam.sokong` / `peguam.keputusan` | as today | controller `can()` (keep) |
| selenggara | `selenggara.manage` (folds 8) OR keep per-area if delegation real | pengarah, koordinator, ketua_pengarah | route (keep) |
| slot | `slot.view` / `slot.manage` | as today | route (keep) |
| admin | `urus.pengguna` / `urus.peranan` / `audit.view` | as today | route + **new guards (§6.3)** |
| scope | `cawangan.view-all` | koordinator, ketua_pengarah | scope (keep) |

**Net deletions:** `kes.view/create/update`, `pengantaraan.manage`, `mahkamah.manage`,
`lampiran.manage`, `cetakan.view`, `oyd.manage`, `kpi.view`, `peguam_panel.manage`,
`peguam.permohonan.view`, `menu.selenggara` (→ derive UI from real perms), 7 of 8 `selenggara.*`
(if folding). Replace with the enforced equivalents above.

### 6.3 Hard fixes (do these regardless of the collapse)
1. **§4.1** — In `UserRequest`/`UserController`: forbid assigning `admin` unless the actor
   `hasRole('admin')`; enforce `role↔user_type` consistency; fix the false `authorize()` comment
   (better: `authorize()` should `return $this->user()->can('urus.pengguna')` and an extra
   admin-only check for the admin role).
2. **§4.2** — In `RolePermissionController`: deny editing `urus.peranan`/`urus.pengguna`/`audit.view`
   grants for non-admin roles; never let `admin`'s matrix be emptied.
3. **§4.3** — Protect `awam` role (add to SYSTEM_ROLES + seeder).
4. **§4.4/§4.5** — Add real `permission:` route gates on /kes, /oyd, /kpi, /cetak, agihan list,
   tarik-diri list, kemaskini-bidang, permohonan-peguam (so the matrix stops lying).
5. **Branch isolation** — extend `CawanganScope` (or explicit service filters) to `butiran_oyd`,
   `khidmat_nasihat`, `temu_janji` so reads are branch-scoped consistently, not just `forms`.
6. **Tests** — add a "non-admin cannot mint admin" test + per-area write-action role tests.

---

## 7. File index (read & verified during this audit)

| Concern | File:line |
|---|---|
| RBAC seed (matrix) | `database/seeders/RolePermissionSeeder.php` (ROLES L19, MATRIX L25-71) |
| Awam role seed (drift) | `database/migrations/2026_06_30_130002_seed_awam_role_permission.php` |
| Role constants / homeRoute | `app/Models/User.php:24-87` |
| Super-admin bypass + policy bind | `app/Providers/AppServiceProvider.php:29,31` |
| Middleware aliases + unauthorized handler | `bootstrap/app.php:18-22,35-52` |
| Route gating (all `permission:`/`role:`) | `routes/web.php` (123, 315, 325, 342, 351-355, 360-373, 385-435, 476) |
| Branch scope | `app/Models/Scopes/CawanganScope.php:19-31` |
| KN ownership policy | `app/Policies/KhidmatNasihatPolicy.php:10-23` |
| Citizen controller (Gate::authorize) | `app/Http/Controllers/Awam/PermohonanController.php:77,107,119,141,152,165` |
| Case-decision controller gate | `app/Http/Controllers/KeputusanController.php:18-25` |
| Lawyer-application gates | `app/Http/Controllers/PermohonanPeguamController.php:50,67,88` |
| Agihan spine (stage vs gate) | `app/Http/Controllers/AgihanSpineController.php:31,51,157-169` |
| Tarik-diri / kemaskini gates | `app/Http/Controllers/TarikDiriController.php:85-95`, `KemaskiniBidangController.php:20-54` |
| **User CRUD (escalation surface)** | `app/Http/Controllers/UserController.php:42-125`, `app/Http/Requests/UserRequest.php:13-35` |
| Role CRUD (system-role protection) | `app/Http/Controllers/RoleController.php:16-75` |
| Permission matrix CRUD | `app/Http/Controllers/RolePermissionController.php:16-39` |
| UI hiding | `resources/views/layouts/staff.blade.php:40-169`, `system/utama.blade.php:6,114`, `kes/show.blade.php:166` |
| RBAC tests | `tests/Feature/Batch7RbacMatrixTest.php`, `Batch7SeederTest.php`, `Batch7ScopeTest.php`, `HardeningTest.php` |
| Legacy role lineage | maps `01-legacy-peguam-panel.md §1,3`, `02-legacy-rekod-kes.md §2`, `03-legacy-iguaman-advisory.md §2` |
</content>
</invoke>
