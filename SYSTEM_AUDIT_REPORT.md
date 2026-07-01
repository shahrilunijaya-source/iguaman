# SYSTEM AUDIT REPORT — iGuaman 2in1

**Audit date:** 2026-07-01
**Commit audited:** `0438624` (main, working tree clean)
**Method:** 9 parallel specialist passes (auth/tenant, injection/config, code quality, architecture, database, performance, business process, frontend/logging, testing/devops), cross-validated and reconciled. Every P0 and the key P1s were hand-verified against source. **This report also merges a second independent audit (Codex, `AUD-xxx` numbering, 22 findings) — its unique valid findings and empirical verification results are folded in below and marked `[Codex AUD-0xx]`.** `CONFIRMED` = observed in code; `POSSIBLE` = requires runtime/prod-config confirmation.

> Secrets are never printed. Where a secret is involved, only the config **key** and a safe/unsafe assessment are given.

---

## 0. System Architecture Summary

**Stack:** PHP 8.3 · Laravel 13 · MySQL 8.4 · Blade + vanilla/Alpine JS · Vite 8 + Tailwind v4. Plain custom auth (no Filament/Breeze/Jetstream). Deployed to Hostinger shared hosting (Laravel-in-`public_html`, git-webhook pull + manual SSH `deploy.sh`).

**Scale:** ~60 controllers, ~40 models, ~32 services (`app/Support`), 54 migrations, 148 Blade views, 344 test methods across 57 files, 158 commits.

**Origin:** Consolidation of two legacy government systems (`sistem-peguam-panel` + `sistem-rekod-kes`) plus an advisory/appointment system into one Laravel app ("2in1"), then a 22-item enhancement roadmap.

**Roles (spatie/laravel-permission):** `admin` (super, `Gate::before` bypass) · `ketua_pengarah` · `pengarah` / `pengarah_pembelaan_awam` / `ketua_pembelaan_awam` · `koordinator` · `pegawai` · `ppuu` · `pembantu_tadbir` · `prison_officer` · `peguam` (lawyer, external area) · `awam` (public citizen).

**Tenancy:** Branch ("cawangan") isolation via a global `CawanganScope` on `Form` and `KhidmatNasihat`. Legacy tables key branch by NAME string (`forms.cawangan`); newer tables by numeric `cawangan_id`.

**Request flow:** Controller → validate (FormRequest / inline) → Service (`app/Support`, wraps `DB::transaction` + `lockForUpdate` + `Audit::log`) → Eloquent (auto branch-scoped) → Blade / Excel / dompdf.

| Module | Purpose |
|---|---|
| Rekod Kes (`forms` spine) | Litigation case records |
| Peguam Panel | Lawyer registration + 3-tier admission + lifecycle |
| Agihan / Agihan Luar | Internal 3-tier case assignment + external lawyer grab/assign |
| Khidmat Nasihat (KN) | Legal advice + appointment slots |
| Pengantaraan | Mediation |
| Lejar Tuntutan | Payment-claim ledger + approval |
| Pemindahan Cawangan | Branch transfer (dual-branch visibility) |
| Statistik / Laporan / KPI | Reporting + exports |
| Awam portal | Citizen self-service (apply, upload, reschedule, status) |
| Chatbot / OCR | External Python microservice seams (chatbot live, OCR spike/off) |

**Overall shape:** Well-structured legacy port; complexity is ~85% essential. Service-layer transaction/lock discipline is genuinely strong, and the prior consolidation audit's CRITICALs are fixed. Residual risk concentrates in **access control (IDOR + over-broad route gating), one stored XSS, a latent seeded-admin backdoor advertised on the login page, unwired queue/cron on the shared host, and logging/audit blind spots** — not in the architecture.

---

## 1. Severity Legend

| Level | Meaning |
|---|---|
| **P0 — Critical** | Immediate security breach, major data loss, or complete feature/system failure. Block release. |
| **P1 — High** | Serious vulnerability, privilege escalation, data-integrity risk, or major process failure. Fix before production. |
| **P2 — Medium** | Important maintainability, performance, reliability, or usability issue. |
| **P3 — Low** | Minor improvement, cleanup, or standards issue. |

ID prefixes: `AUTH` · `INJ` · `CFG` (config/secrets/devops) · `DB` · `PERF` · `PROC` (process/reliability) · `UX` · `LOG` (logging/audit) · `CODE` · `ARCH` · `TEST`.

---

## 2. P0 — Critical (7)

### AUTH-01 — IDOR: any staff can download any branch's case attachments — CONFIRMED
- **File / line:** `app/Http/Controllers/LampiranController.php:47-53`; route `routes/web.php:203`
- **Description:** `download(UploadedFile $lampiran)` resolves the file purely by numeric ID and streams it with **no** `id_kes`/branch/ownership check. The sibling `destroy()` (line 57) *does* check `id_kes === kes->id`; `download()` does not, and its route isn't nested under a `{kes}`. `UploadedFile` has no `CawanganScope`.
- **Evidence:** `return Storage::disk($disk)->download($lampiran->file_path, $lampiran->nama);` under only `auth`+`permission:system.view`.
- **Risk:** Confidential legal documents (IC copies, court filings, waiver proofs, KN attachments) from every branch are readable by any authenticated staff user.
- **Exploit:** `pembantu_tadbir` requests `GET /lampiran/1/muat-turun`, `/2/…` and harvests attachments.
- **Fix:** Load the owning case/KN via `lampiran->id_kes`/`id_khidmat` and reuse the `assertBranchAccess()` pattern before streaming.
- **Effort:** S · **Test:** Y

### AUTH-02 — IDOR: cross-branch read + approve/pay of payment claims — CONFIRMED
- **File / line:** `app/Http/Controllers/LejarTuntutanController.php:46-141`; model `app/Models/LejarTuntutanBayaran.php`
- **Description:** `show/update/hantar/semak/lulus/tolak/bayar` bind `LejarTuntutanBayaran` by route-model-binding with **no branch/ownership check**. No `CawanganScope`, no policy. Only role-wide permissions gate the actions, held broadly across branches. `index()` *is* branch-scoped via the service, which makes the per-record gap the anomaly.
- **Evidence:** `public function show(LejarTuntutanBayaran $tuntutan): View { $tuntutan->load(...); return view(...); }` — no authorization.
- **Risk:** Cross-branch exposure and **tampering of financial claims** (read any claim, approve payout amounts, mark paid with a fabricated receipt).
- **Exploit:** Officer with `tuntutan.bayar` walks `/lejar-tuntutan/2..500`, then `POST /lejar-tuntutan/{id}/bayar`.
- **Fix:** Add branch scoping/policy on `LejarTuntutanBayaran` (via `form.cawangan`/`khidmat_nasihat.cawangan_id`) enforced in every action.
- **Effort:** M · **Test:** Y

