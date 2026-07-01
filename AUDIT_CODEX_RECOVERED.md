# AUDIT — CODEX (RECOVERED)

Preserved record of an **independent Codex audit** of iGuaman 2in1 that ran on 2026-07-01 (session started 16:39). Codex wrote its own `SYSTEM_AUDIT_*.md` files (`AUD-xxx` numbering, 22 findings), which were **overwritten during a subsequent 9-agent audit run** before the two were reconciled. This file reconstructs Codex's findings so its work is not lost, and records what was merged into the canonical reports.

- **Source session log:** `C:\Users\User\.codex\sessions\2026\07\01\rollout-2026-07-01T16-39-59-019f1cd5-afad-7cc2-8e39-50c5f207dc48.jsonl`
- **Findings reconstructed from:** the Codex `SYSTEM_AUDIT_REPORT.md` content embedded in that session log.
- **Canonical (merged) reports:** `SYSTEM_AUDIT_REPORT.md`, `SYSTEM_AUDIT_SUMMARY.md`, `REMEDIATION_PLAN.md`, `DEAD_CODE_AND_CLEANUP.md`.

---

## Codex's final chat comment (verbatim)

> Completed the audit deliverables: SYSTEM_AUDIT_REPORT.md, SYSTEM_AUDIT_SUMMARY.md, REMEDIATION_PLAN.md, DEAD_CODE_AND_CLEANUP.md.
>
> **Verdict: Not ready for production.**
>
> Key verification: `composer audit` and production `npm audit` found no known vulnerabilities; unit tests passed `41/41`; `Phase1RbacHardeningTest` passed `8/8`. The full feature suite timed out after 120s, so I recorded that as a test-readiness limitation.
>
> Note: `git status` also shows an unrelated `.gitignore` modification adding `.gstack/`; I did not touch or revert it.

**Codex scores (from its SYSTEM_AUDIT_SUMMARY):** Overall 58 · Security 52 · Code quality 66 · Architecture 61 · Database 55 · Performance 60 · Reliability 56 · Test-readiness 62 · Production-readiness 45. **Counts:** P0=1, P1=9, P2=10, P3=2 (Total 22).

---

## Codex's 22 findings (reconstructed) → mapping to the merged report

