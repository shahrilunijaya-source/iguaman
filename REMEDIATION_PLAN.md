# REMEDIATION PLAN — iGuaman 2in1

Derived from `SYSTEM_AUDIT_REPORT.md` (2026-07-01, merged with the Codex cross-audit). Grouped into 7 phases. Each action lists **priority · dependencies · files · risk of change · required tests · definition of done**. Effort: S ≤ half-day, M ≤ 2 days, L > 2 days.

> Sequencing rule: Phase 1 blocks release. Phases 2-3 before production. Phases 4-7 are hardening/quality and can run in parallel after release-blockers clear.

---

## Phase 1 — Immediate Critical Fixes (release blockers)

### 1.1 Env-guard the seeders (kills admin backdoor) — `DB-01`
- **Priority:** P0 · **Deps:** none · **Risk:** Low
- **Files:** `database/seeders/DatabaseSeeder.php`, `TestUsersSeeder.php`, `DemoUserSeeder.php`
- **Action:** `if (! app()->environment(['local','testing'])) { return; }` at the top of both demo/test seeders; keep master/ref seeders unconditional.
- **Tests:** assert seeding in `production` env creates zero `@test.local`/`demo@example.com` users.
- **DoD:** `APP_ENV=production php artisan db:seed` creates no demo/test accounts; existing prod DB scanned + purged if present.

### 1.2 Hide the demo-login modal in production — `AUTH-03`
- **Priority:** P0 · **Deps:** 1.1 · **Risk:** Low
- **Files:** `resources/views/system/login.blade.php:141-329,361-369`
- **Action:** Wrap the demo modal + JS in `@unless(app()->isProduction())` (or `@env('local')`).
- **Tests:** `GET /system/login` in `production` env body must not contain `admin@test.local` or `js-demo-login`.
- **DoD:** demo modal absent in prod render; present in local.

### 1.3 Fix attachment-download IDOR — `AUTH-01`
- **Priority:** P0 · **Deps:** none · **Risk:** Low (adds a check)
- **Files:** `app/Http/Controllers/LampiranController.php:47-53`
- **Action:** Resolve the owning case/KN via `lampiran->id_kes`/`id_khidmat`; run `assertBranchAccess()`-style check before `Storage::download`.
- **Tests:** branch A user → 403/404 on branch B attachment; owner succeeds.
- **DoD:** cross-branch download denied; audit entry written (ties to LOG-03).

### 1.4 Fix payment-claim IDOR — `AUTH-02`
- **Priority:** P0 · **Deps:** none · **Risk:** Medium (7 actions)
- **Files:** `app/Http/Controllers/LejarTuntutanController.php:46-141`; `app/Models/LejarTuntutanBayaran.php`
- **Action:** Add a branch/ownership guard (via linked `form`/`khidmat_nasihat` branch) in `show/update/hantar/semak/lulus/tolak/bayar`; consider a `CawanganScope`/policy.
- **Tests:** cross-branch `show` + each mutation → 403; same-branch → 200.
- **DoD:** all 7 actions branch-scoped; regression tests green.

### 1.5 Escape stored XSS in duplicate-check — `INJ-01`
- **Priority:** P0 · **Deps:** none · **Risk:** Low
- **Files:** `resources/views/kes/form.blade.php:369-370`
- **Action:** Replace `innerHTML` concatenation with `document.createElement` + `.textContent` per row (or escape each field).
- **Tests:** payload `<img onerror>` in `nama` renders inert text.
- **DoD:** no HTML from `r.nama/no_fail/status` is interpreted.

### 1.6 Fix export-download IDOR — `AUTH-09`
- **Priority:** P0 · **Deps:** none · **Risk:** Low
- **Files:** `app/Http/Controllers/LaporanController.php:115-128` (`eksportPukal`/`muatTurunEksport`), new `exports` tracking row/table
- **Action:** Record exporter `user_id` + the export's branch filter; at download time check ownership or re-apply the branch filter; use an unguessable filename/token.
- **Tests:** user B → 403 on user A's export; owner succeeds; guessing a name → 404 without leaking existence patterns.
- **DoD:** exports owner/branch-bound; audited (LOG-03).