### INJ-01 — Stored XSS in case duplicate-check (`innerHTML`) — CONFIRMED
- **File / line:** `resources/views/kes/form.blade.php:369-370`; source `app/Http/Controllers/KesController.php:91-101`
- **Description:** The IC duplicate-check builds HTML by concatenating `r.nama`, `r.no_fail`, `r.status` (free-text `forms` columns) and assigns to `nokpDup.innerHTML` with no escaping.
- **Evidence:** `const rows = data.records.map(r => '• #' + r.id + ' — ' + r.nama + ' (' + r.no_fail + ', ' + r.status + ')').join('<br>'); nokpDup.innerHTML = '...' + rows;`
- **Risk:** Stored XSS in an authenticated staff session (session theft, act-as-victim).
- **Exploit:** Case created with `nama = <img src=x onerror=fetch('/x?c='+document.cookie)>`; any staffer typing that IC into the duplicate-check triggers it. Injection surface may be reachable via the public intake path.
- **Fix:** Build the list via `document.createElement` + `.textContent`, or HTML-escape each field.
- **Effort:** S · **Test:** Y

### DB-01 — Seeder plants a full-admin backdoor account (no environment guard) — CONFIRMED
- **File / line:** `database/seeders/DatabaseSeeder.php:17-26`, `TestUsersSeeder.php:34-56`, `DemoUserSeeder.php`
- **Description:** `DatabaseSeeder::run()` unconditionally calls `DemoUserSeeder` + `TestUsersSeeder`, creating 9 accounts including `admin@test.local` (`ROLE_ADMIN`, `is_active=true`, `must_change_password=false`) and `demo@example.com`, all with password `"password"`. No `app()->environment()` guard; "strip before production" is a comment only.
- **Evidence:** `User::updateOrCreate(['email'=>$u['email']], array_merge($u, ['password'=>Hash::make('password'),'is_active'=>true,'must_change_password'=>false])); $user->syncRoles([$u['role']]);`
- **Risk:** Any accidental `db:seed` / `migrate:fresh --seed` against production plants a known-password super-admin.
- **Exploit:** Operator seeds prod → `admin@test.local` / `password` = full control. Chains with **AUTH-03** (login page advertises these exact credentials). Corroborated by **[Codex AUD-001]** (its sole P0).
- **Fix:** `if (! app()->environment(['local','testing'])) { return; }` in both seeders; remove from the prod seed path.
- **Effort:** S · **Test:** Y

### CFG-01 — Queued report export never runs on shared host (no worker) — CONFIRMED
- **File / line:** `app/Jobs/ExportLaporanJob.php:22`, `app/Http/Controllers/LaporanController.php` (`eksportPukal`), `config/queue.php`, `.env` (`QUEUE_CONNECTION=database`), `deploy.sh`
- **Description:** `ExportLaporanJob implements ShouldQueue` and dispatches to the `database` queue, but no `queue:work`/Supervisor/cron worker is started in `deploy.sh` or documented in `DEPLOY.md`.
- **Evidence:** `deploy.sh` steps 1-8 run migrate/cache/build only; `grep queue:work` in deploy artifacts → 0 hits.
- **Risk:** Bulk report exports enqueue and **never complete**; `jobs` table grows unbounded; users wait forever.
- **Fix:** Switch this path to `dispatchSync`/`QUEUE_CONNECTION=sync` for shared hosting, or wire a cron-driven `queue:work --stop-when-empty`.
- **Effort:** M · **Test:** Y

### CFG-02 — Scheduled business-logic commands never fire (no `schedule:run` cron) — CONFIRMED in repo / POSSIBLE on server
- **File / line:** `routes/console.php:12-19`; `deploy.sh`; `DEPLOY.md`
- **Description:** Three scheduled commands exist — `agihan:lebih-masa` (daily, re-assign offers >7 days), `grab:tamat-luput` (daily, expire unclaimed KN grabs), `lampiran:bersih-retensi` (monthly, retention report) — with no wired `php artisan schedule:run` cron in the repo/deploy docs.
- **Evidence:** `Schedule::command('agihan:lebih-masa')->dailyAt('07:00')` etc.; `grep schedule:run` in deploy artifacts → 0 hits.
- **Risk:** Overtime re-assignment, grab expiry, and retention reporting silently never run — workflow/data drift with no error surfaced.
- **Failure scenario:** If a `schedule:run` cron was set directly in hPanel (outside the repo), this is moot — **verify on the server**. If absent, silent failure.
- **Fix:** Add `* * * * * cd <path> && php artisan schedule:run >> /dev/null 2>&1`; document in `DEPLOY.md`.
- **Effort:** S · **Test:** N

### AUTH-03 — Public one-click admin backdoor chain (demo login modal + seeded accounts) — CONFIRMED
- **File / line:** `resources/views/system/login.blade.php:141-329` (demo modal), `:361-369` (auto-fill JS) + **DB-01**
- **Description:** The login page renders an "Akaun Demo" modal — **not** wrapped in any `@env`/`@production` guard — hardcoding all 9 test emails (incl. `admin@test.local`), auto-filling password `password` via JS, and printing "Kata laluan untuk semua akaun: `password`".
- **Evidence:** `<button ... class="js-demo-login" data-email="admin@test.local">`; JS `passwordField.value = 'password'`; footer literal `<code>password</code>`.
- **Risk:** The public login page **advertises admin credentials**. With DB-01 (accounts seeded in prod), any anonymous visitor logs in as super-admin in one click.
- **Fix:** Wrap the demo block so it never renders in production (`@env('local')` / `@unless(app()->isProduction())`). Also fix DB-01.
- **Effort:** S · **Test:** Y

---

## 3. P1 — High (37)

### Access control & tenancy

