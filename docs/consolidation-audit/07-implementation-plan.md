# Deliverable 7 — Implementation Plan (Consolidation Remediation)

> **Purpose.** Turn the gap analysis (`03-gap-analysis.md`), removal list (`06-redundancy-and-removal-list.md`),
> status/workflow governance (`status-and-workflow-governance.md`), RBAC review (`roles-and-access-control.md`),
> and data/DB review (`data-and-database-review.md`) into **controlled, ordered, incremental phases**.
>
> **Audit posture (carried from prior deliverables).** This document is the only artefact written; **no source
> code was modified** to produce it. Every anchor below was re-verified against live 2in1 code at branch `main`,
> commit `735dd4f`.
>
> **Ordering principle.** Risk + dependency first:
> 1. **Security must-fixes** (access-control escalation, citizen-gate fragility) — highest blast radius, no schema risk.
> 2. **Data-integrity / ETL blockers** that make a real migration *fatal* (must precede any production data load).
> 3. **Functional CRITICAL stuck-records** (spine→lawyer break, KN no-show/reject dead-ends) — broken core workflows.
> 4. **HIGH coverage gaps** (notifications, prints, public lookups).
> 5. **Structural hardening** (status enums, FKs, indexes, branch scope, charset).
> 6. **NON-destructive cleanups**, then — gated last — **destructive removals** behind the §11 removal-procedure + archive.
>
> **Non-destructive vs destructive separation.** Phases 0–9 add/repair only (no `DROP`, no data deletion).
> **Phase 10 (destructive removals)** runs ONLY after each item passes the `data-and-database-review.md §11`
> seven-step gate + archive. Nothing is dropped on the strength of this audit alone.
>
> **Commit discipline.** Each phase = a small set of **atomic commits** (one logical change each), conventional-commit
> typed (`fix:`/`feat:`/`refactor:`/`chore:`), each green on `php artisan test` before the next. No phase mixes a
> security fix with a schema change in one commit.

---

## 0. Pre-flight verification (done — security baseline confirmed)

Before sequencing, the brief's three security checkpoints were verified directly against code:

| Checkpoint | Result | Evidence |
|---|---|---|
| **Plaintext passwords already bcrypt?** | **YES — already fixed.** No plaintext compare survives in 2in1. All writes use `Hash::make`. | `SystemAuthController.php:74`, `PublicAuthController.php:35`, `PasswordResetController.php:50`; legacy `$password===$kata_laluan` exists only in the source repos (doc 03 §5, doc 06 AU-2). **No action needed.** |
| **Hardcoded secrets in app?** | **CLEAN in 2in1.** No Gmail app-pw / AWS / API keys in `app/` or `config/`. | grep of `app/`+`config/` for `smtp.gmail`/`AKIA`/`sk-…`/inline passwords → 0 hits. Secrets live in `.env`. The **cbjbg microservice** secrets + the legacy JBG Gmail app-password still need **rotation before prod** (doc 06 R-CODE-01) — operational, tracked in Phase 9. |
| **Missing FKs?** | **CONFIRMED present** (legacy ships 1 FK; most logical links are FK-less). | doc 08 §3. Addressed structurally in Phase 8 (non-destructive, behind the widen-PK procedure). |

**Net:** the two "classic" security must-fixes (plaintext, app-secrets) are already satisfied. The **real**
security must-fix is the **RBAC privilege-escalation** surface (doc 07-roles §4.1/§4.2/§4.3) — that becomes Phase 1.

One **environment precondition** gates several later phases: `MAIL_MAILER=log` today (`.env.example`). Phases 4
(notifications) and the credential-delivery half of Phase 3 **assume a working `MAIL_*`**. If production mail is
deferred, those phases ship their **printed-letter / admin-set-password fallbacks** instead (called out inline).

---

## Phase map (at a glance)

| Phase | Theme | Class | Gates / depends on | Addresses |
|---|---|---|---|---|
| **0** | Pre-flight + safety rails (branch, archive tooling, CI green) | non-destructive | — | enables all |
| **1** | RBAC security must-fixes | non-destructive | 0 | roles §4.1, §4.2, §4.3; gap G-H5 |
| **2** | ETL / migration blockers (make `legacy:import` runnable) | non-destructive (schema add) | 0 | data F1/F2/F3, C1/C2/C3; gap G-H6 |
| **3** | CRITICAL functional fixes — spine→lawyer + status normalisation + KN dead-ends + credential delivery | non-destructive | 1, 2 | G-C1, G-C2, G-C3(partial), B-1, B-2, G-1, G-2; STUCK-1..4,6 |
| **4** | Notification coverage restore | non-destructive | 3, mail env | G-H2, G-C2, G-C3 |
| **5** | Public self-service (semak status, citizen reset) | non-destructive | 1 | G-H3, G-M5 |
| **6** | Print/letter artefacts | non-destructive | — | G-H4, G-C3 |
| **7** | Status governance hardening (enums, guards, constraints) | non-destructive | 3 | A-1, B-3/4/5, D-1, X-2, X-4, H-1 |
| **8** | DB integrity (FKs, indexes, charset, type fixes, branch scope) | non-destructive | 2 | data §3/§4/§9, G-M8/M10, roles §4.5 |
| **9** | Reporting reconciliation + operational hardening | non-destructive | 8 | G-M6, G-M7, secret rotation |
| **10** | **Destructive removals (gated)** | **destructive** | **0–9 + §11 gate per item** | doc 06 R-TB-01, R-CODE-02, R-PERM-01, R-WF-01, R-COL-01 |