### 1.7 Wire scheduled jobs + fix export execution — `CFG-01`, `CFG-02`
- **Priority:** P0 · **Deps:** server access · **Risk:** Medium (ops change)
- **Files:** `deploy.sh`, `DEPLOY.md`, `app/Jobs/ExportLaporanJob.php`, `app/Http/Controllers/LaporanController.php`, `.env`
- **Action:** (a) Hostinger cron `* * * * * cd <path> && php artisan schedule:run >>/dev/null 2>&1`; document it. (b) Shared host: switch bulk export to `dispatchSync` (or `QUEUE_CONNECTION=sync`), or add a cron `queue:work --stop-when-empty`. Add `failed()` + `$tries` (PROC-19).
- **Tests:** `Queue::fake` dispatch test; manual — export → file appears; `schedule:run` → commands execute.
- **DoD:** exports complete; the 3 scheduled commands verified running on the server.

---

## Phase 2 — Security & Data Integrity

| # | Action | ID | Pri | Deps | Files | Risk | Required tests | DoD |
|---|---|---|---|---|---|---|---|---|
| 2.1 | `CawanganScope` fail **closed** on unresolved branch + log | AUTH-04 | P1 | — | `CawanganScope.php` | Med | scoped user w/ bad branch sees 0 rows | unresolved → deny; logged |
| 2.2 | Per-resource view permissions on list/report GET routes; gate nav `@can` | AUTH-05 | P1 | — | `routes/web.php`, `layouts/staff.blade.php` | Med | narrow role → 403 out-of-scope index | GET routes module-gated |
| 2.3 | Lawyer case ownership by FK not name string | AUTH-06 | P1 | backfill | `PeguamController`, `Peguam/TuntutanController`, `Form` assignment | Med | same-name lawyers isolated | ownership on `kp_peguam`/id |
| 2.4 | `tarikDiri`: add permission + from-state guard | AUTH-07 | P1 | — | `PermohonanPeguamController.php:144-155`, `web.php:462` | Low | unauthorized → 403; Lulus app cannot withdraw | gated + guarded |
| 2.5 | Per-account lockout + strong password policy + throttle reset routes | AUTH-08 | P1 | — | `SystemAuthController`, `PasswordResetController`, reset routes, `AppServiceProvider` | Low | 429 after N per-identifier fails; weak pw rejected | `Password::defaults()`; lockout + reset throttle |
| 2.6 | Add CSP (nonce) + HSTS | INJ-02 | P1 | HTTPS confirmed | `SecurityHeaders.php`, Blade inline scripts | Med | header present; no CSP console errors | CSP enforced, HSTS on |
| 2.7 | Neutralize CSV/formula injection in exports | INJ-03 | P1 | — | `LaporanExport`, `WideExport`, `KesExport`, `PendaftaranKnExport` | Low | `=cmd` cell exported as text-safe | risky-prefix cells escaped |
| 2.8 | Prod `.env` hardening + deploy preflight | CFG-03/04/06/10 | P1 | server | `.env` (server), `deploy.sh`, `.env.production` | Low | preflight fails on `APP_DEBUG=true` | debug off, secure cookie, smtp, daily/warning logs |
| 2.9 | Rotate HF bot creds; stop dev/prod reuse | CFG-07 | P1 | HF console | `.env` (server), HF Space secrets | Low | chatbot works post-rotation | new creds live; old revoked |
| 2.10 | `int`→`decimal(12,2)` money columns | DB-02 | P1 | unit confirm | migration on `forms.nilai_sumbangan/kos_oyd/kos_pihak_lawan` | Med | sen values persist | columns decimal; backfilled |
| 2.11 | `unique` index on `forms.no_fail` + retry on collision | DB-03 | P1 | — | migration; `NoFailGenerator` + callers | Low | concurrent create → no dup | unique enforced |
| 2.12 | Repoint `lawyerProfile()` to `id_user` FK | DB-04 | P1 | backfill | `User.php:84-88` | Med | duplicate-IC lawyers resolve correctly | FK-first resolution |
| 2.13 | Security logging: login/failed/permission-denied/exports | LOG-01/02/03 | P1 | — | auth controllers, `bootstrap/app.php`, export/download controllers, `Audit.php` | Low | events recorded w/ IP/UA (no secrets) | security events auditable |
| 2.14 | Stop storing raw PII in audit remarks; mask IC | LOG-07 | P1 | — | `Audit.php`, `OydController`, `UserController`, KN/peguam controllers | Low | remark contains no full IC/email | ids/masked values only |
| 2.15 | CLI `--source` whitelist | CFG-08 | P2 | — | `ImportLegacyData.php` | Low | invalid source rejected | regex-validated |
| 2.16 | Explicit `$fillable` on sensitive models | CFG-09 | P2 | — | `Form`, `KhidmatNasihat`, `PeguamPanel`, `User` | Med | mass-assign of `role`/`status`/`is_active` blocked | allow-lists in place |

