# Deliverable 3 — Gap Analysis: Legacy Sources vs Consolidated 2in1

> **Scope.** Compares each legacy source system against the consolidated 2in1 Laravel app and lists, per gap: what legacy had, what 2in1 has now, the impact, severity, and the legacy reference. Built by cross-referencing audit maps 01–09 and verified against live 2in1 code (controllers, routes, services, migrations, Blade views, schedule) at commit `735dd4f` (branch `main`).
>
> **Sources compared:**
> - L1 = `sistem-peguam-panel` (legacy raw PHP, lawyer panel + agihan) — map 01
> - L2 = `sistem-rekod-kes` (legacy raw PHP, case records + mediation + court + stats) — map 02
> - L3 = `iGuaman` advisory/appointment (legacy ASP.NET + Nuxt) — map 03
> - L4 = `cbjbg` chatbot (Python FastAPI microservice) — map 04
> - 2in1 = consolidated Laravel 13 app — maps 05–08
>
> **READ-ONLY audit. No source files modified.** Only this map file was written.
> Severity legend: **CRITICAL** (data loss / users blocked / legal-obligation break), **HIGH** (relied-on feature missing or broken), **MEDIUM** (workaround exists / partial), **LOW** (cosmetic / minor convenience).

---

## 0. Executive summary

2in1 has achieved broad **structural parity** — the case spine, 3-tier agihan, withdrawal, lawyer lifecycle/death-redistribution, KN advisory + slot engine, citizen portal, feedback, and the bulk of the statistik/laporan/SLA/KPI reporting are all built and largely functional. The gaps cluster in **five areas**:

1. **A broken cross-path hand-off** (spine offers never reach the lawyer's Tawaran list) — the single highest-impact functional break.
2. **Email/notification coverage collapsed** from ~9 legacy triggers to 4 (agihan only). Lawyer credential delivery, registration decisions, withdrawal letters, account-provisioning emails, and password-reset for migrated users are all gone or shown once-on-screen.
3. **FPDF/Dompdf print artefacts** reduced from ~19 legacy print views to 3 per-case + a handful of report PDFs. Several official letters (cancellation letter / "surat batal penugasan", applicant approval/summary letters) are not generated.
4. **Public self-service lookups dropped** — no public application-status lookup (`semak.php`), no IC-user password reset for citizens.
5. **Several lifecycle dead-ends and uncompleted-process states** carried into 2in1 (Pengarah-reject `9`, KN `TIDAK_HADIR`, payment never confirmed, KN `tolak`-leaves-appointmentless).

The chatbot (L4) is cleanly decoupled and has **no functional gap** — only operational hardening (secret rotation) remains, which is out of scope for this comparison.

---

## 1. CRITICAL gaps

### G-C1 — Spine→Lawyer offer hand-off is broken (lawyer cannot see spine-issued offers)
| | |
|---|---|
| **Legacy (L1)** | Assignment via `formAgihanBaru.php` / `formAgihanBaruKP.php` set `forms.status_agihan='1'` (DITAWARKAN); the lawyer's `senarai-penugasan.php` listed offers by reading that numeric `1`. One encoding end-to-end. |
| **2in1 now** | `AgihanService::kpLulus()` (verified `app/Support/AgihanService.php:145-149`) writes the **numeric** `StatusAgihan::DITAWARKAN = '1'`. But the lawyer area `PeguamController::tawaran()`/`dashboard()`/`terima()` filter on the **literal string** `where('status_agihan','Ditawarkan')` (verified `app/Http/Controllers/PeguamController.php:46,70`). A case offered through the canonical 3-tier spine is stored as `'1'` and therefore **never appears** in the lawyer's Tawaran list and cannot be accepted. Only the parallel single-step `AgihanController` path (which writes the string `'Ditawarkan'`) surfaces to lawyers. |
| **Impact** | The primary, documented assignment workflow (PPUU→Pengarah→KP) silently dead-ends: KP approves, case shows `Ditawarkan` to staff, but the lawyer's screen is empty. No offer, no acceptance, no case progression. Functionally severs the core legal-aid assignment chain that 2in1 was built to replicate. |
| **Severity** | **CRITICAL** (relied-on core workflow broken; data is written but unreachable). |
| **Fix direction** | `tawaran/dashboard/terima` must use `StatusAgihan::bucketValues([DITAWARKAN])` (as `LebihMasaService` already does), or write-time normalise the column. |

### G-C2 — No password reset / credential path for migrated lawyer & officer accounts
| | |
|---|---|
| **Legacy (L1)** | `set-semula.php` + `query/set-semulapassword.php` reset by ID+email and **emailed** a new password; `query/selenggaraPengguna.php` emailed temp passwords to newly-created officers (`tambahPegJBG`) and regenerated lawyer/officer passwords (`janaNewPass`/`janaNewPassPP`) with email delivery. |
| **2in1 now** | The only reset is the standard Laravel **email broker** (`/password/forgot`, `PasswordResetController`) — which requires a working `MAIL_*` config (dev driver is `log`) and a valid email on the account. New lawyer login provisioning (`PermohonanPeguamController::provisionLogin`) shows the temp password **once in a flash message** with **no email** (verified `PermohonanPeguamController.php:111`). New-user creation (`UserController::store`) sets the password but **never emails it** (verified — no `Mail::` in `UserController`). |
| **Impact** | A panel lawyer approved-and-provisioned cannot log in if the approving clerk misses the one-time banner — locked out until an admin manually resets. Migrated bulk users (966 ETL accounts) have `must_change_password` but **no delivered initial credential** and citizens/lawyers who never had an email on file cannot self-reset. This blocks the people the system exists to serve. |
| **Severity** | **CRITICAL** (users provably blocked from first login; no delivery channel). |

### G-C3 — Official cancellation letter ("Surat Batal Penugasan") no longer generated on withdrawal
| | |
|---|---|
| **Legacy (L1)** | On KP-approved Tarik Diri, `query/tarikdiri.php` generated a **Dompdf cancellation letter** saved to `uploads/surat/surat_batal_penugasan_{kp}_{ts}.pdf` and **emailed it to the lawyer** — the official record that the assignment was cancelled. |
| **2in1 now** | `TarikDiriService` performs the status transitions (12→16→17→6/case→4) but generates **no PDF and sends no email** (verified — no `pdf`/`dompdf`/`surat`/`Mail::` references in `TarikDiriService.php` or `TarikDiriController.php`). |
| **Impact** | A legally-meaningful artefact (formal notice that a panel lawyer has been released from representing an assisted person) is lost. No document trail for the lawyer, the OYD, or the file. Process-completion step dropped. |
| **Severity** | **CRITICAL** (loss of an official legal document users relied on; affects representation record). |

---

## 2. HIGH gaps

### G-H1 — Dual status-encoding on `forms.status_agihan` (no write normalisation, no data migration)
| | |
|---|---|
| **Legacy** | Single encoding per system: peguam-panel used numeric `0–20`; rekod-kes only *read* `status_agihan`. |
| **2in1 now** | Two front-ends write **two encodings** to the same column: `AgihanController` (single-step) + lawyer accept/reject write **strings** (`'Ditawarkan'`/`'Diterima'`/`'Ditolak'`); the spine + `AgihanService` write **numeric** (`'1'`/`'2'`/`'4'`…). Reconciled only at *read* time via `StatusAgihan::LEGACY_STRING_MAP`/`bucketValues`. No write-time convergence, no migration to normalise existing rows, and **no guard** preventing the single-step path from clobbering a case mid-spine (verified maps 05 §4, 06 §5). |
| **Impact** | The `forms.status_agihan` column holds mixed values; any query that forgets `bucketValues()` (e.g. G-C1) silently mis-counts or drops cases. Two assignment UIs can race on one case. ETL/reporting fragility. |
| **Severity** | **HIGH** (data-integrity + the root cause of G-C1). |

### G-H2 — Email/notification coverage collapsed from ~9 triggers to 4
| | |
|---|---|
| **Legacy (L1)** | Transactional emails (PHPMailer/Gmail) on: (1) registration submitted, (2) forgot-password reset, (3) new officer created + temp pass, (4) password regenerated, (5) **lawyer deceased → reassignment notice** to Pengarah/PPUU/KP, (6) new agihan to PPUU, (7) **registration decision lulus/tolak to applicant**, (8) withdrawal at each tier + cancellation letter, (9) reminder cron. L2 also had `emel_ke_peguam_panel.php` (ad-hoc email to a panel lawyer). |
| **2in1 now** | **Only 4 mail send-sites exist** (verified by grep across `app/`): `KesDitawarkanMail` (single-step offer), `AgihanTransisiMail` (spine next-actor), `KesLebihMasaMail` (auto-reassign), all best-effort. **Missing:** registration-decision email to applicant, deceased-lawyer reassignment notice email, new-officer/new-lawyer credential email, password-regen email, ad-hoc email-to-lawyer, withdrawal-tier emails. The deceased-lawyer redistribution (`PeguamLifecycleService`) updates DB but notifies nobody. |
| **Impact** | Applicants are not told their panel application was approved/rejected; officers aren't emailed credentials; supervisors aren't alerted when a lawyer's caseload is redistributed on death/deactivation. Users who relied on email to know "what happened next" now get nothing. |
| **Severity** | **HIGH** (multiple relied-on notifications dropped; some are duty-to-inform). |

### G-H3 — No public application-status lookup ("Semak Status Permohonan")
| | |
|---|---|
| **Legacy (L1)** | `semak.php` + `query/checkstatus.php` — a **public, no-login** screen where an applicant entered their ID and saw their lawyer-panel application status (`permohonan_status` 0–5). |
| **2in1 now** | **No equivalent route or controller exists** (verified — no `semak`/`checkstatus` public-status route in `routes/web.php`; the only `semak-nokp` is an internal AJAX duplicate-IC guard, and `permohonan-peguam/{butiran}/semak` is the staff PPUU vetting action). Lawyer applicants have no way to check status without logging in (and they have no login until approved). |
| **Impact** | Prospective panel lawyers lose all visibility into their pending application. The legacy "diproses dalam 21 hari, semak di sini" affordance is gone. Generates support load. |
| **Severity** | **HIGH** (public self-service feature relied on by every applicant, removed). |

### G-H4 — Print/letter artefacts reduced from ~19 to ~3 per-case (applicant letters dropped)
| | |
|---|---|
| **Legacy (L1+L2)** | L1: 4 FPDF prints — applicant summary (`cetak.php`/`cetakanRingkasanPemohon.php`), **applicant approval letter** (`cetakanKelulusanPemohon.php`), full application (`cetakanMaklumatPermohonan.php`), court-case report (`cetakanLaporanKesMahkamah.php`) + the Dompdf cancellation letter. L2: **15 `cetakan*.php`** print views (per-case dossier + statistik prints). |
| **2in1 now** | `CetakanController` exposes **3** per-case dompdf views only: `ringkasan`, `agihan` (penugasan), `laporan` (verified — `app/Http/Controllers/CetakanController.php`, views `resources/views/cetakan/{ringkasan,agihan,laporan}.blade.php`). Report-level PDFs exist for the statistik/laporan/SLA modules (maps 06 §4), so the *statistik* prints are mostly covered, but the **applicant-facing approval/summary letters** (`cetakanKelulusanPemohon`, full application dossier for the lawyer applicant) are **not reproduced**. |
| **Impact** | Officers can no longer print the official applicant approval letter or the full lawyer-application dossier as PDF. Manual workaround only. |
| **Severity** | **HIGH** (relied-on official prints missing) — but partially mitigated because statistik/laporan PDFs are ported. |

### G-H5 — `awam` role is renamable/deletable → citizen portal gate can be broken
| | |
|---|---|
| **Legacy (L3)** | Roles were ASP.NET Identity roles managed centrally; the citizen role (`PELANGGAN`) was a fixed seed. |
| **2in1 now** | The `awam` role + `awam.portal` permission are seeded by a **separate migration** (`2026_06_30_130002`), **not** in `RolePermissionSeeder::ROLES` nor `RoleController::SYSTEM_ROLES` (verified map 08 §2.2). An admin using the Peranan UI can therefore **rename or delete the `awam` role**, which silently breaks the entire citizen portal gate (`permission:awam.portal`). |
| **Impact** | One admin mis-click in role management locks every citizen out of the public portal — the most exposed, highest-volume surface. |
| **Severity** | **HIGH** (single action breaks a whole user_type's access; no guard). |

### G-H6 — Reconstructed lawyer tables (`_3.._6`, `sejarah_ppuu`) not reconciled against the real dump
| | |
|---|---|
| **Legacy** | The 51.7 MB `sistemspk.sql` dump **does contain** `butiran_peguam_panel_3..6` and `sejarah_ppuu` with their real column shapes and data. |
| **2in1 now** | 2in1 migrations (`…000002`, `…000004`, `…000005`) treat `_3..6` and `sejarah_ppuu` as **"reconstructed from source code, never dumped"** and rebuild Blueprint shapes from the legacy PHP forms (verified map 08 §7.3). The actual dump was apparently not used as the schema/data source of truth for these tables. |
| **Impact** | Column drift between the reconstructed Blueprint and the real legacy schema → **ETL of existing lawyer qualification/cert/bank/specialisation + PPUU history data may silently lose or mis-map columns** (e.g. CSO cert fields, eVendor, ADR certs). Existing lawyers' profile/history could import incomplete. |
| **Severity** | **HIGH** (data-loss risk on migration of real lawyer records). |

### G-H7 — KN `TIDAK_HADIR` is a permanent hanging state (no-show cases never close)
| | |
|---|---|
| **Legacy (L3)** | The Nuxt flow completed a session even without attendance: "…Selesai Tanpa Kehadiran Pelanggan" set `statusKN: 'SELESAI'` with `isPelangganHadir=false`. A no-show still reached a terminal SELESAI. |
| **2in1 now** | `KhidmatProsesService` `kehadiran(false)` sets `temu_janji.status=TIDAK_HADIR` but leaves `khidmat_nasihat.status_kn` at `DALAM_PROSES` **forever**. The `selesai` action requires temu `HADIR`, so a no-show **cannot be completed or reopened** (verified map 07 §10). |
| **Impact** | Every no-show appointment becomes a stuck `DALAM_PROSES` record that distorts officer worklists and KN statistics indefinitely. Legacy could close it; 2in1 cannot. |
| **Severity** | **HIGH** (process-completion step lost; data pollution that grows over time). |

---

## 3. MEDIUM gaps

### G-M1 — Status `9` (Ditolak Pengarah) is a spine dead-end with no recovery screen
| | |
|---|---|
| **Legacy (L1)** | A Pengarah-rejected new case (`statusAgihan='9'`) flowed back into PPUU re-distribution queues (`senarai-pengagihan-semula.php`). |
| **2in1 now** | `pengarahTolakBaru` sets `9`, which is in **no list bucket** (baru/semasa/semula/tarik_diri all exclude `9`) and `stage()` returns null. The case vanishes from every spine queue with no built re-open/re-route screen (verified map 05 §5). The notification tells the branch to "kemas kini sistem" but no corresponding screen exists. |
| **Impact** | A rejected case is orphaned in the spine; staff must edit the DB or use the parallel single-step path. Recovery workflow lost. |
| **Severity** | **MEDIUM** (workaround via single-step path exists, but the documented re-distribution flow is broken). |

### G-M2 — Two parallel assignment front-ends with no mutual guard
| | |
|---|---|
| **Legacy (L1)** | One assignment workflow (the multi-tier `formAgihan*` controllers). |
| **2in1 now** | `AgihanController` (single-step, string status) **and** `AgihanSpineController`+`AgihanService` (3-tier, numeric) both mutate `forms.status_agihan` for the same case, with no lock/guard preventing one from overwriting the other's in-flight state (verified maps 05 §4, 06 §5). |
| **Impact** | A case mid-spine can be clobbered by a single-step assign; encoding clash (G-H1). Consolidation must pick one path. |
| **Severity** | **MEDIUM** (latent corruption risk; depends on operator discipline). |

### G-M3 — KN payment is computed but never confirmed (no receipt step)
| | |
|---|---|
| **Legacy (L3)** | Officer recorded `statusBayaran` + `nomborResit` at pengesahan (skipped only when amount = 0). |
| **2in1 now** | `KhidmatBayaran::kira()` computes `jumlah_bayaran` and `status_bayaran` defaults false, but **no route/controller flips `status_bayaran` or records a receipt number** (verified — no `resit`/payment-confirmation route in `routes/web.php`; map 07 §10). Fee is informational only. |
| **Impact** | The RM10/RM260 contribution path has no payment reconciliation; no receipt record. Financial step incomplete. |
| **Severity** | **MEDIUM** (revenue/audit step missing; not blocking the advisory itself). |

### G-M4 — KN `tolak` leaves the application appointment-less with no rebook path
| | |
|---|---|
| **Legacy (L3)** | Reject (`SetKeputusanPegawaiTerimaKes` with `isTerimaKes=false`) wrote only the TemuJanji decision; the KN could be reassigned/rebooked through the officer queue. |
| **2in1 now** | `tolak` sets `temu_janji=BATAL` but leaves `status_kn` unchanged; the KN now has no appointment and **no staff-side rebook path** (verified map 07 §10). |
| **Impact** | A PKN-rejected appointment strands the citizen's KN with no clear next step on staff side. |
| **Severity** | **MEDIUM** (recoverable manually; workflow gap). |

### G-M5 — Citizens (IC-login) have no working password-reset
| | |
|---|---|
| **Legacy (L2/L3)** | rekod-kes had `lupa_kata_laluan.php` (email reset). L3 citizens registered with email. |
| **2in1 now** | Citizens log in by **`nokp` (IC)**, and the only reset is the **email** broker (`/password/forgot`). A citizen who forgot their password and whose account email is blank/unknown has **no reset path** (verified — only `password.request`/`password.email`; awam login is IC-based). |
| **Impact** | Locked-out citizens cannot self-recover; the highest-volume user_type has the weakest recovery. |
| **Severity** | **MEDIUM** (depends on whether citizen emails are captured at register; portal still usable for others). |

### G-M6 — Pengantaraan (mediation) wide-export columns degrade to "-Tiada Maklumat-"
| | |
|---|---|
| **Legacy (L2)** | `export_*.php` wide CSVs emitted full mediation columns: `alasan_tidak_setuju_pengantara`, `alasan_gagal_pengantara`, `alasan_tangguh_sidang`, `alasan_tidak_rujuk_pengantaraan`, `kategori_kes2`, and perjanjian dates. |
| **2in1 now** | `WideExport` flags these columns as **stub/degraded** — they emit `-Tiada Maklumat-` because the pengantaraan workflow that populates them is not fully ported; `penugasanPengantaraan` re-uses `tarikh_persetujuan` for "TARIKH PERJANJIAN PENYELESAIAN" as a known mismap (verified map 06 §4, §5.4). |
| **Impact** | Mediation reports/exports are incomplete vs legacy; analysts lose those fields. |
| **Severity** | **MEDIUM** (export exists but columns are hollow). |

### G-M7 — SLA `khidmat` end-date discrepancy vs KPI (port inconsistency)
| | |
|---|---|
| **Legacy (L2)** | One business rule for the 60-day mediation-service KPI. |
| **2in1 now** | `SlaMatrix` uses `tarikh_persetujuan` as the end column while `KpiController` uses `tarikh_selesai` for the equivalent 60-day rule (verified map 06 §4, §5.5) — same rule, two different end dates → the two dashboards can disagree. |
| **Impact** | SLA and KPI views report different pass/fail for the same metric; erodes trust in reporting. |
| **Severity** | **MEDIUM** (numbers diverge; needs reconciliation). |

### G-M8 — Branch isolation (`CawanganScope`) applied to only one model
| | |
|---|---|
| **Legacy (L2)** | Branch scoping (`$is_hq` rule) was applied consistently across the case/list/report queries in `dashboard.php` and the senarai screens. |
| **2in1 now** | `CawanganScope` is a global scope on **`Form` only**. KN (`khidmat_nasihat`), `temu_janji`, `slot_temu_janji`, OYD, and lawyer tables have **no scope**; KN branch isolation is re-implemented manually in 3 places (`KhidmatProsesService`, `LaporanKnService`, report queries) (verified map 08 §3, map 07 §10). |
| **Impact** | Any new KN/OYD query that forgets to scope leaks cross-branch data. Inconsistent enforcement is a latent confidentiality risk. |
| **Severity** | **MEDIUM** (works today, fragile by construction). |

### G-M9 — Promoted lawyer master loses section-4 firm data; name-based linkage throughout
| | |
|---|---|
| **Legacy (L1)** | Lawyer↔case linkage was by name string too (legacy weakness), but the firm record existed in `butiran_peguam_panel_4`. |
| **2in1 now** | `PermohonanPeguamController::promote()` stubs firm address/poskod/negeri/tel as `'-'` even though `butiran_peguam_panel_4` holds the real data (verified map 05 §3). All case↔lawyer ownership, workload, redistribution, and category-drop guards match on `nama_peguam` **string** rather than `kp_peguam`/id (verified map 05 §4, §6, §9). |
| **Impact** | Promoted lawyer masters carry placeholder firm fields; duplicate/renamed names break ownership/workload/redistribution. |
| **Severity** | **MEDIUM** (data-quality + fragility; not an immediate break). |

### G-M10 — `laporan_kes.id_kes` type mismatch (`varchar` vs `forms.id` int)
| | |
|---|---|
| **Legacy (L2)** | Same legacy shape, joined loosely. |
| **2in1 now** | `laporan_kes.id_kes` is `varchar(20)` while `forms.id` is int — **no FK possible**; joins/relations rely on string comparison (verified map 06 §5.6, map 08 §8). |
| **Impact** | Court-report rows can orphan or mis-join; referential integrity is app-only. |
| **Severity** | **MEDIUM** (integrity risk on the court-report child). |

---

## 4. LOW gaps

| ID | Legacy had | 2in1 now | Impact | Sev |
|---|---|---|---|---|
| G-L1 | L3 surfaced a **`DIKECUALIKAN`** (exempted) KN list badge/filter | `dikecualikan` exists only as a **screening input** (`tiada_perkara_dikecualikan`), not a stored `status_kn`; no exempted-state badge (verified) | Minor list/filter affordance lost | LOW |
| G-L2 | L1 `cron_lebih_masa.php` reassigned via random PPUU (`array_rand`) | 2in1 `agihan:lebih-masa` scheduled daily 07:00 **and implemented** (`LebihMasaService`, uses `bucketValues([DITAWARKAN])`) — **this gap is CLOSED and improved** (deterministic, audited). Noted to correct map 05's "missing automation" claim. | None — parity reached + improved | LOW |
| G-L3 | L1 `katalaluan.php` per-role change-password screens | 2in1 single `ForcePasswordChange` + `/password/change` | Consolidated, fine | LOW |
| G-L4 | L2 mediator **Cuti/elaun** (`formTambahCuti`, `list_cuti`, `detail_elaun`) for mediator availability/allowance | 2in1 has `Cuti Umum`+`Cuti Negeri` (public-holiday masters for SLA/slot calc) but **no mediator leave/elaun module** | Mediator-availability/allowance tracking not ported | LOW |
| G-L5 | L1 status `14` (TOLAK_KE_CAWANGAN) was a live KP-reject target | `14` defined+labelled but **never written** (KP reject → `15`); dead constant (verified map 05 §5) | Dead code, read-compat only | LOW |
| G-L6 | L3 staff-created KN visible in officer context | 2in1 staff-created KN set `id_pengguna=null` → **invisible in citizen portal** even if the same person later registers (by design) | Citizen can't see counter-registered KN | LOW |
| G-L7 | `checkbox_value_status=0` ("selected at daftar") | `0` is an **unnamed/unhandled** state in `ButiranPeguamPanel6` — never surfaced in kemaskini queue (verified map 05 §8) | Mild dead-state | LOW |
| G-L8 | Legacy `items` demo table | Imported but **no model use/controller** (DEAD candidate); `ref_lokasi_berguam`, `butiran_peguam_panel` v1 near-dead (verified map 08 §9) | Schema clutter | LOW |

---

## 5. What 2in1 IMPROVED over legacy (anti-gaps — do not "restore")

These are deliberate, verified improvements; listed so they are not mistaken for regressions:

| Area | Legacy weakness | 2in1 |
|---|---|---|
| Passwords | **Plaintext** (`$password === $kata_laluan`) in BOTH L1 & L2 | bcrypt via `Auth::attempt` + `must_change_password` + `ForcePasswordChange` |
| Backdoor login | L2 shipped `log_masuk_backdoor.php` | **Absent** (verified — no backdoor in 2in1) |
| Hardcoded secrets | L1 `config.php` Gmail app-pw + 4 hardcoded PDO hosts; L4 `.env` live keys | App grep-clean; secrets in `.env`/config |
| Authz | L1 `allowRole()` broken (`peranan` never set); L3 `[Authorize]` commented out (client-side-only gating) | spatie RBAC, server-side route + Gate enforcement |
| No. Fail race | L2 COUNT-based sequence, no locking → duplicate file numbers | `NoFailGenerator` service (centralised) |
| SQL injection | Pervasive raw interpolation in both legacy PHP systems | Eloquent / prepared throughout |
| Lebih-Masa | L1 random PPUU pick (`array_rand`), non-deterministic | Deterministic, audited `LebihMasaService` (G-L2) |
| FK integrity | Legacy had almost no FKs | New tables carry real FKs (legacy int tables kept index-only by design) |

---

## 6. Gap counts by source system

| Source | CRITICAL | HIGH | MEDIUM | LOW | Notes |
|---|---|---|---|---|---|
| L1 peguam-panel | G-C1, G-C2, G-C3 | G-H1, G-H2, G-H3, G-H4, G-H6 | G-M1, G-M2, G-M9 | G-L2, G-L3, G-L5, G-L7 | Most gaps cluster here (assignment + notifications + prints + lawyer-data ETL). |
| L2 rekod-kes | — | G-H4 (shared prints) | G-M6, G-M7, G-M8, G-M10 | G-L4 | Core case lifecycle well-ported; gaps are in mediation columns, SLA reconciliation, branch-scope breadth, mediator-leave. |
| L3 iguaman advisory | — | G-H5, G-H7 | G-M3, G-M4, G-M5 | G-L1, G-L6 | KN/slot engine strong; gaps in no-show closure, payment confirmation, citizen reset, role-protection. |
| L4 cbjbg chatbot | — | — | — | — | No functional gap (decoupled microservice). Operational hardening only (secret rotation) — out of comparison scope. |

---

## 7. Open questions (carry into remediation planning)

1. **G-C1/G-H1:** Is the single-step `AgihanController` path meant to be retired, or kept as an officer override? The fix differs (retire vs reconcile encodings + fix `tawaran()` filters).
2. **G-C2/G-H2:** Is `MAIL_*` provisioned for production? Several CRITICAL/HIGH gaps assume working mail; if mail is intentionally deferred, the credential-delivery gap needs an alternative (printed letter / admin-set-password UI).
3. **G-H6:** Confirm whether real `_3..6`/`sejarah_ppuu` data is being ETL'd from the dump or only schema-created empty — determines whether existing lawyer profiles/history survive migration.
4. **G-H4/G-C3:** Which official letters are legally required (approval letter, cancellation letter) vs nice-to-have? Prioritise reinstating the required PDFs.
5. **G-M3:** Is KN payment reconciliation in scope for v1, or is the fee purely advisory? (`statusBayaran` is dead today.)
6. **G-L4:** Is mediator leave/allowance (`detail_elaun`) in scope, or intentionally dropped from the mediation domain?