| Codex ID | Sev | Area / file | Finding | Merged report ID | Status |
|---|---|---|---|---|---|
| AUD-001 | P0 | seeders + `login.blade.php` | Demo/test users w/ shared password `password`; login page exposes their emails | DB-01 + AUTH-03 | **Agree** (both P0) |
| AUD-002 | P1 | `routes/web.php`, `PermohonanRequest`, controllers | Case create/edit only `system.view`; requests accept lifecycle fields | AUTH-05 + PROC-12 + CFG-09 | Agree |
| AUD-003 | P1 | `routes/web.php`, `PeguamPanelController` | Panel-lawyer edit/update + application withdrawal inherit only `system.view` | AUTH-07 + AUTH-05 | Agree |
| AUD-004 | P1 | `LaporanController`, `ExportLaporanJob` | Bulk export downloads predictable + not bound to user/branch/permission | **AUTH-09** | **Merged in** (was dropped from my draft) |
| AUD-005 | P1 | `CawanganScope.php` | Branch isolation fails open when branch name can't resolve | AUTH-04 | Agree |
| AUD-006 | P1 | `PermohonanPeguamController` | Temp lawyer passwords returned in a browser flash message | PROC-09 | Agree |
| AUD-007 | P1 | `.env.example`, `DEPLOY.md`, `deploy.sh` | Prod `.env` copied from dev template (local/debug/log mail) | CFG-04/06/10 | Agree |
| AUD-008 | P1 | `PasswordResetController`, routes | Reset endpoints not throttled; password policy only `min:8` | AUTH-08 | Agree |
| AUD-009 | P1 | `temu_janji`/`slot` migration | Appointment slots rely on app-level idempotency; no DB uniqueness | DB-10 | Agree |
| AUD-010 | P1 | `database/migrations` | Future-dated migrations relative to audit date | — | **Not adopted** (dates ≈ audit date; no real risk) |
| AUD-011 | P2 | `LaporanController`, `LaporanExport` | CSV/formula injection — user fields exported without neutralizing prefixes | **INJ-03** | **Merged in** (was absent from my report) |
| AUD-012 | P2 | report controllers | Exports load full datasets with `get()` (no streaming) | PERF-01 | Agree |
| AUD-013 | P2 | `Audit.php`, `OydController`, controllers | Audit remarks store names/IC/emails as free text | **LOG-07** | **Merged in** |
| AUD-014 | P2 | auth controllers, `bootstrap/app.php` | Failed login / captcha fail / permission-denial not logged | LOG-01 + LOG-02 | Agree (I rated P1) |
| AUD-015 | P2 | `SecurityHeaders.php` | No CSP / HSTS | INJ-02 | Agree (I rated P1) |
| AUD-016 | P2 | `ChatbotController`, `config/services.php` | Chatbot proxy configurable URL forwards public input + username | **CFG-13** | **Merged in** (privacy angle; I'd assessed SSRF-safe) |
| AUD-017 | P2 | migrations | Several relationships not FK-enforced | DB-08 + DB-09 | Agree |
| AUD-018 | P2 | `Form.php`, `KhidmatNasihat.php` | Legacy tables broad/mutable with `$guarded=['id']` | CFG-09 | Agree |
| AUD-019 | P2 | `PermohonanPeguamController`, KN | Workflow transitions lack status guards / idempotency | PROC-12/14/15/20/21 | Agree (I enumerated specifics) |
| AUD-020 | P2 | `phpunit.xml`, suite exec | Full feature suite didn't complete; tests depend on live MySQL | TEST-01 + TEST-03 | Agree |
| AUD-021 | P3 | `composer.json`, `package.json` | Audits clean, but several deps have newer patch/minor versions | CFG-11 | Agree (empirical) |
| AUD-022 | P3 | `routes/web.php`, `.htaccess` | Two internal documentation pages deliberately public | **CFG-14** | **Merged in** |

**Merged from Codex into the canonical report:** AUD-004→AUTH-09, AUD-011→INJ-03, AUD-013→LOG-07, AUD-016→CFG-13, AUD-022→CFG-14, plus the empirical dependency-audit result (CFG-11 / §6 positives). AUD-010 was assessed and not adopted.

---

## What the merged (9-agent) audit added beyond Codex

Codex reported **1 P0**; the merged report has **7 P0** — the 5 Codex missed:

| Merged ID | Sev | Finding Codex did not surface |
|---|---|---|
| INJ-01 | P0 | Stored XSS in `kes/form.blade.php` (`innerHTML` of unescaped `forms` free-text) |
| AUTH-01 | P0 | IDOR: any staff downloads any branch's case **attachments** by ID (distinct from AUD-004's *export* files) |
| AUTH-02 | P0 | IDOR: cross-branch read + approve/pay of **payment claims** |
| CFG-01 | P0 | Queued export job never runs (no `queue:work` worker on shared host) |
| CFG-02 | P0 | Scheduled commands never fire (no `schedule:run` cron wired) |

Plus the reliability/process layer (PROC-01…10: non-atomic flows, terminal dead-ends, citizen lockout, reschedule dead-end, missing cancellation letter), the performance layer (PERF-02/03/04: N+1, zero caching, DATEDIFF scans), broad UX PII display + missing confirmations (UX-01…05), data-integrity defects (DB-02 int money, DB-03 `no_fail` race, DB-04 `lawyerProfile` drift, DB-06 soft deletes), name-string lawyer ownership (AUTH-06), and no CI (TEST-02).

---

## Independent verification (Codex, empirical — retained)

| Check | Result |
|---|---|
| `composer audit` | No known vulnerabilities |
| `npm audit` (production) | No known vulnerabilities |
| Unit tests | 41 / 41 pass |
| `Phase1RbacHardeningTest` | 8 / 8 pass |
| Full feature suite | Timed out at 120s (recorded as test-readiness limitation → corroborates TEST-01/03) |

**Verdict (both audits agree):** Not ready for production.