---

## PHASE 0 — Pre-flight & safety rails

**Objective.** Establish the working branch, archive tooling, and a green baseline so every later phase is
reversible and test-gated. No behavioural change.

**Files/modules affected.**
- New branch off `main` (e.g. `consolidation/remediation`).
- `composer.json` / CI config (confirm `php artisan test` runs clean at `735dd4f`).
- A scratch `database/archive/` convention + a documented `CREATE TABLE <t>_archive_YYYYMMDD AS SELECT …` recipe
  (from doc 08 §11) — **no archive run yet**, just the procedure committed as `docs`.

**Database impact.** None.
**Migration requirement.** None.
**Testing requirement.** `php artisan test` green on the untouched tree (establishes the regression baseline).
Record the current pass count as the floor every later phase must not drop below.
**Completion criteria.** Branch created; baseline suite green; §11 archive recipe committed under `docs/`.

---

## PHASE 1 — RBAC security must-fixes (highest priority, zero schema risk)

> The genuine "security first" work. These are app-layer authorization fixes — no migrations, fully testable,
> small blast radius per fix. Order *within* the phase: escalation → matrix-tamper → citizen-gate.

### 1.1 Block non-admin from minting/promoting an `admin` (roles §4.1 — CRITICAL)
**Objective.** Stop pengarah/koordinator/ketua_pengarah (who hold `urus.pengguna`) from creating or editing a user
into role `admin` and inheriting the `Gate::before` super-admin bypass.
**Files.**
- `app/Http/Requests/UserRequest.php` — replace `authorize(): return true;` (the false "route gated to admin role"
  comment, verified `:13-16`) with a real check: `return $this->user()?->can('urus.pengguna') ?? false;` **plus** an
  admin-only guard — if the submitted `role === User::ROLE_ADMIN`, require `$this->user()->hasRole('admin')`.
- `app/Http/Controllers/UserController.php` — defence in depth at `store`/`update` (`:59,:92` `syncRoles`): refuse
  `admin` assignment unless actor `hasRole('admin')`; add a `role ↔ user_type` consistency rule
  (staff-roles only for `TYPE_STAFF`, `peguam` only for `TYPE_LAWYER`).
**Database impact.** None. **Migration.** None.
**Testing.** New `Feature` test: a `pengarah` POST/PUT to `pengguna.store`/`update` with `role=admin` → 403/redirect-with-error;
an `admin` doing the same → 200. (roles §5 explicitly notes "No test asserts a non-admin cannot mint an admin — add one.")
**Completion.** Non-admin cannot create or promote to `admin`; role/user_type mismatch rejected; existing
`Batch7RbacMatrixTest` still green.

### 1.2 Protect sensitive permissions in the matrix editor (roles §4.2 — CRITICAL)
**Objective.** Prevent the Akses matrix from being used as an escalation persistence mechanism.
**Files.** `app/Http/Controllers/RolePermissionController.php` (`update` `:28-39`, blind `syncPermissions`):
introduce a `PROTECTED_PERMISSIONS` allow/deny list (`urus.peranan`, `urus.pengguna`, `audit.view`) that cannot be
granted to non-admin roles, and never let `admin`'s own grants be emptied.
**Database impact.** None (operates on existing `role_has_permissions`). **Migration.** None.
**Testing.** Feature test: granting `urus.peranan` to `pegawai` via the matrix → rejected; stripping all perms
from `admin` → rejected/no-op (Gate::before still bypasses, but the trap is closed).
**Completion.** Sensitive permissions ungrantable to non-admin via the UI.

### 1.3 Make the `awam` role/permission tamper-proof (roles §4.3, gap G-H5, data F6 — HIGH)
**Objective.** Stop an admin from renaming/deleting the `awam` role and silently breaking the citizen portal gate
(`permission:awam.portal`, `web.php:82`).
**Files.**
- `app/Http/Controllers/RoleController.php` — add `awam` to `SYSTEM_ROLES` (verified `:16` currently lists 8, no awam;
  protection guards at `:51,:63`).
- `database/seeders/RolePermissionSeeder.php` — add `awam` to `ROLES` and `awam.portal` to the `MATRIX` so a canonical
  re-seed recreates them (today only migration `2026_06_30_130002` seeds them — invisible to the seeder).