---

## Phase 3 — Functional & Process Corrections

| # | Action | ID | Pri | Files | Risk | Tests | DoD |
|---|---|---|---|---|---|---|---|
| 3.1 | Wrap lawyer-approval promote+provision in `DB::transaction` | PROC-01 | P1 | `PermohonanPeguamController.php:119-142` | Low | forced mid-failure → full rollback | atomic |
| 3.2 | Wrap Pembelaan-Awam intake in transaction | PROC-02 | P1 | `PembelaanAwamController.php:85-90` | Low | failure → no orphan case | atomic |
| 3.3 | Mediator re-assignment from-guard + audit | PROC-03 | P1 | `PengantaraanService.php:79-106` | Low | re-assign blocked without override | guarded |
| 3.4 | `DITOLAK → DRAF` claim re-work path | PROC-04 | P1 | `LejarTuntutanService.php:33-40` | Low | rejected claim re-opened | transition exists |
| 3.5 | Branch-transfer aging/escalation command | PROC-05 | P1 | `TransferCawanganService.php`, `routes/console.php` | Med | stale `DIPINDAH` surfaced/returned | scheduled + tested |
| 3.6 | Citizen IC-based reset path | PROC-06 | P1 | `PublicAuthController`, reset flow, awam login view | Med | email-less citizen can recover | reset path + link |
| 3.7 | Reschedule against real slots | PROC-07 | P1 | `AwamRescheduleRequest.php`, `KhidmatNasihatService.php` | Low | in-window rejected clearly, valid slot accepted | no 422 dead-end |
| 3.8 | Backfill `id_pengguna` on register/login by nokp | PROC-08 | P1 | `PublicAuthController`, `PortalController` | Med | staff-created KN visible to citizen | linkage on match |
| 3.9 | Email credential/activation link on lawyer provision | PROC-09 | P1 | `PermohonanPeguamController.php:200-228`, new Mailable | Low | provision → email sent | no flash-only credential |
| 3.10 | Port cancellation-letter PDF + email on KP withdrawal approve | PROC-10 | P1 | `TarikDiriService.php:74-126`, `CetakanController` | Med | approve → PDF generated + sent | document restored |
| 3.11 | Confirmations on irreversible actions | UX-05, UX-06 | P1/P2 | `agihan/maklumat`, `kes/show`, `permohonan-peguam/show`, `kemaskini-bidang/index`, + reschedule/reactivate/no-show/reject | Low | — (manual/e2e) | confirm dialogs; `type="submit"` fixed |
| 3.12 | From-status guards: `keputusan`/`sokong`, `terima`/`tolak`, close/lulus | PROC-20/21/12 | P2 | `PermohonanPeguamController`, `PeguamController`, `KeputusanController` | Low | re-decide/out-of-order → blocked | guarded |
| 3.13 | Idempotency on create endpoints (KN, lawyer claims) | PROC-14/15 | P2 | `KhidmatNasihatController`, `Awam/PermohonanController`, `Peguam/TuntutanController` | Med | double-submit → single record | nonce/dedupe |
| 3.14 | Constrain mediation status to enum | PROC-13 | P2 | `PengantaraanRequest`, constants | Low | invalid status rejected | `Rule::in` |
| 3.15 | Branch/track ownership on tarik-diri/bidang approvals | PROC-17 | P2 | `TarikDiriController`, `KemaskiniBidangController` | Med | cross-branch approve → 403 | scoped |
| 3.16 | Cron/job failure alerting + per-row try/catch | PROC-18/19 | P2 | `routes/console.php`, `LebihMasaService`, `ExportLaporanJob` | Low | failure surfaced | `onFailure` + `failed()` |
| 3.17 | Queue the mailables | PROC-11 | P2 | `app/Mail/*` | Low (needs worker) | mail queued not inline | `ShouldQueue` |
| 3.18 | Fail (not self-heal) on impossible perakuan state | PROC-16 | P2 | `PerakuanService.php:44-60` | Low | INTERIM+null → throws | explicit failure |

