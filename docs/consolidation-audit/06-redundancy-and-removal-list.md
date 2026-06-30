# 06 — Redundancy & Removal List (Phase 4 / Deliverable 6)

> **Audit posture:** READ-ONLY. Only this file was written. Every code/table/route proposed for removal
> was Grep/Glob-verified against the live 2in1 codebase at
> `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/2in1` (branch `main`, commit `735dd4f`).
> **No production data is proposed for deletion without an archive step.**
>
> Scope: redundancy arising because the four origin systems were originally separate —
> legacy `sistem-peguam-panel` (raw PHP), legacy `sistem-rekod-kes` (raw PHP),
> legacy iGuaman advisory (`be_iguaman-master` .NET / `fe-iguaman-master` Nuxt),
> chatbot `cbjbg` (Python) — now fused into the consolidated 2in1 Laravel app.
>
> Inputs: maps `01`–`09` under `docs/consolidation-audit/maps/`, planning docs under `context/`,
> and direct grep/read of 2in1 source.

---

## 0. Map-vs-code corrections found during verification (read these first)

The maps are mostly accurate but contain **two stale claims** that materially change the removal list.
Verified against actual code — **do not act on the stale map text**:

| Map claim | Reality (verified) | Evidence |
|---|---|---|
| Map 05 §5: "Status `7` (LEBIH_MASA) defined but **never produced**; no scheduled job; auto-reassignment NOT implemented." | **FALSE — it is fully implemented and live.** `app/Console/Commands/AgihanLebihMasa.php` (`agihan:lebih-masa`) + `routes/console.php:12` `Schedule::command('agihan:lebih-masa')->dailyAt('07:00')` + `app/Support/LebihMasaService.php` + `tests/Feature/LebihMasaTest.php` + `app/Mail/KesLebihMasaMail.php`. | `routes/console.php`, `app/Console/Commands/AgihanLebihMasa.php` |
| Map 05 §6 C2: "Spine→lawyer offer hand-off broken" implies the string/numeric path is a pure bug. | Real, but `StatusAgihan::LEGACY_STRING_MAP` + `bucketValues()` exist precisely to reconcile reads. The fix is to **normalise on write + pick one assignment front-end** (see R-WF-01), not to delete either path blindly. | `app/Support/StatusAgihan.php:56,99` |

**`welcome.blade.php` is NOT removable** — recent commit `0699861` replaced the default Laravel welcome
with a real Khidmat Nasihat landing page (`resources/views/welcome.blade.php` is the live public portal +
chatbot widget host). It is **not** the dead Laravel scaffold the term "welcome" might suggest.

---

## 1. Redundancy inventory (duplication → single source of truth)

Each row: the redundancy, **which version wins (source of truth)**, what to consolidate. Removal actions are
tabulated in §2.

### 1.1 Workflows / modules