| ID | File:Line | Description | Evidence | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|---|
| AUTH-04 | `app/Models/Scopes/CawanganScope.php:44,52-53` | Tenant scope **fails open**: skips filtering for non-`isStaff()` principals (lawyers) and no-ops when a staff branch name can't resolve to `cawangan.id`, returning **all branches** instead of denying. `[Codex AUD-005]` | `if ($needle === null) { return; }` | Cross-branch leak on any branch rename/typo/unseeded branch, or any non-staff query path. | Fail **closed** (`whereRaw('1=0')`) + log; migrate string join to `cawangan_id` FK. | M | Y |
| AUTH-05 | `routes/web.php:143,221,239,253,416,439,446,457` | Whole staff area gated by one umbrella `permission:system.view`; many list/report GET routes lack module permission (`oyd.index`, `laporan.index`, `statistik.index`, `agihan.senarai`, `tarikdiri.senarai`, `kemaskini-bidang.index`, `permohonan-peguam.index`). `kes.view` exists in the seeder but is never used in routes. `[Codex AUD-002/003]` | `Route::middleware(['auth','permission:system.view'])->group(...)` (400+ lines) | Narrow roles read unrelated cross-branch case data, statistics, victim IC, internal legal opinions. | Per-resource view permissions on GET routes; gate nav with `@can`. | M | Y |
| AUTH-06 | `PeguamController.php:434-438`; `Peguam/TuntutanController.php:131-135` | Lawyer case-ownership is a **display-name string match**, not FK/IC. | `abort_unless($profile && $kes->nama_pegawai_yang_dapat_kes === $profile->nama_peguam, 403, ...)` | Two lawyers with the same name can act on each other's cases (accept/reject offers, file reports, file claims). | Match on a stable FK (`peguam_panel.id`/`kp_peguam`). | M | Y |
| AUTH-07 | `PermohonanPeguamController.php:144-155`; route `web.php:462` | `tarikDiri` (withdraw a panel application → status `3`) has **no permission check** and **no from-state guard** (reachable from Lulus). `[Codex AUD-003]` | Method validates `sebabBatal`, then `update(['permohonan_status'=>'3'])` | Any staff withdraws any application; an already-promoted lawyer's app flips to withdrawn while login+panel rows persist. | Require an approval permission; guard `permohonan_status==='0'`. | S | Y |
| AUTH-08 | `SystemAuthController.php:28-58,65-79`; `PasswordResetController.php`; reset routes | Login throttle is IP-only (`throttle:10,1`), **no per-account lockout**; password policy is `min:8` only; password-reset routes are **not throttled**. `[Codex AUD-008]` | route `throttle:10,1`; `changePassword` rule `min:8`; reset routes lack `throttle` | Distributed credential-stuffing bounded only per IP; weak passwords accepted; reset endpoint brute-forceable. | Per-identifier RateLimiter + account lockout; `Password::defaults()` (min 12 + uncompromised); throttle reset routes. | S | Y |
| AUTH-09 | `app/Http/Controllers/LaporanController.php:122-128` (`muatTurunEksport`) | **Export-download IDOR** — streams any file under `exports/` by filename with only `permission:system.view`; not bound to the generating user, branch, or the export's baked-in branch filter. Low-entropy name `{type}-{Ymd-His}.xlsx`; the `abort_unless(exists)` 404/200 gives a brute-force oracle. `[Codex AUD-004]` (reinstates my Agent A's SEC-06). | `$path='exports/'.$fail; abort_unless(Storage::disk('local')->exists($path),404); return ...->download($path,$fail);` | Any staff downloads another user's/branch's bulk export — including all-branch exports generated by a `cawangan.view-all` user. Cross-branch PII exfiltration, untraceable (LOG-03). | Track exporter user_id + allowed branch filter in a DB row; check ownership or re-apply the branch filter at download time; use unguessable names. | S | Y |

### Injection / web security