---

## Phase 4 — Architecture Simplification

| # | Action | ID | Files | Risk | DoD |
|---|---|---|---|---|---|
| 4.1 | Extract `KesService` (litigation core) | ARCH-02 | `KesController.php` | Med | controller thin; tests in service |
| 4.2 | Slim `KhidmatNasihatController` (mapper + service) | ARCH-01 | `KhidmatNasihatController.php`, `KhidmatProsesService` | Med | <250 lines; logic in services |
| 4.3 | Consolidate `forms` report filter builder | ARCH-03 | `LaporanRegistry`, 4 report controllers | Med | single filter source |
| 4.4 | Centralize branch list + period helpers | ARCH-04, CODE-06 | statistik controllers, new value class/trait | Low | one definition |
| 4.5 | Wrap `KeputusanController` sequences + `pushToKn` in transactions | CODE-01 | `KeputusanController`, `KesKnSyncService` | Low | atomic close/selesai |
| 4.6 | Extract `PeguamProfilUpdateService`; `ChatbotClient` adapter | CODE-05, ARCH-06 | `PeguamController`, `ChatbotController` | Low | services own logic |
| 4.7 | Decide + document notification strategy | ARCH-07 | events/mail/listeners | Low | one consistent pattern |
| 4.8 | Resolve `ButiranPeguamPanel` v1 vs v2-6 source-of-truth | CODE-03 | models + views | Med | precedence documented or v1 deprecated |

---

## Phase 5 — Performance Optimisation

| # | Action | ID | Files | Risk | DoD |
|---|---|---|---|---|---|
| 5.1 | Stream/chunk heavy exports (`cursor()`/`FromQuery`) | PERF-01, PERF-07 | 5 export paths | Low | no OOM/timeout on large export |
| 5.2 | Cache + SQL-rank the case-assignment shortlist | PERF-02 | `AgihanSpineController`, `PeguamShortlistService` | Med | 2 full loads removed |
| 5.3 | Cache dashboard + SLA/KPI aggregates (short TTL) | PERF-03/04 | `StatistikController`, `SlaMatrix`, `KpiController` | Low | ≤1 recompute/TTL |
| 5.4 | Composite indexes on filtered `tarikh_*` pairs | PERF-04 | new migration | Low | index used by SLA/KPI |
| 5.5 | Cache/replace `DISTINCT` dropdown scans | PERF-05, PERF-08 | `KesController`, `RefCuti` callers | Low | no per-page `DISTINCT` on `forms` |
| 5.6 | Consider `file` cache store on shared host | PERF-06 | `.env` (server) | Low | cache off the DB round-trip |