| # | Redundancy (because systems were separate) | Source of truth (winner) | Consolidate |
|---|---|---|---|
| WF-1 | **Two parallel case-assignment front-ends.** `AgihanController` single-step (`/kes/{kes}/agih`, writes STRING `status_agihan='Ditawarkan'`) vs `AgihanSpineController` 3-tier PPUU→Pengarah→KP (`/agihan/*`, writes NUMERIC `0/8/10/13/1/2…` via `AgihanService`). Both mutate the same `forms.status_agihan` with no guard. | **`AgihanSpineController` + `AgihanService` + `StatusAgihan`** (the canonical legacy `pp-agihan` machine; richer, guarded by `ensureStatus()`, audited, tested). | Deprecate single-step assign; keep `@beban` workload only. Add a one-time data migration normalising legacy string codes → numeric via `StatusAgihan::LEGACY_STRING_MAP`. Make lawyer `tawaran/dashboard/terima` query via `StatusAgihan::bucketValues()` not literal `'Ditawarkan'`. |
| WF-2 | **Lawyer accept/reject writes STRING statuses** (`PeguamController` → `'Diterima'`/`'Ditolak'`) while spine writes numeric — same dual-encoding column. | **Numeric `StatusAgihan` codes** (`2`/`4`). | Convert lawyer-side writes to numeric; rely on `StatusAgihan::label()` for display. |
| WF-3 | **Name-based case↔lawyer linkage** repeated in 4 places (`authorizeCase`, `@beban` workload, `redistributeActiveCases`, `hasActiveCaseInCategory`) — each re-matches `forms.nama_pegawai_yang_dapat_kes` against `peguam_panel.nama_peguam` string. | **`peguam_panel.kp_peguam` / `users.id_peguam_panel`** (stable IC key). | Add `forms.kp_peguam_dapat_kes` (or FK) and switch the 4 matchers to it. Redundant string-matching logic collapses to one helper. |
| WF-4 | **Branch isolation re-implemented 3× for KN** (`KhidmatProsesService::branchFilter`, `LaporanKnService::resolveBranchFilter`, report queries) because KN has no `CawanganScope` (only `forms` is auto-scoped). | **`CawanganScope`** pattern (already global on `Form`). | Add a `CawanganScope`-equivalent (keyed on `cawangan_id`) to `KhidmatNasihat`; delete the 3 hand-rolled branch filters. |

### 1.2 Tables / schema

| # | Redundancy | Source of truth (winner) | Consolidate |
|---|---|---|---|
| TB-1 | **`butiran_peguam_panel` (v1) vs `butiran_peguam_panel_2`** — two lawyer-application tables imported from one legacy DB. v1 is superseded by `_2..._6`. | **`butiran_peguam_panel_2` (+`_3.._6`)** — the active approval/profile record. | v1 is still *read* via `PeguamPanel::butiran()` (`PeguamController:210`, `PeguamPanelController:28`) for legacy display — **do NOT drop yet.** Migrate those 2 read sites to `_2..._6`, then archive+drop v1. |
| TB-2 | **`mahkamah_sivil` vs `mahkamah_syariah`** — identical column shape, two tables (civil vs syariah court registry). | Single `mahkamah` + `jenis` discriminator (mirrors the `cawangan.jenis` pattern). | **MEDIUM refactor, not a safe deletion** — both are live in `MahkamahRefController`, `KhidmatNasihatController:243-244`, `KhidmatNasihat::mahkamah()`. Merge only with a migration + controller rewrite. Leave as-is unless schema cleanup is in scope. |
| TB-3 | **`ref_kes` (litigation taxonomy) vs `ref_kategori_kn` tree (advisory)** — two case-type taxonomies because rekod-kes and the KN advisory came from different systems. | **Both kept, intentionally separate** (LOCKED decision D3 / memory `ref-kes-not-kn-tree`). | NOT redundant — do not merge. Documented here only to pre-empt a wrong "dedupe" instinct. |
| TB-4 | **Lawyer profile fragmented across `_2/_3/_4/_5/_6`** (5 tables, one logical entity, joined by `kpBaru` string, no FKs). | A normalised `lawyer_profile` + child tables (per map 01 §10 rewrite note). | Schema-normalisation epic, out of removal scope; flagged as the structural redundancy to address later. |
| TB-5 | **`items`** — orphan demo/stub table (`name`,`description`), no controller, no relations, no routes; only the `CREATE TABLE` + the ETL `verbatim` list reference it. | — (no winner; it carries no domain meaning). | Drop after archiving any rows. See R-TB-01. |

### 1.3 Auth / users / identity

