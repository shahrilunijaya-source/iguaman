# SYSTEM AUDIT SUMMARY — iGuaman 2in1

**Audit date:** 2026-07-01 · **Commit:** `0438624` · **Method:** 9 cross-validated specialist passes, P0/key-P1 hand-verified against source, **merged with an independent Codex audit** (22 findings) — its unique valid findings and empirical checks folded in.

---

## 1. Scores (out of 100)

| Dimension | Score | Basis |
|---|---:|---|
| **Overall system health** | **56** | Strong build discipline undercut by exploitable access-control gaps + unwired ops |
| Security | 42 | 2 record IDORs + 1 export IDOR, stored XSS, CSV injection, seeded+advertised admin backdoor, no CSP, fail-open tenancy |
| Code quality | 80 | No dead debug code, no swallowed errors, consistent types; a few god controllers + magic strings |
| Architecture | 82 | ~85% essential complexity; clean layering; minor sprawl + one dead spike (OCR) |
| Database | 60 | Excellent transaction discipline; but int-money, `no_fail` race, no soft deletes, fail-open scope |
| Performance | 63 | Good pagination/chunked exports; zero caching, unbounded sync exports, N+1 on busiest screen |
| Reliability | 58 | Strong service-layer atomicity; but non-atomic outlier flows, terminal dead-ends, no cron/queue wiring |
| Test coverage readiness | 55 | 344 tests incl. negative cases; but no CI, tests hit live dev DB, suite times out at 120s |
| Production readiness | 38 | Queue/cron unwired, debug-on-prod risk, no CI, migration drift, logging/audit blind spots |

---

## 2. Issue Counts

| Severity | Count |
|---|---:|
| P0 — Critical | 7 |
| P1 — High | 37 |
| P2 — Medium | 28 |
| P3 — Low | 20 |
| **Total** | **92** |

(Union of the 9-agent audit + Codex cross-audit. Codex alone reported 22 findings / 1 P0; this audit adds 5 P0s Codex missed.)

---

## 3. Top 12 Risks

| # | Risk | ID(s) |
|---|---|---|
| 1 | **Public one-click admin backdoor** — login page advertises `admin@test.local` / `password`; seeder plants those accounts with no env guard. | AUTH-03, DB-01 |
| 2 | **IDOR: any staff downloads any branch's case attachments** (legal docs, IC copies) by ID. | AUTH-01 |
| 3 | **IDOR: cross-branch read + approve/pay of payment claims** (financial tampering). | AUTH-02 |
| 4 | **Stored XSS** in the case IC-duplicate check (`innerHTML`, no escaping) — runs in staff sessions. | INJ-01 |
| 5 | **Tenant isolation fails open** — unresolved branch / non-staff principals see all branches. | AUTH-04 |
| 6 | **Queue + scheduled jobs never run on the shared host** — exports hang, SLA/expiry/retention silently skip. | CFG-01, CFG-02 |
| 7 | **Export-download IDOR** — predictable, non-owner-bound export files (all-branch PII) brute-forceable via 404/200 oracle. | AUTH-09 |
| 8 | **Over-broad `system.view` gating** exposes cross-branch case data, victim IC + internal legal opinions to narrow roles. | AUTH-05, UX-01/02 |
| 9 | **CSV / formula injection** — user fields exported to XLSX without neutralizing `= + - @` → code exec on the analyst's machine. | INJ-03 |
| 10 | **No security logging** — no login/failed-login/permission-denied/export audit; PII stored in audit remarks (PDPA + forensics gap). | LOG-01/02/03/07 |
| 11 | **Tests run against the live dev DB with no CI** — a mis-pointed run risks data loss; regressions ship unguarded. | TEST-01, TEST-02 |
| 12 | **Data-integrity defects** — `int` money columns (truncation), `no_fail` race (dup file numbers), name-string lawyer ownership/join. | DB-02/03/04, AUTH-06 |

---

## 4. Quick Wins (high value, ≤ Small effort)