- Keep migration `130002` as a now-idempotent no-op (don't delete it; it already ran in environments).
**Database impact.** Re-seed only (no schema change). **Migration.** None (seeder change + idempotent re-run).
**Testing.** Feature test: `RoleController::update/destroy` on `awam` → blocked; fresh `db:seed` recreates `awam` +
`awam.portal`; citizen portal route still gated and reachable for an `awam` user.
**Completion.** `awam` survives a matrix re-seed and cannot be renamed/deleted; citizen gate intact.

### 1.4 (Carry-forward note, not built here) decorative-permission + read-leak gates
The 11 decorative permissions (roles §4.4) and the lifecycle-queue read-leaks (roles §4.5) are **real** but are a
**permission-model refactor**, sequenced in **Phase 7** (status/governance) + **Phase 8** (branch scope) to avoid
mixing a broad RBAC reshape into the urgent escalation fix. Flagged here so they are not forgotten.

**Phase 1 completion criteria (all):** the two CRITICAL escalation paths closed, citizen gate protected, new
auth tests added, full suite green. **No migration ran.**

---

## PHASE 2 — ETL / migration blockers (make a real `legacy:import` survivable)

> These must land **before any production data migration is attempted** — today `legacy:import` would fatal.
> Non-destructive: they add columns / repoint ETL sources / reconcile schema; they delete nothing.

### 2.1 Fix the fatal ETL source (data F2/C2 — HIGH)
**Objective.** `ImportLegacyData::importUsers()` selects from `users_peguam_panel_3`, which has **0 rows in the
51.7 MB `sistemspk.sql` dump** (it does not exist) → `legacy:import` fatals on a real run.
**Files.** `app/Console/Commands/ImportLegacyData.php` (`:151` loop over `['users_peguam_panel_2','users_peguam_panel_3']`):
either locate the dump that actually contains `_3` or drop it from the loop. **Decision required** (open question §G-H6):
confirm with the data owner which dump is authoritative.
**Database impact.** None (read-side ETL). **Migration.** None.
**Testing.** `php artisan legacy:import --fresh` against a **sandbox** `sistemspk` completes without a "table not
found" fatal. Add a guard that skips a missing source table with a logged warning rather than aborting.
**Completion.** ETL runs end-to-end on the real dump.

### 2.2 Reconcile the reconstructed lawyer tables against the real dump (data F1/C1, gap G-H6 — HIGH)
**Objective.** `butiran_peguam_panel_3..6` + `sejarah_ppuu` Blueprints were *reconstructed from PHP* ("never dumped")
but **do** exist in `sistemspk.sql` with real column shapes → ETL of real lawyer qual/cert/firm/bank/specialisation +
PPUU history may silently mis-map/lose columns.
**Files.** The three reconstruction migrations (`…000002`, `…000004`, `…000005`) — compare column-by-column against the
`CREATE TABLE` blocks in `sistemspk.sql`; add an **ALTER migration** (never edit a migration that has already run) to
add/rename any drifted columns. Cross-check the `sistem-rekod-kes-laravel` 29-table migration set (doc 06 §3) as a
secondary reference, but treat the **data dump as source of truth** for these tables.
**Database impact.** Add-only `ALTER TABLE` for any missing columns. **Migration.** New reversible `…_reconcile_lawyer_tables`.
**Testing.** `legacy:import` of `_3..6`+`sejarah_ppuu` row-counts match the dump; spot-check 5 lawyers' qual/cert/bank
fields survive end-to-end.
**Completion.** Reconstructed shapes match the dump; no column silently dropped.

### 2.3 Close the `forms` parity gap (data F3/C3, doc 08 §8 — HIGH)
**Objective.** 8 rekod-kes columns are absent from 2in1 `forms`; the `sharedColumns()` intersect ETL **silently drops**
any column not in the target.
**Files.** New ALTER migration adding the 8 columns (`nama_pegawai_pengesahan`, `tarikh_pengesahan`, `alasan_pembatalan`,
`jenis_kes_lain`, `nyatakanLain`, `alasan_tidak_rujuk_pengantaraan`, `alasan_gagal_pengantara`,
`alasan_tidak_setuju_pengantara`) **after confirming they are live in the production `sistemspk.forms`** (not the stale
`.sql`). If not live → sign off documented loss instead. Also reconcile the column-count discrepancy (model docblock
"94" vs map "98" vs actual 92+4) so the canonical number is recorded.
**Database impact.** Add-only columns on `forms`. **Migration.** New reversible `…_add_rekodkes_forms_columns`.
**Testing.** ETL of a `forms` row carrying those columns preserves them; `php artisan test` green (no model/factory breaks).
**Completion.** `forms` ETL is loss-free for the documented rekod-kes columns, or loss is explicitly signed off.

**Phase 2 completion criteria:** `legacy:import --fresh` runs clean on a sandbox of the real dump with no fatal and
no silent column loss; the authoritative schema source for the lawyer tables is recorded.

---

## PHASE 3 — CRITICAL functional fixes (broken core workflows)

> The three CRITICAL stuck-records + the credential lockout. Depends on Phase 1 (no auth regressions) and Phase 2
> (so fixes are validated against real data). **Each sub-fix is its own atomic commit + test.**

### 3.1 Repair the spine→lawyer offer hand-off (G-C1 / B-1 / STUCK-1 — CRITICAL)
**Objective.** Make spine-issued offers (numeric `status_agihan='1'`) appear in the lawyer's Tawaran list so the 3-tier
spine works end-to-end. Today `AgihanService::kpLulus` writes numeric `DITAWARKAN` (`AgihanService.php:149`, verified)
but `PeguamController` filters the literal string `'Ditawarkan'` (`:45,:46,:70`, verified) → the offer never surfaces.
**Files.** `app/Http/Controllers/PeguamController.php`:
- Replace `where('status_agihan','Ditawarkan')` (`:46,:70`) and `where('status_agihan','Diterima')` (`:45`) with
  `whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN]))` / `bucketValues([DITERIMA])` —
  copy the pattern `PeguamLifecycleService::redistributeActiveCases` already uses correctly (governance F-1).
- On accept (`:84` `update(['status_agihan'=>'Diterima'])`) write numeric `StatusAgihan::DITERIMA`; on reject (`:106,:112`)
  write numeric `PPUU_AGIH_SEMULA` and stop emitting un-decoded history `'T'`.
**Database impact.** None structurally (the **data-converge** migration is 3.2). **Migration.** None here.
**Testing.** Feature test: a case driven through `pengarahTerima→ppuuPilih→pengarahSokong→kpLulus` (lands at `'1'`)
**appears** in the lawyer's `tawaran()` list and can be accepted (→ numeric `'2'`); then Tarik Diri `ensureKesDiterima`
still passes. This is the single highest-value fix.
**Completion.** A spine-offered case is visible + acceptable by the lawyer; `LebihMasaTest` still green.

### 3.2 Write-normalise `status_agihan` + one-off data converge (G-H1 / B-3 — HIGH, root cause)
**Objective.** Stop two encodings being written; converge existing rows so no query needs `LEGACY_STRING_MAP` at read.
**Files.**
- `app/Http/Controllers/AgihanController.php` (single-step) + `PeguamController` — write **numeric** consts only
  (`StatusAgihan::*`), never string labels.
- New **data migration** converging legacy string rows: `'Ditawarkan'→'1'`, `'Diterima'→'2'`, `'Ditolak'/'T'→'4'`,
  `'Diserah Semula'/'S'→'5'` (governance §12.2.3) across `forms.status_agihan` + the `sejarah_*` history columns.
**Database impact.** UPDATE of existing `status_agihan` values (data migration, **reversible** — snapshot first).
**Migration requirement.** Yes — a reversible data migration with a pre-image archive (`forms_status_agihan_archive_…`).
**Testing.** Post-migrate, `SELECT DISTINCT status_agihan FROM forms` yields only numeric codes; agihan + lawyer suites green.
**Completion.** Column holds one encoding; reads no longer depend on the string map.

### 3.3 Give status `9` (Ditolak Pengarah) an exit (G-M1 / B-2 / STUCK-2 — CRITICAL dead-end)
**Objective.** A Pengarah-rejected new case (`pengarahTolakBaru` sets `9`, `AgihanService.php:52`) currently sits in
no bucket with `stage()=null` — invisible, unrecoverable.
**Files.** `app/Support/StatusAgihan.php` (add `9` to a new `BUCKET_DITOLAK` or back into a re-review queue) +
`AgihanService`/`AgihanSpineController` (`stage()` `:157-169`) — add a guarded transition `9 → 0` (re-review) or
`9 → tutup`. Add the queue screen.
**Database impact.** None. **Migration.** None.
**Testing.** Feature test: a case at `9` appears in a recovery queue and can be re-routed to `0` or closed.
**Completion.** No case can be stranded at `9`.

### 3.4 Close the KN no-show + rejected-appointment dead-ends (G-H7 / G-M4 / G-1 / G-2 / STUCK-3,4 — CRITICAL/HIGH)
**Objective.** A `TIDAK_HADIR` appointment leaves `status_kn=DALAM_PROSES` forever; `tolak` orphans the KN appointment-less.
**Files.** `app/Support/KhidmatProsesService.php` (`TEMU_TRANSITIONS` `:47-50`, verified):
- Define a `TIDAK_HADIR` resolution: either `TIDAK_HADIR → SELESAI` setting `status_kn=SELESAI` (mirror legacy "Selesai
  Tanpa Kehadiran Pelanggan") **or** `TIDAK_HADIR → MENUNGGU` reschedule. Pick per product decision (governance §12.1).
- On `tolak` (`:146-152`): explicitly set `status_kn=BATAL` (release) **or** open a rebook path to a fresh `temu_janji
  MENUNGGU`. Never leave KN appointment-less.
**Database impact.** None. **Migration.** None.
**Testing.** Feature tests: a no-show KN reaches a terminal/rescheduled state (not stuck `DALAM_PROSES`); a rejected
appointment is either BATAL or rebookable. Extend `Batch11OfficerProcessingTest`.
**Completion.** No KN can hang in `DALAM_PROSES` after a no-show or rejection.

### 3.5 Deliver lawyer/officer credentials (G-C2 / D-2 / STUCK-6 — CRITICAL)
**Objective.** A provisioned lawyer (`PermohonanPeguamController::provisionLogin` shows temp pw **once** in a flash,
`:111`) or a new officer (`UserController::store`, no email) cannot obtain credentials if the banner is missed.
**Files.**
- If mail is provisioned (Phase 4): send a credential email on `promote()`/`provisionLogin` and `UserController::store`.
- **Mail-deferred fallback (ship regardless):** an **admin "set/regenerate password"** action on the user/lawyer record
  (admin-gated) so credentials can be reset without the email broker, and a **printable credential slip** (ties to Phase 6).
**Database impact.** None. **Migration.** None.
**Testing.** Feature test: after promote, an admin can regenerate + view/deliver the credential; the lawyer can log in.
**Completion.** No approved lawyer/new officer is locked out of first login.

> **G-C3 (cancellation letter on withdrawal)** is split: the **status transitions** already work (`TarikDiriService`);
> the **letter PDF + email** are built in Phase 6 (print) + Phase 4 (email). Tracked there to keep PDF work together.

**Phase 3 completion criteria:** all four CRITICAL stuck-records (STUCK-1,2,3,4) traversable; credential lockout closed;
status_agihan single-encoded; full suite green including new spine/KN tests.

---

## PHASE 4 — Notification coverage restore (G-H2 / G-C2 / G-C3 — HIGH)

> **Precondition:** working `MAIL_*` (today `MAIL_MAILER=log`). If deferred, this phase's deliverables become the
> printed-letter equivalents (Phase 6) + on-screen + the admin-set-password fallback (3.5); revisit when mail is live.

**Objective.** Restore the relied-on transactional emails that collapsed from ~9 legacy triggers to 4 send-sites
(`KesDitawarkanMail`, `AgihanTransisiMail`, `KesLebihMasaMail`).
**Files/modules.** New `Mail` classes + send-sites for:
- registration **decision** (lulus/tolak) to the applicant — `PermohonanPeguamController::keputusan`.
- **deceased/deactivated-lawyer reassignment** notice to Pengarah/PPUU/KP — `PeguamLifecycleService`.
- new-officer / new-lawyer **credential** email — `UserController::store`, `PermohonanPeguamController::promote` (pairs with 3.5).
- **withdrawal-tier** notices + the **cancellation letter** email — `TarikDiriService` (attach the Phase 6 PDF).
Keep all sends **best-effort** (try/catch + log) like the existing three, so a mail failure never blocks the transition.
**Database impact.** None (consider a `notifications`/queue table only if async is wanted — currently all mail is sync;
`jobs`/`job_batches` exist but unused — see doc 06 RP-3). **Migration.** None unless adopting queued mail.
**Testing.** `Mail::fake()` feature tests asserting each trigger dispatches the right mailable to the right recipient.
Pattern already exists (`NotifikasiAgihanTest`).
**Completion.** The 5 missing notification triggers fire (or their fallbacks do); each covered by a `Mail::fake` test.

---

## PHASE 5 — Public self-service (G-H3 / G-M5 — HIGH/MEDIUM)

**Objective.** Restore the public application-status lookup and give citizens a working password reset.
**Files/modules.**
- **Semak Status Permohonan** (G-H3): new public route + controller reproducing legacy `semak.php`/`checkstatus.php`
  — a no-login screen where a lawyer-applicant enters IC and sees `permohonan_status` (0–5). Read-only; rate-limited;
  reuse the existing captcha. (No such route exists today — verified.)
- **Citizen password reset** (G-M5): citizens log in by `nokp` (IC) but the only reset is the email broker. Add an
  IC-based reset path (security-question / OTP-to-registered-contact / admin-assisted) so a citizen with no/blank
  account email can recover. Confirm whether citizen email is captured at register (if always captured, the email
  broker may suffice + a "no email on file → contact branch" message).
**Database impact.** Possibly a `password_reset` token variant keyed on `nokp` if not reusing the broker. **Migration.**
Only if a new token table/column is needed; otherwise none.
**Testing.** Feature tests: public semak returns status for a known IC and a generic "not found" for unknown (no
enumeration leak); citizen reset path issues + consumes a token.
**Completion.** Applicants can self-check status; citizens have at least one working reset path.

---

## PHASE 6 — Print / letter artefacts (G-H4 / G-C3 — HIGH/CRITICAL-doc)

**Objective.** Reinstate the applicant-facing official letters dropped in the port (legacy ~19 prints → 3 per-case today
in `CetakanController`: `ringkasan`, `agihan`, `laporan`).
**Files/modules.**
- **Applicant approval letter** (`cetakanKelulusanPemohon`) + **full lawyer-application dossier** — new dompdf views +
  `CetakanController`/`PermohonanPeguamController` actions, matching the existing 3 Blade dompdf views' style.
- **Surat Batal Penugasan** (G-C3 — the legally-meaningful cancellation letter): generate a dompdf letter on KP-approved
  Tarik Diri in `TarikDiriService`, save under `storage` (mirror legacy `uploads/surat/…`), and hand it to Phase 4 to
  email to the lawyer.
**Database impact.** None (PDFs are generated artefacts; optionally log the file path on the case/withdrawal row).
**Migration.** Only if persisting the generated-letter path (an add-only column). **Testing.** Feature test asserting
each endpoint returns a `application/pdf` 200 for an eligible record; the cancellation letter is produced on TD approval.
**Completion.** Approval letter, application dossier, and cancellation letter are generatable as PDF.

---

## PHASE 7 — Status governance hardening (A-1, B-3/4/5, D-1, X-2, X-4, H-1)

> Structural, behind the functional fixes. Converts the read-time reconciliation into write-time enforcement and adds
> the missing transition guards + DB-level constraints. Non-destructive (constraints + enums; existing data converged in 3.2).

**Objective.** Make illegal status transitions impossible at the model/DB layer, not just by which button shows.
**Files/modules.**
- Promote status fields to **PHP 8.3 backed enums** + a single `transition($from,$event):$to` table per workflow for
  `forms.status`, `status_kn`, `temu_janji.status`, `permohonan_status`, `checkbox_value_status` (governance §12.2).
- Add **`ensureStatus` from-guards** to the two ungated transitions — lawyer `terima`/`tolak` (B-5) must assert
  `normalise(status_agihan)===DITAWARKAN` before writing.
- **Mutual guard between the two agihan front-ends** (B-4): `AgihanController@store` refuses to overwrite a case whose
  `status_agihan ∈ {8,10,13,12,16,17}` (mid-spine). (Full retirement of the single-step path is the gated Phase 10 R-WF-01.)
- **Gate `permohonan_status='3'` (Tarik Diri)** (D-1): require an approval permission + a from-guard (verified today it
  is `auth`-only).
- **DB CHECK / enum columns** (MySQL 8) for `status_kn`, `temu_janji.status`, `permohonan_status`,
  `checkbox_value_status`, `statusAktif`; **unique partial index** "one `status_rekod='aktif'` per `id_kes`" on
  `sejarah_ppuu` (X-5). Constrain free-text `status_pengantaraan` to an enum (H-1).
- Tackle the **decorative-permission** problem (roles §4.4): add real `permission:` route gates on `/kes`, `/oyd`,
  `/kpi`, `/cetak` (so the matrix stops lying) **or** delete the dead permission names (the latter is gated in Phase 10
  R-PERM-01 — do the *add-gates* half here, the *delete-names* half there).
**Database impact.** New CHECK constraints / enum column types + one unique partial index; no data deletion (data already
converged in 3.2). **Migration requirement.** Yes — reversible per-column constraint migrations, one table per commit.
**Testing.** Feature tests asserting an illegal `$to` is rejected at the model layer; the mid-spine overwrite is refused;
`permohonan_status=3` requires the new permission. Extend `Batch7RbacMatrixTest` for the new route gates.
**Completion.** Every state machine rejects illegal transitions at model+DB level; the matrix UI reflects real access.

---

## PHASE 8 — Database integrity (FKs, indexes, charset, type fixes, branch scope)

> Mechanical, reversible, table-by-table. Each FK add follows the doc 08 §3 recipe: widen the legacy `int` PK to
> `bigint`, backfill, then `foreign()`. Non-destructive.

**Objective.** Convert soft links to real FKs where safe, add the hot-path indexes, fix the one type-mismatch FK, and
extend branch isolation beyond `forms`.
**Files/modules (all new ALTER migrations + the relevant models/services):**
- **Type-mismatch FK** (data F3, G-M10): clean + cast `laporan_kes.id_kes varchar(20) → int`, then FK → `forms.id`.
  (Most blast-radius FK — do it first, isolated.)
- **Widen legacy `int` reference PKs to `bigint`** then add deferred FKs: `sejarah_ppuu.id_kes`, `uploaded_files.{id_kes,
  id_khidmat,kpBaru-join}`, `khidmat_nasihat.{id_temu_janji,id_forms,id_mahkamah}` where polymorphism allows
  (the `id_mahkamah` sivil/syariah split stays FK-less until Phase 10 table-merge).
- **Missing indexes** (data §4): `forms(status_agihan, agih_kepada)`, `laporan_kes(id_kes,no_fail)`,
  `khidmat_nasihat(id_pengguna,id_pegawai_kn,cawangan_id,id_temu_janji)`, `temu_janji(id_khidmat_nasihat,id_pegawai_kn,
  status)`, `peguam_panel(kp_peguam)`, `pegawai_jbg(cawangan,status_aktif)`, `posters(status_poster)`,
  `butiran_peguam_panel_2(permohonan_status,semakan_ppuu)`, `butiran_peguam_panel(kpBaru)`.
- **Branch isolation breadth** (roles §4.5, G-M8): add a `CawanganScope`-equivalent to `KhidmatNasihat` (keyed
  `cawangan_id`) and to `butiran_oyd`; delete the 3 hand-rolled KN branch filters
  (`KhidmatProsesService::branchFilter`, `LaporanKnService::resolveBranchFilter`, report queries) once the scope covers them.
  Add per-permission read gates on the lifecycle queues (`agihan.senarai`, `tarikdiri.senarai`, `kemaskini-bidang.index`,
  `permohonan-peguam.index/show`) so reads are branch+role-scoped (roles §4.5).
**Database impact.** PK widenings + FK adds + index adds + (no data deletion). **Migration requirement.** Yes — one
reversible migration per table; FKs added only after the §11 archive + a clone test of up/down.
**Testing.** `Batch7ScopeTest`-style tests proving KN/OYD are now branch-scoped (the current gap is *untested* per
roles §5); an integrity test that orphaned `laporan_kes` rows are caught post-FK; query-plan/EXPLAIN smoke on the indexed
hot paths. Full `migrate:fresh` + `legacy:import` + `test` on a clone.
**Completion.** The type-mismatch FK is in place; hot paths indexed; branch isolation consistent across `forms`, KN, OYD.

---

## PHASE 9 — Reporting reconciliation + operational hardening (G-M6, G-M7, secrets)

**Objective.** Close the remaining MEDIUM reporting inconsistencies and the operational items.
**Files/modules.**
- **SLA vs KPI end-date** (G-M7): reconcile `SlaMatrix` (uses `tarikh_persetujuan`) vs `KpiController` (uses
  `tarikh_selesai`) for the 60-day mediation-service rule — pick one business definition, update the other.
- **Pengantaraan wide-export stubs** (G-M6): populate the degraded `-Tiada Maklumat-` columns
  (`alasan_*_pengantara`, `kategori_kes2`, perjanjian dates) once the pengantaraan workflow that fills them is ported;
  fix the `tarikh_persetujuan` "TARIKH PERJANJIAN PENYELESAIAN" mismap in `penugasanPengantaraan`.
- **Secret rotation** (doc 06 R-CODE-01 — operational): rotate the 5 cbjbg microservice secrets + the JBG Gmail
  app-password before production. Confirm production `.env` carries no committed secrets.
**Database impact.** None (report-layer + ops). **Migration.** None.
**Testing.** Snapshot tests that SLA and KPI agree on the same metric; export tests that the previously-stubbed columns
now emit real values; a config test that required secrets are present at boot.
**Completion.** SLA/KPI agree; mediation exports complete; secrets rotated.

---

## PHASE 10 — DESTRUCTIVE REMOVALS (gated — runs last, per-item §11 + archive)

> **Hard gate.** No item in this phase executes until it passes the **`data-and-database-review.md §11` seven-step
> procedure** (code grep → data count → reports/ETL check → regulatory check → reversible migration → **archive** →
> test up/down on a clone). Each removal = its own commit with the archive artefact recorded. The removal-list
> (`06-redundancy-and-removal-list.md`) confidence ratings govern what is safe.

| Order | Item | Confidence (doc 06) | Pre-conditions before drop | Test |
|---|---|---|---|---|
| 10.1 | **`items` table + `Item` model + ETL `$verbatim` entry** (R-TB-01) | **high** | grep confirms 0 routes/views (verified); remove `'items'` from `ImportLegacyData::$verbatim` (`:29`); archive any rows | `migrate:fresh` + `php artisan test` + `legacy:import` must not error on missing table |
| 10.2 | **`StatusAgihan::TOLAK_KE_CAWANGAN ('14')` dead constant** (R-CODE-02) | medium | grep `sistemspk` for `status_agihan='14'`; **keep the display label if any legacy `14` rows exist**, delete only the write-path expectation | agihan suite green |
| 10.3 | **Unused seeded permissions** `menu.selenggara`, `peguam_panel.manage`, `peguam.permohonan.view` (R-PERM-01) | medium | grep whole tree (`can(`, `@can`, `permission:`, `hasPermissionTo`) — drop ONLY names with zero hits outside the seeder; re-seed + `permission:cache-reset` | auth/permission feature tests + per-role menu smoke |
| 10.4 | **`checkbox_value_status=0` unnamed state** (R-COL-01) | medium | choose rename (`SELECTED_AT_DAFTAR`) vs remap `0→2`; if remap, data-migrate existing `0` rows first | pengkhususan feature tests (`hasActiveCaseInCategory`, `PengkhususanService`) resolve same set |
| 10.5 | **Single-step assign `AgihanController@form/@store` + routes** (R-WF-01) | **low (high-risk live path)** | **product decision required**; only after Phase 3.1/3.2 (lawyer Tawaran fixed, encodings converged) + Phase 7 mid-spine guard; keep `@beban` workload | full agihan + lawyer regression suite; spine-offered case reaches lawyer and accept/reject is numeric |
| 10.6 | **Decorative-permission name deletions** (roles §4.4 second half) | medium | only after Phase 7 added the *real* gates on `/kes`,`/oyd`,`/kpi`,`/cetak`; drop the now-truly-dead `kes.view/create/update`, `pengantaraan.manage`, `mahkamah.manage`, `lampiran.manage`, `cetakan.view`, `oyd.manage`, `kpi.view` | matrix UI reflects only enforced perms; RBAC tests green |

**Explicitly NOT removed (doc 06 §4 — do not touch):** `model_has_permissions` (spatie-required),
`jobs`/`job_batches`/`failed_jobs` (recommend keep), `welcome.blade.php` (live landing), `butiran_peguam_panel` v1
(still read by `PeguamController:210`/`PeguamPanelController:28` — migrate those reads to `_2..6` **first**, only then
archive+drop), `mahkamah_sivil`/`mahkamah_syariah` (merge is a Phase-3-DB refactor, not a deletion). **Never** drop any
populated legacy table without §11.

**Out-of-scope leftover dirs (doc 06 §3):** `sistem-rekod-kes-laravel/` and `spk-laravel/` are sibling reference
folders, not 2in1 dependencies. Archive (don't hard-delete) after closing the two cross-checks (schema reference for the
`forms` drift; `DocumentController` parity vs 2in1 `LampiranController`). Zero impact on 2in1.

---

## Dependency graph (why this order)

```
0 (rails)
 └─ 1 (RBAC security)  ── gates ──┐
 └─ 2 (ETL blockers)  ── gates ──┤
                                  ├─ 3 (CRITICAL functional) ─┬─ 4 (notifications, needs mail)
                                  │                           ├─ 6 (prints; feeds 4's cancellation letter)
                                  │                           └─ 7 (status governance hardening)
 1 ──────────────────────────────┴─ 5 (public self-service)
 2 ───────────────────────────────── 8 (DB integrity / FKs / scope) ── 9 (reporting + ops)
 0..9 + per-item §11 gate ─────────── 10 (DESTRUCTIVE removals)
```

- **Security (1)** before everything: it is the highest-blast-radius, zero-schema-risk work, and several later phases
  (10.3/10.6 permission deletes, 5 public surfaces) depend on a correct RBAC model.
- **ETL blockers (2)** before any data-dependent functional work (3) — you cannot validate a fix against data that
  won't import.
- **CRITICAL functional (3)** before notifications/prints/governance, which build on the now-working spine + KN.
- **Destructive (10)** strictly last, each item independently gated + archived.

---

## Cross-cutting rules (apply to every phase)

1. **Small atomic commits.** One logical change per commit; never mix a security fix, a schema change, and a refactor.
2. **Test-gate each phase.** `php artisan test` must be green (≥ the Phase 0 baseline pass count) before starting the next.
3. **Reversible migrations only.** Every schema/data migration has a real `down()`; legacy raw-SQL tables are altered via
   new migrations, never by editing `legacy-domain.sql` after it has run anywhere (doc 08 §11.5).
4. **Archive before any destruct.** `CREATE TABLE <t>_archive_YYYYMMDD AS SELECT …` (or column snapshot) precedes every
   `DROP`/`dropColumn` (Phase 10 only).
5. **Mail-deferred fallbacks.** Any phase that assumes `MAIL_*` (3.5 email half, 4) ships its printed/admin fallback so
   the feature is usable even with `MAIL_MAILER=log`.
6. **Decisions to confirm before building** (open questions from the prior deliverables): single-step assign retire vs
   keep (10.5/R-WF-01); is production `MAIL_*` provisioned (3.5/4); which dump is authoritative for `_3..6` (2.1/2.2);
   which letters are legally required (6); is KN payment reconciliation in scope (not planned above — see G-M3);
   is mediator leave/elaun in scope (G-L4, not planned).

---

## Items deliberately deferred / out of this plan's execution scope

| Item | Why deferred | Reference |
|---|---|---|
| KN payment confirmation / receipt step (G-M3) | Open product decision: is the fee a real gate or informational? `status_bayaran` is a dead-write today. | governance G-3 |
| Mediator leave/allowance module (G-L4) | Confirm in-scope before porting `formTambahCuti`/`detail_elaun`. | gap G-L4 |
| `forms` monolith decomposition (`kes` + detail tables) | Large structural epic; behind §11; not required for parity. | data §8 |
| `mahkamah_sivil`+`mahkamah_syariah` → one `mahkamah`+`jenis` | MEDIUM refactor (controller rewrite + migration), not a deletion; only if schema cleanup is greenlit. | data §6 / doc 06 TB-2 |
| Lawyer-profile normalisation (`_2.._6` → one entity) | Schema-normalisation epic, out of removal scope. | data F5 / doc 06 TB-4 |
| Captcha hardening | Weak 2-number-sum captcha; harden when public-surface abuse is a concern. | roles §4.9 |

---

> **End state after Phases 0–10:** the privilege-escalation surface is closed; `legacy:import` runs loss-free on the
> real dump; the 3-tier spine, KN no-show/reject, and credential delivery work end-to-end; notifications and official
> letters are restored; every status machine is enum+guard+DB-constraint enforced and single-encoded; FKs/indexes/branch
> isolation are consistent; and only genuinely-dead artefacts are removed — each archived and tested. No production data
> was dropped on the strength of the audit alone.