| # | Redundancy | Source of truth (winner) | Consolidate |
|---|---|---|---|
| AU-1 | **3 legacy user tables** (`users` staff, `users_peguam_panel_2`, `users_peguam_panel_3`) — separate because peguam-panel and rekod-kes were separate apps. | **Unified `users` table** (already built; `user_type` staff/lawyer/awam + `role`). | Already consolidated at ETL (`ImportLegacyData::importUsers`). The legacy 3 tables are **only in the source `sistemspk` DB**, never created in 2in1 — no removal needed in 2in1 (confirmed: no `CREATE TABLE users_peguam_panel*` in `legacy-domain.sql`). |
| AU-2 | **Plaintext-password auth** duplicated in both legacy PHP systems (`$password === $kata_laluan`). | **bcrypt `Auth::attempt`** in `SystemAuthController`/`PublicAuthController`. | Already replaced. Legacy backdoor (`log_masuk_backdoor.php`), URL-password-reset, `phpinfo.php`/`test-emel.php` live **only in the legacy source repos**, not in 2in1 — nothing to remove from 2in1. |
| AU-3 | **Captcha implemented twice** (legacy 4/6-digit per system) → 2in1 uses one trivial 2-number-sum captcha across all public forms. | 2in1 session-sum captcha (weak but single). | Already single. Flag for hardening, not removal. |

### 1.4 Statuses / terminology / permissions

| # | Redundancy | Source of truth (winner) | Consolidate |
|---|---|---|---|
| ST-1 | **`status_agihan` dual encoding** (string labels vs numeric codes) — see WF-1/WF-2. | Numeric `StatusAgihan`. | Normalise on write. |
| ST-2 | **Status `14` (`TOLAK_KE_CAWANGAN`)** — defined + labelled in `StatusAgihan` but **never written** by any transition (KP reject goes to `15`). Verified: only non-doc hit is the `const` declaration. | — (dead constant; KP reject canonical target is `15`). | Keep for read-compat OR delete with the verification in R-CODE-02. |
| ST-3 | **`checkbox_value_status=0`** — unnamed "selected at daftar" state on `butiran_peguam_panel_6`, not in any named const, never surfaced in the kemaskini queue. | Named consts `1/2/3/4/7/9`. | Either name it (`SELECTED_AT_DAFTAR`) or migrate `0`→`2` at registration. Mild dead-state, low priority. |
| ST-4 | **Declared-but-unenforced permissions** — seeded in `RolePermissionSeeder::MATRIX` but never used as `permission:` route middleware: `peguam_panel.manage`, `peguam.permohonan.view`, `menu.selenggara`, and the `kes.view/create/update` family (gating is via the outer `permission:system.view` group + in-controller `->can('kes.keputusan')`). | The route-level + in-controller gates actually used. | Audit each; drop the truly-unreferenced permission names (see R-PERM-01) — but **only after confirming no in-controller `->can()` / Blade `@can` use** (some, e.g. `kes.keputusan`, ARE used in-controller and must stay). |
| ST-5 | **Free-text `forms.status`** strings (`Diterima`/`Ditolak`/`Fail Tutup` + legacy `'1'`/`'TOLAK'`/`'TARIK DIRI'`/`'LEBIH MASA'`) — no enum, historical drift from the merged legacy data. | A PHP enum / `StatusKes` const set. | Normalisation epic (out of removal scope); flagged. |

### 1.5 Reports / notifications / services

| # | Redundancy | Source of truth (winner) | Consolidate |
|---|---|---|---|
| RP-1 | **Two report layers over `forms`**: `LaporanController` (6 narrow reports) + `LaporanPenuhController` (5 wide-CSV) + `StatistikController` — overlapping permohonan/pendaftaran/status-fail/pengantaraan subjects, inherited from rekod-kes's ~13 `export_*.php`. | Keep both tiers (narrow = on-screen, wide = legacy-parity CSV) — they serve different output needs. | Not redundant enough to remove; consolidate column/formatter logic into `WideExport` (already partly done). No deletion. |
| RP-2 | **Notifications**: legacy peguam-panel = PHPMailer Gmail; rekod-kes = PHPMailer; KN = none; cbjbg = none. 2in1 = Laravel Mail (`AgihanTransisiMail`, `KesDitawarkanMail`, `KesLebihMasaMail`). | Laravel Mail. | Already single. No raw PHPMailer in 2in1 to remove. |
| RP-3 | **`jobs`/`job_batches`/`failed_jobs` tables** present (DB queue driver) but **zero queue usage** in `app/` (no `dispatch`/`ShouldQueue`/`Queue::`). | — (infra only). | Stock Laravel; low-value to drop and re-add. Leave unless trimming the schema. See R-TB-02 (low confidence). |