- Env-guard both seeders (`app()->environment(['local','testing'])`) — kills the admin backdoor. **[DB-01]**
- Wrap the demo-login modal in `@production`-off so it never renders in prod. **[AUTH-03]**
- Add the missing branch/ownership check to `LampiranController::download`. **[AUTH-01]**
- Escape the 3 fields in `kes/form.blade.php` duplicate-check (`textContent`). **[INJ-01]**
- Owner/branch-bind + unguessable-name export downloads. **[AUTH-09]**
- Neutralize CSV formula prefixes (`= + - @`) in all exports. **[INJ-03]**
- `CawanganScope`: fail **closed** on unresolved branch (`whereRaw('1=0')`). **[AUTH-04]**
- Set prod `.env`: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `MAIL=smtp`, `LOG_STACK=daily`, `LOG_LEVEL=warning`. **[CFG-03/04/06/10]**
- Wire Hostinger cron `schedule:run`; switch `ExportLaporanJob` to `dispatchSync` (or run a worker). **[CFG-01/02]**
- `unique` index on `forms.no_fail`; unique on slot tuple. **[DB-03/10]**
- Wrap `PermohonanPeguamController::keputusan` and `PembelaanAwamController::store` in `DB::transaction`. **[PROC-01/02]**
- Log login success/failure + permission-denied + exports; stop logging raw PII in remarks. **[LOG-01/02/03/07]**
- Throttle password-reset routes; strong password policy. **[AUTH-08]**
- Run `./vendor/bin/pint`; rotate the HF bot creds. **[CODE-08, CFG-07]**

---

## 5. Recommended Remediation Phases

| Phase | Theme | Key IDs |
|---|---|---|
| 1 | Immediate critical fixes (block release) | DB-01, AUTH-03, AUTH-01, AUTH-02, INJ-01, AUTH-09, CFG-01, CFG-02 |
| 2 | Security & data integrity | AUTH-04/05/06/07/08, INJ-02/03, CFG-03/04/06/07/10, DB-02/03/04, LOG-01/02/03/07 |
| 3 | Functional & process correctness | PROC-01…PROC-10, PROC-20/21, UX-05 |
| 4 | Architecture simplification | ARCH-01…ARCH-08, CODE-01…CODE-05 |
| 5 | Performance optimisation | PERF-01…PERF-08 |
| 6 | Code cleanup | CODE-06/07/08, DB-12, UX-10/11, CFG-13/14, dead code (see DEAD_CODE) |
| 7 | Testing & production hardening | TEST-01/02/03, CFG-05/11/12, DB-06/11, LOG-05/06 |

Full task breakdown in `REMEDIATION_PLAN.md`.

---

## 6. Independent Verification (Codex cross-audit)

Recorded in `AUDIT_CODEX_RECOVERED.md`. Empirical results from the Codex run:
- `composer audit` + `npm audit`: **no known vulnerabilities**.
- Unit tests: **41/41 pass**. `Phase1RbacHardeningTest`: **8/8 pass**.
- Full feature suite: **timed out at 120s** → recorded as a test-readiness limitation (corroborates TEST-01/03: the live-MySQL suite is slow/heavy, no isolation).
- Codex reached the same top-level verdict (Not ready) but under-triaged severity (1 P0 vs 7 here) — it missed the stored XSS, both record IDORs, and the queue/cron ops-break.

---

## 7. Final Production-Readiness Verdict

## ❌ NOT READY FOR PRODUCTION

**Evidence:** Seven CONFIRMED P0s — a publicly-advertised, seedable super-admin backdoor (AUTH-03 + DB-01), two exploitable record IDORs exposing confidential legal documents and enabling financial-claim tampering (AUTH-01, AUTH-02), a stored XSS executing in staff sessions (INJ-01), tenant-scope fail-open (AUTH-04), and core features (report exports, SLA/expiry/retention automation) that **silently never run** on the shared host because no queue worker or `schedule:run` cron is wired (CFG-01, CFG-02). Both this audit and the independent Codex audit reached the same verdict.

**The good news:** the build is fundamentally sound — strong transaction/lock discipline, clean architecture, real negative-path tests, a clean dependency audit, and the prior audit's CRITICALs already fixed. **The blocking P0s and most P1s are Small-effort fixes** (env guards, a few authorization checks, output escaping, config flags, cron wiring). Once Phase 1 + the Phase 2 security items land and prod `.env`/cron/queue are verified on the server, the system moves to **Ready with minor corrections**.

**Do not deploy to production until:** all P0s are closed, prod `.env` is verified (`APP_DEBUG=false`, secure cookies), and `schedule:run` + the export path are confirmed working on Hostinger.