| ID | File:Line | Description | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|
| INJ-02 | `SecurityHeaders.php:16-21` | No `Content-Security-Policy` and no `Strict-Transport-Security`. `[Codex AUD-015]` | No CSP means INJ-01's XSS runs with full privileges; no HSTS allows first-visit downgrade. Inline `<script>` heavy → CSP needs nonces. | Add nonce-based CSP + HSTS (HSTS after HTTPS enforcement confirmed). | M | Y |
| INJ-03 | `app/Exports/LaporanExport.php`, `WideExport.php`, `KesExport.php`, `PendaftaranKnExport.php` (+ report controllers' CSV) | **CSV / formula injection** — user-controlled fields (`nama`, addresses, remarks) exported to XLSX/CSV without neutralizing leading `= + - @` (tab/CR). `[Codex AUD-011]` | A crafted `nama` (e.g. `=cmd|'/c calc'!A1`) executes when a staffer opens the export in Excel — command execution / data exfiltration on the analyst's machine. | Prefix risky cells with `'` or use a sanitizing formatter; validate/escape on export. | S | Y |
| CFG-03 | `.env` (`SESSION_SECURE_COOKIE` absent); `config/session.php:172` | `SESSION_SECURE_COOKIE` unset → default falsy → cookie omits `Secure`. | Session cookie sendable over plain HTTP if edge HTTPS is incomplete → capture. | `SESSION_SECURE_COOKIE=true` in prod `.env`; verify headers. | S | Y |

### Config / DevOps / production readiness

| ID | File:Line | Description | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|
| CFG-04 | `deploy.sh:16-19,44` | First-run deploy copies `.env.example` (`APP_DEBUG=true`, `MAIL=log`, `LOG=debug`) then `config:cache`, baking dev defaults. `[Codex AUD-007]` | POSSIBLE prod debug-on (stack-trace disclosure), mail disabled, verbose logs. **Verify live `.env`.** | Ship `.env.production` template; make deploy refuse to cache when `APP_DEBUG=true`. | S | N |
| CFG-05 | `deploy.sh:33` vs webhook | `migrate --force` exists in `deploy.sh`, but the webhook runs only `git pull` + `composer install`; migrations need a manual SSH run. | Schema drift → "column not found" 500s after a webhook deploy. | Guarded post-deploy migrate or pending-migration startup check; document the SSH step. | M | N |
| CFG-06 | `.env` (`MAIL_MAILER`, `LOG_LEVEL`, `LOG_STACK`); `config/logging.php:61-66` | Prod-unsafe defaults from example: `MAIL_MAILER=log`, `LOG_LEVEL=debug`, `LOG_STACK=single` (unbounded). | No mail delivery; unbounded debug log fills shared-host disk; internals leaked. | Prod: `MAIL_MAILER=smtp`, `LOG_STACK=daily`, `LOG_LEVEL=warning`. | S | N |
| CFG-07 | `.env` (`BOT_API_USER`, `BOT_API_PASS`) | Live HF-Space bot creds in the on-disk `.env` (dev=prod reuse). `.env` gitignored + `.htaccess` blocks dotfiles → **not** leaked via git/web. | Dev-machine/backup compromise exposes prod bot creds. | Rotate the HF Space creds as a precaution; avoid dev/prod reuse. | S | N |

### Database & data integrity

| ID | File:Line | Description | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|
| DB-02 | `database/schema/legacy-domain.sql:183,230,231` (`forms.nilai_sumbangan`, `kos_oyd`, `kos_pihak_lawan`) | Money columns typed `int`, not `decimal(n,2)` — silent sen truncation. (Later tables correctly use `decimal`.) | Corruption of monetary values in the litigation spine. | Migrate to `decimal(12,2)`; confirm legacy unit before backfill. | M | Y |
| DB-03 | `app/Support/NoFailGenerator.php:112-123`; callers `KesController.php:76`, `KhidmatProsesService.php:246`, `PembelaanAwamController.php:88`; index `2026_06_29_000002_...php:37` | `forms.no_fail` generated via `ROW_NUMBER()` count with no row lock and only a **plain index** (sibling `no_pengantaraan` got a `unique()` backstop). | POSSIBLE duplicate legal file numbers under concurrent creation. | Add `unique` index on `forms.no_fail`; catch + retry. | S | Y |
| DB-04 | `app/Models/User.php:84-88` vs `2026_07_01_160002_add_user_link_to_peguam_panel.php` | `lawyerProfile()` still joins on string key `id_peguam_panel = peguam_panel.kp_peguam` despite a new numeric FK added because the DB has 115 duplicate IC groups. | Wrong lawyer profile resolved (or null) on duplicate IC. | Repoint `lawyerProfile()` to the `id_user` FK, fall back to string only when null. | S | Y |
| TEST-01 | `phpunit.xml:26-33`; `tests/TestCase.php` | Tests run against the **live MySQL dev DB** (defaults to `iguaman_2in1`); no `RefreshDatabase` in base case; manual delete-by-tag cleanup; seeder runs on the real DB each test. `[Codex AUD-020]` | Aborted test leaves orphans; **if ever pointed at prod, data loss**. | Dedicated `_test` DB + `RefreshDatabase`/`DatabaseTransactions`; hard-fail if `APP_ENV!=testing`. | L | — |
| TEST-02 | `.github/` (absent) | **No CI.** Nothing runs the 344 tests on push/PR; deploy is an ungated webhook auto-pull. | RBAC/scope-leak regressions reach prod undetected. | GitHub Actions: mysql + `composer test` + `pint --test` + `composer audit`; branch protection. | M | — |

### Business process & reliability

| ID | File:Line | Description | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|
| PROC-01 | `PermohonanPeguamController.php:119-142` | Lawyer-approval promote+provision **not** in a `DB::transaction`: status → panel create → user create → syncRoles → audit as separate writes. | Mid-failure → app "Lulus" with a panel row but no login; lawyer stranded. | Wrap the lulus branch in one `DB::transaction`. | S | Y |
| PROC-02 | `PembelaanAwamController.php:85-90` | Intake does 3 sequential writes with **no transaction**: `Form::create` → `update(no_fail)` → audit. | Failure after create leaves a register row with blank `no_fail` (orphan case). | Wrap create + no_fail in one transaction. | S | Y |
| PROC-03 | `app/Support/PengantaraanService.php:79-106` | Mediator assignment has **no from-guard** — overwrites an already-assigned mediator. | Double-click/second officer silently displaces the mediator; no audit of the displaced. | `abort_if(filled(current) && !$override)`; audit reassignment. | S | Y |
| PROC-04 | `app/Support/LejarTuntutanService.php:33-40` | Claim status `DITOLAK` is **terminal with no exit**. | Rejected claim stuck; officer must recreate, losing history. | Add `DITOLAK → DRAF` (rework) or allow re-hantar. | S | Y |
| PROC-05 | `app/Support/TransferCawanganService.php`; `routes/console.php` | Branch transfer has **no timeout/expiry/escalation**; a never-accepted `DIPINDAH` stays pending forever. | Transfers stuck; cases in limbo. | Scheduled aging alert / auto-return for stale `DIPINDAH`. | M | Y |
| PROC-06 | `Awam/PublicAuthController.php:34`; `PasswordResetController.php`; `AwamDaftarRequest.php:19` | Citizens auth by IC, email `nullable`, only reset is the email broker; awam login has no reset link. | Highest-volume user type has **no recovery** when email blank → lockout. | IC-based reset (staff-verified/OTP) or require email + expose reset link. | M | Y |
| PROC-07 | `AwamRescheduleRequest.php:17` vs `KhidmatNasihatService.php:31-40` | Reschedule validates only `after:today`, but `bookSlot` needs a real open slot (≥4 working days out) → in-window dates `abort 422`. | Citizen cannot reschedule to valid-looking dates. | Drive reschedule off `SlotAvailabilityService`. | M | Y |
| PROC-08 | `KhidmatNasihatController::mapInput`; `Awam/PortalController.php:14` | Staff-created KN never sets `id_pengguna`; portal lists `where('id_pengguna', auth id)`. | Citizens can't see advice created on their behalf; no nokp backfill. | Backfill `id_pengguna` on register/login by matching `id_pengenalan_mangsa = nokp`. | M | Y |
| PROC-09 | `PermohonanPeguamController.php:128-129,200-228` | Temp password shown once in a flash banner, **no email**. `[Codex AUD-006]` | Missed banner → user row exists but bcrypt credential unrecoverable → lockout. | Email a credential / activation-reset link on provision. | M | Y |
| PROC-10 | `app/Support/TarikDiriService.php:74-126` (`kpKeputusan`) | KP approval of case-withdrawal generates **no cancellation-letter PDF and sends no email** (legacy did). | Loss of an official legal document + notification. | Port the "Surat Batal Penugasan" PDF + delivery. | M | Y |

### Frontend / UX (PII exposure & irreversible actions)

| ID | File:Line | Description | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|
| UX-01 | `laporan-kn/pendaftaran.blade.php:43` | Victim IC (`id_pengenalan_mangsa`) per-row in an Excel-exportable report. | Bulk NRIC export of legal-aid victims (PDPA). | Mask on screen; gate full value behind stricter export permission. | M | N |
| UX-02 | `laporan-kn/pandangan-uu.blade.php:45` | Internal officer legal opinion (`ulasan_pegawai`) per application in a list/export. | Internal legal advice broadly exposed (compounded by AUTH-05). | Gate column by permission or drop from list. | M | N |
| UX-03 | `pembelaan-awam/index.blade.php:53`; `oyd/index.blade.php:40,45`; `kes/index.blade.php:71`; `peguam/kes.blade.php:32`; `pengantaraan/index.blade.php:36`; `permohonan-peguam/index.blade.php:43` | NRIC (and phone in OYD) shown in browsable/searchable list rows across modules. | Bulk PII surfaces; IC in query string → access logs. | Mask (last 4) or detail-only; name-based search. | M | N |
| UX-04 | `pengguna/index.blade.php:55`; `peguam-panel/show.blade.php:20`, `_butiran.blade.php:45` | Full email columns; full bank account + IC + email in lawyer profile, cleartext. | PII/financial-PII harvest. | Mask account (last 4); email to detail; confirm role gating. | M | N |
| UX-05 | `agihan/maklumat.blade.php:59,69-110,158-195`; `kes/show.blade.php:194-199`; `permohonan-peguam/show.blade.php:92-101`; `kemaskini-bidang/index.blade.php:59-62` | Cluster of irreversible actions with **no confirm**: Batalkan Agihan, Pengarah/KP final decisions, Luluskan Permohonan, panel admission, Sokong/Tolak/Luluskan (Enter auto-submits). | One misclick/Enter irreversibly advances/cancels/approves. | Add confirmations; set `type="submit"` on approval buttons. | S | N |

### Logging & audit trail

| ID | File:Line | Description | Risk / Failure | Fix | Eff | Test |
|---|---|---|---|---|---|---|
| LOG-01 | `SystemAuthController.php:46-57`; `Awam/PublicAuthController.php:58-85` | **No login-success, failed-login, or lockout logging.** `[Codex AUD-014]` | No forensic trail for brute force/compromise; can't answer "who logged in / failed". | Log success + failure (identifier + IP + UA, never password) to a security channel. | M | Y |
| LOG-02 | `bootstrap/app.php:37-53` | Permission-denied (`UnauthorizedException`) handler logs nothing. `[Codex AUD-014]` | No trail of privilege-violation attempts (what AUTH-05 would generate). | Log denied access (user, route, permission, IP). | S | Y |
| LOG-03 | export/download controllers: `StatistikController:29,37`, `LaporanController:52,69,122`, `LaporanPenuhController:21`, `LaporanKhidmatNasihatController:49,66`, `LejarTuntutanController:149`, `LampiranController:47`, `Awam/PermohonanController:139` | **No export or document download is audited.** | Bulk PII/financial exfiltration (UX-01/03/04, AUTH-09) untraceable — serious for PDPA. | `Audit::log` on every export/download with type + filters + row count. | M | Y |
| LOG-04 | ~13 catch blocks: `KesController:166`, `AgihanLuarController:86,99`, `PemindahanController:43,60`, `PengantaraanController:102`, `PeguamController:267`, `KhidmatProsesController:115,128`, `LejarTuntutanController:172`, `KhidmatNasihatController:108` | Exceptions caught → `back()->with('error', ...)` but **never logged** (only ChatbotController logs). | Production errors invisible; cannot diagnose failures (silent failure). | Add `report($e)`/`Log::error($e)` in each catch. | M | N |

---

## 4. P2 — Medium (28)

### Architecture & code quality

| ID | File:Line | Description | Risk | Fix | Eff |
|---|---|---|---|---|---|
| ARCH-01 | `KhidmatNasihatController.php` (464 L, 18 methods) | God controller: CRUD + saringan + pindah + rekodBayaran + attachment + input mapping. | Hard to test/change. | Extract `KnFormMapper` + move attachment/waiver into a service. | M |
| ARCH-02 | `KesController.php` (192 L) | Litigation core has **no service** while every sibling domain does; create/update/transfer inline. | Inconsistent layering; logic in transport. | Extract `KesService`. | M |
| ARCH-03 | `StatistikController` · `LaporanController` · `LaporanPenuhController` · `KpiController` | Same `forms` filter builder re-implemented 4×. | Filter drift. | Consolidate into `LaporanRegistry`/`FormsReportQuery`. | M |
| ARCH-04 | statistik controllers | 23-branch list + `BULAN` + `year()/month()` helpers duplicated. | Drift risk. | Centralize into a value class + `ResolvesPeriodFilters` trait. | S |
| CODE-01 | `KeputusanController.php:126-147,81-106`; `KesKnSyncService.php:23-38` | `sahkanSelesai()`/`tutupFail()` do 3 independently-committed writes with no outer transaction; `pushToKn()` has none. | Partial-state drift between `forms`, ledger, KN. | Wrap the sequence in one transaction; add one inside `pushToKn()`. | S |
| CODE-02 | `PeguamLifecycleService.php:65-109` | `redistributeActiveCases()` issues ~5 queries per case in a loop (incl. per-row `MAX()`). | 250+ queries for a 50-case lawyer on deactivation. | Batch updates/inserts; compute next-kali once. | M |
| CODE-03 | `ButiranPeguamPanel.php` (v1) vs `ButiranPeguamPanel2..6` | v2-6 are a legitimate normalized split (bio/quals/firm/bank/spec), **not** duplicates. But v1 overlaps v2-6 facts with no visible sync; v1 read in 1 place + 1 view. | Two sources of truth can diverge. | Confirm if v1 is dead historical data; deprecate or document precedence. | M |
| CODE-04 | `PeguamController.php:106,228` (`'T'`/`'S'`); `KeputusanController.php:44-133` (`'Diterima'`/`'Fail Tutup'`) | Status magic strings with no constants (unlike `StatusAgihan`). | Typo silently breaks `where` matches. | Introduce `SejarahPeguamPanel::STATUS_*` and `FormStatus::*`. | S |
| CODE-05 | `PeguamController.php:351-404` (`updateProfil`) | Multi-model whitelist + fill/save inline in the controller. | Inconsistent; hard to unit test. | Extract `PeguamProfilUpdateService`. | M |
| CFG-09 | all models `protected $guarded = ['id']` (`Form.php:27`, `UploadedFile.php:15`, `KhidmatNasihat.php:19`); `User` uses `#[Fillable]` | Blanket mass-assignment surface; safe today (no controller uses `$request->all()`). `[Codex AUD-018]` | One future `Model::create($request->all())` allows attacker-set `status`/`is_active`/`role`. | Prefer explicit `$fillable` on sensitive models. | M |

### Database

| ID | File:Line | Description | Risk | Fix | Eff |
|---|---|---|---|---|---|
| DB-05 | `2026_06_30_000002_...php:26,76,98,112` | `butiran_peguam_panel_3/4/5/6` keyed by `kpBaru` string with index only (no unique/FK). | Duplicate/orphan profile rows; nondeterministic `first()`. | `unique('kpBaru')` on _3/_4/_5; composite unique on _6. | M |
| DB-06 | repo-wide (no `SoftDeletes`); `KategoriKnController.php:85-94`; `UserController.php:99-113` | All deletes are hard deletes. KN category cascade destroys children with only parent-level audit; user delete `nullOnDelete` detaches officer accountability. | Data loss; lost accountability; no per-child audit. | `SoftDeletes` on ref-tree + `User`; deactivate not delete; log cascade children. | M |
| DB-07 | `2026_06_30_100001_...` (`forms.cawangan` varchar, no FK) | Deliberate dual-keying: legacy branch-by-name-string with no FK; nothing enforces `forms.cawangan` matches a real branch (root of AUTH-04). | String/id drift → scope bypass. | Integrity-check command; long-term `forms.cawangan_id` shadow column. | L |
| DB-08 | `legacy-domain.sql:289-311` | `mahkamah_sivil`/`mahkamah_syariah` byte-identical; `khidmat_nasihat.id_mahkamah` resolves the table in app code → no FK possible. `[Codex AUD-017]` | No referential integrity; silent dangling refs. | Merge into one `mahkamah` table with `jenis` enum + FK. | L |
| DB-09 | `2026_06_30_000004_...:20-21` vs `legacy-domain.sql:419` | Inconsistent FK enforcement: `sejarah_pegawai`/`sejarah_sidang` RESTRICT case deletion; `sejarah_ppuu.id_kes` has no FK. `[Codex AUD-017]` | Orphaned `sejarah_ppuu` if a case is deleted. | Add `restrictOnDelete()`/`nullOnDelete()` FK to match siblings. | S |
| DB-10 | `SlotTemuJanji.php`; `temu_janji` migration | No DB unique on `temu_janji.slot_temu_janji_id` nor `(cawangan_id,bilik_id,tarikh_slot,masa_mula)`; double-booking prevented only by app `lockForUpdate`. `[Codex AUD-009]` | POSSIBLE double-booking via any future non-locked path. | Add the unique indexes as a DB backstop. | M |

### Performance

| ID | File:Line | Description | Risk | Fix | Eff |
|---|---|---|---|---|---|
| PERF-01 | `LaporanController.php:56`, `LaporanPenuhController.php:27`, `KesilapanController.php:46`, `StatistikSlaController.php:89`, `ExportLaporanJob.php:44` | 5 export paths `->get()` on `forms` with no `chunk()`/`cursor()`/cap; 3 run synchronously in the request. `[Codex AUD-012]` | Large exports exceed memory_limit / timeout → blank/500. | Stream via `cursor()`; `FromQuery` for Excel. | M |
| PERF-02 | `AgihanSpineController.php:66`; `PeguamShortlistService.php:54-78` | Busiest case-assignment screen loads the whole active lawyer roster twice + a full `GROUP BY`; ranking/limit in PHP. | 2 full-table loads + aggregate per view; scales badly. | Cache lawyer list; rank/limit in SQL. | M |
| PERF-03 | `StatistikController.php:48-104` | Dashboard runs 13 aggregate queries/request; **zero** `Cache::` app-wide. | Repeated full `forms` scans; first page to slow. | `Cache::remember` keyed by filters (short TTL). | S |
| PERF-04 | `SlaMatrix.php:129-150`; `KpiController.php:100-139` | `SUM(CASE WHEN DATEDIFF(...))` + `GROUP BY` on unindexed `tarikh_*`; uncached; 10 dashboard variants. | Full table scans on every SLA/KPI view. | Composite indexes on filtered date pairs + cache. | M |
| PERF-05 | `KesController.php:46-48,183-190`; `StatistikController.php:25` | `DISTINCT` scans of `forms` for dropdowns on nearly every page. | Repeated dedupe scans grow with table size. | Cache lists or source from `Cawangan`/`RefKes`. | S |
| PERF-06 | `.env` (`CACHE_STORE=database`) | Cache store is MySQL — every cache op a DB round-trip (no Redis). | Caching fixes hit a low ceiling. | `file` cache on shared host, or Redis if available. | S |
| PERF-07 | `StatistikController.php:29-35` | `KesExport` `Excel::download` synchronous (unlike the W20 queued path). | Ties up a PHP-FPM worker for the export duration. | Cap size + warn, or queue once CFG-01 fixed. | S |

### Process / reliability (continued)

| ID | File:Line | Description | Risk | Fix | Eff |
|---|---|---|---|---|---|
| PROC-11 | `app/Mail/*` (`AgihanTransisiMail`, `KesDitawarkanMail`, `KesLebihMasaMail`, `PemindahanMasukMail`) | None `implements ShouldQueue`; all mail sends inline. | Slow/timing-out SMTP stalls every spine action/cron × recipients. | Add `ShouldQueue`/`->queue()` + run the worker. | S |
| PROC-12 | `KeputusanController.php:81-106,34,60` | `tutupFail`/`lulus`/`tolak` gate on permission only, **no from-status guard**; `Form` mass-assignable. `[Codex AUD-019]` | A never-approved case can be closed (order enforced only by button visibility). | Guard `tutupFail` requires `status==='Diterima'`; `lulus/tolak` require blank. | S |
| PROC-13 | `PengantaraanController` + `PengantaraanRequest` (`status_pengantaraan`, `status_sidang`) | Mediation status fields free-text; stats filter exact strings. | A typo silently drops the row from every KPI; `status_sidang` only writes `Tangguh`. | `Rule::in([...])` + status constants. | M |
| PROC-14 | `Peguam/TuntutanController.php:45-86` | Lawyer claim `store` has no idempotency for non-KN rows (unique key null). `[Codex AUD-019]` | Double-submit → two parallel DIHANTAR claims. | Dedupe on (id_kes,kp_peguam,open-status) or PRG + submit token. | M |
| PROC-15 | `KhidmatNasihatController::store:196`; `Awam/PermohonanController::store:74` | No idempotency token on KN create; double-click → 2 KN (2nd slot 422 → orphan). `[Codex AUD-019]` | Duplicate applications. | Per-form idempotency nonce. | M |
| PROC-16 | `app/Support/PerakuanService.php:44-60` | `muktamadkan` self-heals a missing cert number instead of failing. | Masks an impossible INTERIM-with-no-number state. | Throw on INTERIM with null no_perakuan. | S |
| PROC-17 | `TarikDiriController` + `KemaskiniBidangController` routes (`web.php:441-448`) | Approvals gated by broad `role:` only — no branch/assignment ownership. | Any Pengarah of any branch approves any lawyer's withdrawal/bidang. | Add branch/track ownership check. | M |
| PROC-18 | `routes/console.php:12-19`; `LebihMasaService.php:50-62` | Scheduled commands have no `onFailure`/monitoring; loops have no per-row try/catch. | Silent cron failure; one bad row aborts the whole run. | `->onFailure()` alert + per-row try/catch + report. | S |
| PROC-19 | `app/Jobs/ExportLaporanJob.php` | No `$tries`, no `failed()` handler, no backoff. | Silent job failure; download waits on a file that never appears. | Add `failed()` surfacing failure; set `$tries`/backoff. | S |
| PROC-20 | `PermohonanPeguamController.php:104-142,83-101` | No terminal-state guard on `keputusan`/`sokong` (only checks prior tier's flag). `[Codex AUD-019]` | Re-POST re-approves (double `Audit::APPROVE`); approve-after-reject possible. | `abort_unless($butiran->permohonan_status==='0')`; guard sokong. | S |
| PROC-21 | `PeguamController.php:85-125` (`terima`/`tolak`) | Lawyer accept/reject checks ownership only, no `ensureStatus` from-guard. | A lawyer can "accept" a case no longer at offer state. | Assert `normalise(status_agihan)===DITAWARKAN` before write. | S |

### Logging (continued) & config

| ID | File:Line | Description | Risk | Fix | Eff |
|---|---|---|---|---|---|
| LOG-05 | `app/Support/Audit.php:20-33` | `Audit::log` hard-codes `field_name/old_value/new_value = null` — remark-only, no diff (even for role-permission and user edits). | Can't reconstruct what changed. | Add an overload capturing field/old/new for sensitive updates. | M |
| LOG-06 | `app/Support/Audit.php:30` | `modified_by` stores the display **name**, not user id. | Ambiguous actor attribution (names collide/change). | Store user id (+name). | S |
| LOG-07 | `app/Support/Audit.php`; `OydController`, `UserController`, KN/peguam controllers passing PII into remark text | **PII stored as free text in `audit_trail` remarks** (names, IC numbers, emails) — an unmasked secondary copy of personal data in a long-lived, broadly-readable log. `[Codex AUD-013]` | PDPA exposure; `audit.view` (admin) surfaces IC/emails; grows unbounded (DB-11). | Log identifiers/ids, not raw PII, in remarks; mask IC; restrict `audit.view`. | M |
| CFG-08 | `app/Console/Commands/ImportLegacyData.php:93,118,130,152` | `--source` option interpolated raw into backtick-quoted table/DB identifiers. | SQL injection via identifier if ever web-triggered (CLI-only today). | Whitelist-validate `--source` against `^[A-Za-z0-9_]+$`. | S |
| CFG-10 | `.env` (`APP_DEBUG=true`, `APP_ENV=local`) — local only | Local debug on (expected); flagged for prod risk via CFG-04. POSSIBLE in prod. | Stack-trace/query/credential leak on 500 if left on. | Verify `APP_ENV=production`, `APP_DEBUG=false` on server; add deploy guard. | S |
| TEST-03 | `ExportLaporanJob.php` (no test); `SystemAuthController` (no throttle test); `app/Console/Commands/*` (thin) | Zero tests for queued export, login throttle/lockout (429), scheduled command negative/idempotency paths (`--purge`, luput boundaries). | Regressions ship undetected. | `Queue::fake` dispatch test, 429 test, command boundary tests. | M |

### Frontend / UX (continued)

| ID | File:Line | Description | Risk | Fix | Eff |
|---|---|---|---|---|---|
| UX-06 | `awam/permohonan/show.blade.php:89-110`; `peguam-panel/show.blade.php:60-63`; `khidmat-nasihat/partials/proses-actions.blade.php:37-41`; `lejar-tuntutan/show.blade.php:56-60` | Reschedule / reactivate / mark-no-show / reject-claim lack confirmation (inconsistent with siblings). | Accidental state changes. | Add confirmations (echo the amount for claim rejection). | S |
| UX-07 | `kes/mahkamah.blade.php:29-157`; `kes/pengantaraan.blade.php:31-98`; `pengantaraan/create.blade.php`; `lejar-tuntutan/borang.blade.php` | Primary update forms lack `@error`/`$errors->any()` display. | Silent validation failures confuse users. | Add summary + per-field `@error`. | M |
| UX-08 | `oyd/form.blade.php`, `khidmat-nasihat/form.blade.php`, `pengantaraan/create.blade.php`, `slot/index.blade.php`; filter/upload inputs across list views | Systemic a11y gap: detached `<label>` (no `for=`/`id`); filter/search/upload inputs placeholder-only. | Screen readers don't announce fields (WCAG 1.3.1/4.1.2). | Add `for=`/`id` pairs + `aria-label`. | L |
| UX-09 | wide tables in `keputusan/selesai`, `agihan/senarai`, `agihan-luar/index`, `pemindahan/index`, `statistik-pemindahan/index`; `.tap-table` fixed-px grids in `audit/index`, `cuti/index`, `mahkamah-ref/index`, `ref-kes/index` | Wide/fixed-px tables not wrapped in `overflow-x:auto`. | Horizontal overflow / clipped buttons on mobile. | Wrap in overflow-x scroll containers. | S |

---

## 5. P3 — Low (20)

| ID | File:Line | Description | Fix | Eff |
|---|---|---|---|---|
| ARCH-05 | `OcrPrefillController` + `OcrPrefillService` + `config/ocr.php` + route | Dead/spike feature, flag OFF, `extract` returns 503. | Delete if not roadmapped (see DEAD_CODE). | S |
| ARCH-06 | `ChatbotController.php:16-90` | Proxy token+forward inline; breaks the "wrap SDKs in adapters" convention; 74-line method. | Extract a `ChatbotClient` adapter + helpers. | S |
| ARCH-07 | Events/Jobs usage | Only 1 event/listener; notifications mostly inline — no single strategy. | Pick events-vs-inline and apply uniformly. | M |
| ARCH-08 | `app/Support` (32 flat classes, mixed suffixes) | Services/matrices/exports/value-objects in one dir. | Optional: namespace into `Services/`, `Reporting/`, value subfolders. | S |
| CODE-06 | `StatistikPengantaraanController.php:89-94`; `StatistikSlaController.php:124-136` | Identical `year()` helper duplicated. | Extract to trait/helper. | S |
| CODE-07 | `PeguamController.php:351-404`; `ChatbotController::ask` | Functions >50 lines. | Split into focused helpers. | S |
| CODE-08 | repo-wide | `pint --test` fails on 55 files; no PHPStan/Psalm. | Run `pint`; add PHPStan level 5-6 to CI. | S |
| DB-11 | `legacy-domain.sql:15-27` (`audit_trail`) | `int` PK, append-only, no pruning/archival; grows unbounded (compounds LOG-07). | Scheduled archival/partition + `bigint` PK. | M |
| DB-12 | `2026_06_30_000002_...:119` | `butiran_peguam_panel_6.modifiedDate` typed `string`, not `dateTime`. | Alter to `dateTime`; backfill first. | S |
| PROC-22 | `PermohonanPeguamController` (semak/sokong); `PengkhususanService`; `PublicAuthController`; `Awam/PermohonanController::download`; `LejarTuntutanController::update` | Sensitive actions with no `Audit::log` (endorsements, bidang add/drop incl. deletes, register/login/logout, downloads, claim-amount edits). | Add audit entries. | S |
| PROC-23 | `PeguamPanelController.php:79-84`; `PeguamLifecycleService::reactivate` | `aktifSemula` re-enables login unconditionally, incl. lawyers deactivated for death. | Block/warn on death reactivation; surface redistribution. | S |
| PERF-08 | `SlotAvailabilityService::holidayDates()` + `SlotGenerator::holidayDates()` | Both `RefCuti::all()` uncached per call. | Cache holiday list per request. | S |
| UX-10 | `layouts/peguam.blade.php:20` | Dead ternary `routeIs(...) ? '' : ''` — lawyer topbar never shows active tab. | Add real active-state class. | S |
| UX-11 | `cetakan/ringkasan.blade.php:47-49` | Monetary fields printed raw (no `number_format`, no RM). | Apply `number_format` + RM. | S |
| UX-12 | `statistik/index` SVGs, `kpi/partials/chart.blade.php`; icon-only links (`cuti/index:61`, `ref-kes/index:49`, `khidmat-nasihat/show:64`) | Charts lack `role="img"`/`<title>`; icon-only links lack accessible names. | Add text alternatives + `aria-label`. | M |
| UX-13 | `mail/agihan-transisi.blade.php:8` | Raw `{!! $perenggan !!}` in email body — POSSIBLE XSS if `$mesej` isn't 100% static. | Confirm content is system-static; else `{{ }}`. | S |
| CFG-11 | `composer.json:19` (`laravel/pao ^1.0.6`) | Low-footprint dev dependency; verify provenance. **`composer audit` was run (Codex) → no known vulnerabilities**; several deps have newer patch/minor versions. `[Codex AUD-021]` | Supply-chain hygiene; keep `composer audit` in CI. | Provenance check; bump patch/minors. | S |
| CFG-12 | repo settings (unknown) | Branch protection / secret scanning status unverified; direct commits to `main` auto-deploy. | Enable branch protection + secret scanning/push protection. | S |
| CFG-13 | `app/Http/Controllers/ChatbotController.php:16-90`; `config/services.php`; `.env` | Chatbot proxy forwards **citizen input + username** to an external HF Space (URL is config-controlled, so not SSRF) with no consent/notice. `[Codex AUD-016]` | Privacy/PDPA: personal input sent to a third-party endpoint; no user notice. | Add a privacy notice/consent; minimize forwarded fields; document the data flow. | S |
| CFG-14 | `routes/web.php`; `.htaccess` (recent carve-out for `docs/(penambahbaikan-22\|system-overview)\.html`) | Two internal documentation pages are **deliberately public**. `[Codex AUD-022]` | Minor info exposure if the docs contain internal detail. | Confirm the docs are non-sensitive; otherwise gate behind auth. | S |

---

## 6. Positive Controls Observed (verified — no finding)

- **Awam portal ownership** — `Awam/PermohonanController` uses `Gate::authorize` via `KhidmatNasihatPolicy` (ownership by `id_pengguna`) on every citizen route; awam file download is properly scoped (not IDOR).
- **`KhidmatNasihatController::assertBranchAccess()`** — correct defense-in-depth on top of `CawanganScope` (checks `cawangan_id` + `cawangan_asal_id`). The pattern AUTH-01/02 should adopt.
- **Privilege-escalation guards** — `UserRequest` blocks non-admins minting/editing admin accounts; `RolePermissionController`/`RoleController` protect `urus.peranan` and system roles.
- **Session security** — regeneration on login, `invalidate()` + `regenerateToken()` on logout in both auth controllers.
- **CSRF** — Laravel default `VerifyCsrfToken` intact; no `$except`; no CSRF-bypassing API routes.
- **Transaction/lock discipline** — `AgihanService`, `AgihanLuarService`, `KhidmatProsesService`, `TransferCawanganService`, `TarikDiriService`, `LejarTuntutanService`, `PengantaraanService`, `PerakuanService`, `KhidmatNasihatService` consistently use `DB::transaction` + `lockForUpdate` + post-commit audit.
- **File uploads** — validated `mimes`/`max`, private disks, `basename()` storage name, streamed downloads (no path traversal).
- **No raw-SQL injection** in report builders — `whereRaw`/`selectRaw`/`DB::raw` fragments come from static config, never request input.
- **No `dd()`/`dump()`/`var_dump`/`eval`/`exec`/`unserialize`** in `app/`; no Telescope/Debugbar; no empty catch blocks; consistent `===`/`!==`.
- **`.env` gitignored**; only `.env.example` tracked (placeholders only). `.htaccess` blocks dotfiles + Laravel source dirs.
- **Dependencies** — `composer audit` and `npm audit` both run clean (no known vulnerabilities) — empirically confirmed by the Codex cross-audit `[Codex AUD-021]`.
- **Prior consolidation-audit CRITICALs FIXED** — spine-offer-invisible, status-9 orphan, KN no-show hang, rejected-appointment orphan, dual assignment front-ends, payment confirmation, public status lookup, awam-role protection (verified in current code).
- **Pagination** — `KesController`, `KhidmatNasihatController` (`with()` + `paginate(25)`), `PermohonanPeguamController`, `AgihanSpineController` paginate correctly with no N+1 in views. `SlotGenerator` batches inserts with a capped date range. `KesExport`/`LejarTuntutanExport`/`PandanganUuExport`/`PendaftaranKnExport` use chunked `FromQuery`.

---

## 7. Can any DB operation corrupt, duplicate, lose, or expose data?

**Yes — the following vectors are real:**

| Vector | Verdict | Finding |
|---|---|---|
| Exposure via seeded admin backdoor | CONFIRMED path (not confirmed executed) | DB-01 / AUTH-03 |
| Exposure via branch-scope fail-open | CONFIRMED code path | AUTH-04 |
| Exposure via IDOR (attachments, claims, exports) | CONFIRMED | AUTH-01, AUTH-02, AUTH-09 |
| Exposure of PII in list/export surfaces + audit remarks | CONFIRMED | UX-01/03/04, LOG-03, LOG-07 |
| Client-side exec via CSV/formula injection in exports | CONFIRMED | INJ-03 |
| Duplication of legal file numbers (`no_fail`) | POSSIBLE (race) | DB-03 |
| Duplication of appointments / claims / KN | POSSIBLE (idempotency gaps) | DB-10, PROC-14/15 |
| Loss of officer/audit linkage on user delete | CONFIRMED | DB-06 |
| Corruption via truncated money (`int` columns) | CONFIRMED schema defect | DB-02 |
| Wrong-data via stale `lawyerProfile()` join | CONFIRMED drift | DB-04 |
| Partial saves (non-atomic multi-write flows) | CONFIRMED | PROC-01, PROC-02, CODE-01 |

---

## 8. Assumptions, Limitations & Cross-Audit Notes

- **Production `.env` was not accessible** (correctly gitignored). All `APP_DEBUG`/`MAIL`/`QUEUE`/cron findings marked POSSIBLE must be verified on the Hostinger server. The local `.env` was read for key assessment only.
- **Hostinger cron/queue-worker config lives outside the repo.** CFG-01/CFG-02 are CONFIRMED as "not wired in the repo/deploy script"; whether a worker/`schedule:run` was set manually in hPanel is unverified — verify on the server.
- Findings are static-analysis based; timing side-channels (login enumeration) and true concurrency races (DB-03, DB-10) are POSSIBLE and need runtime confirmation.
- No production data was modified. No secret values are printed.

**Cross-audit (Codex) reconciliation:**
- This report is the union of a 9-agent audit (mine) and an independent Codex audit (22 findings, `AUD-xxx`). Codex's unique valid findings were merged: **AUTH-09** (export-download IDOR, AUD-004), **INJ-03** (CSV/formula injection, AUD-011), **LOG-07** (PII in audit remarks, AUD-013), **CFG-13** (chatbot data-forwarding privacy, AUD-016), **CFG-14** (public docs, AUD-022), and the empirical dependency result (CFG-11 / §6).
- **Codex empirical verification** (recorded in `AUDIT_CODEX_RECOVERED.md`): `composer audit` + `npm audit` clean; unit tests 41/41; `Phase1RbacHardeningTest` 8/8; the full feature suite **timed out at 120s** (a test-readiness limitation, corroborates TEST-01/03 — the live-MySQL suite is slow/heavy).
- **Codex-raised, assessed low and NOT adopted as a finding:** "future-dated migrations" (Codex AUD-010) — migrations are dated `2026_06_29`…`2026_07_01`, i.e. ~the audit date, not genuinely future; no release/rollback risk in practice.
- **Codex under-triaged severity:** it rated only the seeded-credential issue P0 and did **not** identify the stored XSS (INJ-01), the two record-level IDORs (AUTH-01/02), or the queue/cron ops-break (CFG-01/02) — the 5 additional P0s in this report. Those were hand-verified against source here.
- Codex's original 4 `AUD-xxx` report files were overwritten during the first audit run; their content is preserved in `AUDIT_CODEX_RECOVERED.md` (reconstructed from the Codex session log).

**Totals:** P0 = 7 · P1 = 37 · P2 = 28 · P3 = 20 · **Total = 92.**