---

## 2. REMOVAL LIST

Legend — **Confidence**: how sure removal is safe. **Risk**: blast radius if wrong.
All "Table"/"Column" removals assume an **archive step first** (dump to `*_archive` table or SQL file) — never a bare `DROP` on data.

| Type | Item | Why unnecessary | What replaces it | Risk | How removal will be tested | CONFIDENCE |
|---|---|---|---|---|---|---|
| **Table** (R-TB-01) | `items` table + `app/Models/Item.php` | Orphan demo stub (`name`,`description`). Grep: zero controllers, zero routes, zero relations reference `Item`; only the `CREATE TABLE` in `legacy-domain.sql:263`, the model file, and the ETL `verbatim` array (`ImportLegacyData.php:29`) mention it. Carries no domain data. | Nothing. | LOW — but it is in the ETL copy list, so removal must also drop `'items'` from `ImportLegacyData::$verbatim`. | Archive any rows → drop table + model + ETL entry → run `php artisan migrate:fresh`, `php artisan test`, and `php artisan legacy:import` against a sandbox `sistemspk` (must not error on the missing table). | **high** |
| **Code** (R-CODE-01) | Dead-import / legacy debug files **in the source repos only** (`sistem-peguam-panel/phpinfo.php`, `test-emel.php`, `log_masuk_backdoor.php`, hardcoded-secret `config.php`, `cbjbg/main-commented-hero-serpapi-jwt.py`) | Security liabilities (plaintext secrets, backdoor) that were NOT carried into 2in1. | The hardened 2in1 equivalents already exist. | NONE for 2in1 (these are not in the 2in1 tree). | Confirm via grep that none exist under the 2in1 root (already confirmed). Action item is "do not port", plus rotate the 5 cbjbg secrets + JBG Gmail app password before prod. | **high** (advisory) |
| **Status** (R-CODE-02) | `StatusAgihan::TOLAK_KE_CAWANGAN = '14'` constant | Defined + labelled but **never written** — every KP-reject transition targets `15` (`kpTolak`). Grep confirms the only non-doc occurrence is the `const` line; `bucket, label, transitions` never set `'14'`. | `15` (`KELULUSAN_KP_SEMULA`) is the real KP-reject path. | LOW — `label()`/`LABELS` may still map `14` for display of any pre-existing `14` rows from legacy data. | Grep legacy `sistemspk` dump for `status_agihan='14'`; if present, keep the label mapping for read-only display and only remove the *write-path* expectation. If absent, delete the const + its `LABELS` entry and run `php artisan test` (agihan suite). | **medium** (keep label if legacy `14` rows exist) |
| **Permission** (R-PERM-01) | Seeded permissions never used as `permission:` route middleware AND never used in-controller: candidates `menu.selenggara`, `peguam_panel.manage`, `peguam.permohonan.view` | Dead grant rows — they neither gate a route nor a controller action. (`kes.view/create/update` look unused at route level but the **outer `system.view` group + `kes.keputusan` in-controller** cover the real gating, so leave those.) | Existing `permission:system.view` group gate + in-controller `->can()` checks. | MEDIUM — a permission may be referenced in a Blade `@can()` or a future-planned screen; spatie also caches permission lists. | For each candidate, grep the whole tree (`*.php` + `*.blade.php`) for the literal name in `can(`, `@can`, `permission:`, `hasPermissionTo`. Only drop names with zero hits outside the seeder. Re-seed (`RolePermissionSeeder`), `php artisan permission:cache-reset`, run auth/permission feature tests, manually smoke each role's menu. | **medium** |
| **Workflow/Route** (R-WF-01) | Single-step assign path `AgihanController@form/@store` + routes `agihan.form`/`agihan.store` (`/kes/{kes}/agih`) — **the assignment writes, NOT the `@beban` workload** | Duplicate of the 3-tier spine; writes legacy STRING `status_agihan` that desyncs the lawyer Tawaran list (only string-offered cases surface to lawyers). Picking one assignment front-end is required by the consolidation (map 05 C5, map 06 §5.1). | `AgihanSpineController` 3-tier spine (canonical, guarded, audited, tested). Keep `AgihanController@beban` (workload report — `agihan.beban` route + `/peguam-panel/beban`) as it has no spine equivalent. | **HIGH** — this is a behavioural change, not dead code. The single-step path is live + routed + has a Blade view (`kes/agihan.blade.php`) + emails (`KesDitawarkanMail`). Removing it changes how staff assign cases. | Do NOT remove unilaterally — this is a product decision. Plan: (1) data-migrate string→numeric (`StatusAgihan::LEGACY_STRING_MAP`); (2) fix `PeguamController::tawaran/dashboard/terima` to use `bucketValues()`; (3) route single-step UI to the spine; (4) feature-test that a spine-offered case appears in the lawyer's Tawaran and accept/reject flows numeric; (5) only then retire `@form/@store` + its view + route. Regression-test full agihan + lawyer suites. | **low** (correct target, but high-risk live path — gate behind the WF-1 consolidation decision, do not delete now) |
| **Code/Dead-state** (R-CODE-03) | Spine status `9` (`DITOLAK_PENGARAH`) dead-end handling | After `pengarahTolakBaru` a case sits at `9`, which is in no list bucket and `stage()` returns null — the case vanishes from queues with no recovery screen. This is a **missing-feature gap, not a removable item**. | A re-route/re-open screen for `9` (build, don't delete). | n/a (nothing to remove). | Listed here so it is not mistaken for safe-to-ignore dead code — it is a **gap to fill**, tracked in the gap-analysis deliverable, not removed. | n/a (gap, not removal) |
| **Table/Infra** (R-TB-02) | `jobs`, `job_batches`, `failed_jobs` tables | No queue usage anywhere in `app/` (grep: no `dispatch`/`ShouldQueue`/`Queue::`/`Bus::`). `QUEUE_CONNECTION=database` is set but unused (all mail is best-effort sync). | Nothing today; Laravel re-creates them via `queue:table` if a queue is later added. | MEDIUM — trivial to need again; removing stock infra saves almost nothing and risks a future `dispatch` failing. | **Recommend KEEP.** If trimmed: switch `QUEUE_CONNECTION=sync`, drop the 3 tables via a reversible migration, run full test suite + verify no mail/notification path calls `->queue()`. | **low** (recommend keep) |
| **Table/Pivot** (R-TB-03) | `model_has_permissions` pivot (direct per-user permission grants) | Grep: no `givePermissionTo`/direct-grant call anywhere; all grants flow through roles (`role_has_permissions`). The pivot is empty in seed. | Role-based grants only. | **HIGH** — spatie/laravel-permission **requires** this table to exist; dropping it breaks the package even if empty. | **Do NOT remove.** Documented as "empty by design". No test needed — keep. | **low** (must keep — package-required) |
| **Column** (R-COL-01) | `checkbox_value_status = 0` unnamed state on `butiran_peguam_panel_6` | Not in any named const; never surfaced in the kemaskini queue; effectively "selected at daftar" with no handler. Mild dead-state. | Name it `SELECTED_AT_DAFTAR` OR migrate `0`→`2` (AKTIF) at registration so registration-selected areas are immediately active. | LOW — registration writes `0` today; a blind delete/remap could flip practice-area visibility in assignment matching. | Pick rename vs remap; if remap, data-migrate existing `0` rows, then verify lawyer assignment matching (`hasActiveCaseInCategory`, `PengkhususanService`) still resolves the same set. Run pengkhususan feature tests. | **medium** |

---

## 3. Out-of-scope leftover directories (intermediate artifacts — NOT 2in1, NOT a 4th source)

These are **earlier partial Laravel rewrites** sitting beside the real source systems under
`c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/`. They were superseded by the
consolidated `2in1` build and should be treated as **reference-only leftovers to archive/exclude**, never
imported, deployed, or mistaken for an origin system.

| Dir | What it is | Why leftover | Disposition |
|---|---|---|---|
| `sistem-rekod-kes-laravel/` | 2026-06-29 partial Laravel 11/12 rewrite of the **case-records side only**. Hardened `AuthController`, full 29-table migration set, but **`KeputusanController`/`PeringkatController` are 25/23-line stubs** — the 5-stage decision engine, no_fail gen, 30-day rule, batal/jana were never implemented; KPI/statistik/exports/e-poster/cuti stubbed. | Superseded by 2in1, which re-implemented the decision engine from the raw PHP. Its **migration set is still the best schema reference** for the 98-col `forms` (use it to reconcile drift), but the app code is dead. | **Keep read-only as a schema reference; exclude from any build/deploy. Do not delete the migrations** (they resolve the `forms` 78→98 drift better than the stale `.sql` dump). Archive the rest. CONFIDENCE: **high** it is out-of-scope. |
| `spk-laravel/` | More ambitious **both-systems merge** attempt. Has a real `DocumentController` (general doc mgmt beyond legacy poster-only) + `PermohonanReview`, models for `SejarahPpuu`/`ButiranPeguamPanel2-6`/`AuditTrail`/`ButiranOyd`. **No migrations populated**; README is Laravel default. | Superseded by 2in1. Its `DocumentController` design is worth cross-checking against 2in1's citizen/case upload (`LampiranController` + `uploaded_files`) before discarding, in case a feature was dropped. | **Keep read-only until DocumentController parity with 2in1 `LampiranController` is confirmed, then archive.** Exclude from build/deploy. CONFIDENCE: **high** out-of-scope. |

> Neither dir is referenced by 2in1's `composer.json`, autoload, or routes (they are sibling folders, not
> dependencies). Removing them from the *workspace* has zero impact on 2in1 — but **archive, don't hard-delete**,
> until the two cross-check items above (schema reference, DocumentController parity) are closed.