---

## Phase 6 — Code Cleanup

| # | Action | ID | Files | DoD |
|---|---|---|---|---|
| 6.1 | Status constants (`StatusAgihan`-style) | CODE-04 | `SejarahPeguamPanel`, `FormStatus`, callers | no bare status literals |
| 6.2 | Run `./vendor/bin/pint`; add PHPStan L5-6 | CODE-08 | repo-wide, CI | pint clean; static analysis in CI |
| 6.3 | Split >50-line functions | CODE-07 | `PeguamController`, `ChatbotController` | focused helpers |
| 6.4 | Fix `modifiedDate` type; audit-trail retention/`bigint` | DB-12, DB-11 | migrations | correct types; growth bounded |
| 6.5 | UX cleanups: peguam active tab, `number_format` money, chart/icon a11y, table overflow wrappers | UX-08/09/10/11/12 | multiple blades, `system.css` | a11y + responsive fixed |
| 6.6 | Chatbot privacy notice + minimize forwarded fields | CFG-13 | `ChatbotController`, chat widget view | consent/notice; minimal data |
| 6.7 | Confirm/gate the two public doc pages | CFG-14 | `.htaccess`, `routes/web.php` | non-sensitive confirmed or gated |
| 6.8 | Remove OCR spike if not roadmapped | ARCH-05 | see `DEAD_CODE_AND_CLEANUP.md` | dead code removed |
| 6.9 | Namespace `app/Support` (optional) | ARCH-08 | `app/Support/*` | grouped by role |

---

## Phase 7 — Testing & Production Hardening

| # | Action | ID | Files | DoD |
|---|---|---|---|---|
| 7.1 | Isolate test DB + `RefreshDatabase`; hard-fail if not `testing`; fix 120s suite timeout | TEST-01 | `phpunit.xml`, `tests/TestCase.php` | tests never touch dev/prod DB; suite completes |
| 7.2 | Stand up CI (mysql + `composer test` + `pint --test` + `composer audit`) | TEST-02 | `.github/workflows/` | tests gate every push/PR |
| 7.3 | Add tests: queued export, login lockout (429), scheduled commands (`--purge`, luput), tenant isolation, IDOR regressions (AUTH-01/02/09) | TEST-03 | `tests/` | negative/failure paths covered |
| 7.4 | Enable branch protection + secret scanning/push protection | CFG-12 | GitHub repo settings | `main` protected; secrets scanned |
| 7.5 | Keep `composer audit`/`npm audit` in CI; bump patch/minors; verify `laravel/pao` provenance | CFG-11 | `composer.json`, `package.json` | audits clean in CI |
| 7.6 | Soft deletes for ref-tree + `User`; deactivate not hard-delete | DB-06 | models, controllers | no accountability loss on delete |
| 7.7 | DB backstops: unique slot tuple; FK on `sejarah_ppuu`; before/after audit diffs; store actor id | DB-10, DB-09, LOG-05/06 | migrations, `Audit.php` | integrity + forensics hardened |
| 7.8 | Document backup/restore cadence; verify `storage:link` post-deploy | (ops) | `DEPLOY.md`, `deploy.sh` | backup + symlink verified |

---

## Cross-cutting Definition of Done (all phases)

- [ ] No P0/P1 finding open without a linked, merged fix or an explicit accepted-risk sign-off.
- [ ] New/changed behaviour has a test, including a negative/failure case.
- [ ] Prod `.env` verified on server (`APP_DEBUG=false`, secure cookies, smtp, log level).
- [ ] `schedule:run` + queue/export path confirmed working on Hostinger.
- [ ] `pint --test` and `composer audit` green in CI.