---

## 4. Summary — safe vs risky

**Safe, high-confidence removals (after archive):**
- `items` table + `Item` model + its ETL entry (R-TB-01).
- Confirm legacy debug/secret/backdoor files are absent from 2in1 (R-CODE-01) — advisory; rotate secrets.
- Treat `sistem-rekod-kes-laravel/` and `spk-laravel/` as out-of-scope leftovers (archive, keep the former's migrations as a schema reference).

**Medium-confidence (verify each literal before dropping):**
- `StatusAgihan::TOLAK_KE_CAWANGAN ('14')` dead constant — keep its display label if legacy `14` rows exist (R-CODE-02).
- Unused seeded permissions `menu.selenggara`, `peguam_panel.manage`, `peguam.permohonan.view` (R-PERM-01) — grep `@can`/`->can`/`permission:` first.
- `checkbox_value_status=0` unnamed state (R-COL-01).

**Do NOT remove (risky / required / live):**
- `model_has_permissions` (spatie-required), `jobs`/`job_batches`/`failed_jobs` (recommend keep), `welcome.blade.php` (live landing), `butiran_peguam_panel` v1 (still read by 2 controllers — migrate reads first), `mahkamah_sivil`/`mahkamah_syariah` (both live — merge is a refactor, not a deletion).
- **`AgihanController@form/@store` single-step assign (R-WF-01)** — it is the *correct* consolidation target but a **live, routed, view-backed, email-sending** path; gate its retirement behind the WF-1 write-normalisation decision + lawyer-Tawaran fix, with full regression tests. Do not delete now.

**Gaps mistaken for dead code (fill, don't remove):** spine status `9` dead-end (R-CODE-03).

**Never:** drop any populated legacy table (`forms`, `butiran_peguam_panel*`, `sejarah_*`, `audit_trail`, `peguam_panel`, `ref_*`) — all needed for historical data + active workflows; any schema change goes through a reversible migration + archive.
