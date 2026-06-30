# Deliverable 8 — Final Validation Checklist + Phase 12 Test Scenarios

> **Purpose.** The merge-acceptance gate for the consolidated **2in1** Laravel app (Malaysian legal-aid,
> JBG / BHEUU). Confirms — with concrete, route-anchored checkboxes — that **no important legacy feature is
> missing, no business process is left incomplete, no duplicated/unnecessary process remains, no record can
> get stuck, no obsolete code remains, roles/permissions are correct, reports reflect correct data, the chat
> follows the same business rules, and all end-to-end tests pass.**
>
> **Built on** the six prior audit deliverables — feature matrix (`02-feature-comparison-matrix.md`),
> gap analysis (`03-gap-analysis.md`), redundancy/removal (`06-redundancy-and-removal-list.md`),
> status/workflow governance (`status-and-workflow-governance.md`), roles/access (`roles-and-access-control.md`),
> data/database (`data-and-database-review.md`) — and the nine maps (`maps/01..09`). Every item below
> traces to a real route name (from `routes/web.php`), controller, service, table, or test class verified at
> commit `735dd4f`, branch `main`. **READ-ONLY audit. Only this file was written.**
>
> **Legend.** `[ ]` = must pass before merge sign-off. **Severity tags** carried forward from prior docs:
> **CRITICAL** = blocks sign-off; **HIGH** = must fix or formally accept-with-waiver; **MED/LOW** = track.
> A checkbox marked **(BLOCKED — <ID>)** is a known-failing gate today: it must NOT be ticked until the
> referenced finding is remediated. Findings are referenced by their original IDs (e.g. B-1, G-C1, §4.1).

---

## 0. How to use this document

1. Run **Section 1** (the eight acceptance gates) top-to-bottom. Each gate is a set of checkbox assertions
   against a named route/controller/test. A gate **passes** only when every non-waived box is ticked.
2. Items pre-marked **(BLOCKED)** correspond to the CRITICAL/HIGH defects already found. They are listed so
   the checklist is honest: today the system **fails** gates 1, 4, 5, and 7. Do not paper over them.
3. Run **Section 2** (Phase 12 end-to-end test scenarios). Each scenario names the exact flow, route(s),
   pre-state, action, and expected post-state, and is tagged with the scenario *type* the brief requires
   (normal / alternative / rejection / correction / cancellation / resubmission / permission-restriction /
   invalid-data / missing-data / duplicate-submission / integration-failure / notification-failure /
   concurrent-update / historical-access / chat-triggered).
4. **Section 3** is the consolidated blocker register — the short list that must be green before go-live.

---

# SECTION 1 — FINAL VALIDATION CHECKLIST (eight acceptance gates)

## GATE 1 — No important legacy feature is missing

> Source: `02-feature-comparison-matrix.md` (Missing/Partial rows) + `03-gap-analysis.md`.

### 1A. Lawyer panel (PP origin)
- [ ] Public lawyer registration works end-to-end — `peguam.daftar` / `peguam.daftar.store` (`PeguamDaftarController`) writes `butiran_peguam_panel_2..6` + `uploaded_files`, honeypot + throttle 6/1, 18 doc types. **(Fully available — verify)**
- [ ] 3-tier application approval chain reachable — `permohonan-peguam.semak` → `.sokong` → `.keputusan` (`PermohonanPeguamController`), `permohonan_status` 0→1/2/3. **(Fully available — verify)**
- [ ] **(BLOCKED — G-H3)** Public application-status lookup ("Semak Status Permohonan", legacy `semak.php`/`checkstatus.php`) exists. **MISSING — no `semak`/`status-permohonan` public route.** Box stays unticked until a public status-checker route is added (note: `kes.semak-nokp` is an internal dup-IC guard, `permohonan-peguam.semak` is the staff vetting action — neither is the public checker).
- [ ] **(BLOCKED — G-C3 / G-H4)** "Surat Batal Penugasan" cancellation-letter PDF generated on KP-approved Tarik Diri. **MISSING — `TarikDiriService`/`TarikDiriController` generate no PDF/email.**
- [ ] **(BLOCKED — G-H4)** Applicant approval letter (`cetakanKelulusanPemohon`) + full lawyer-application dossier PDF reproducible. Only `cetak.ringkasan`/`cetak.penugasan`/`cetak.laporan` (`CetakanController`) exist — applicant-facing letters not ported.
- [ ] Tarik Diri Mewakili OYD (4-stage, 9 reasons) works — `peguam.tarikdiri.store` → `tarikdiri.ppuu` → `tarikdiri.pengarah` → `tarikdiri.kp` (`TarikDiriService`). **(Fully available — verify)**
- [ ] Bidang Pengkhususan add/drop works — `peguam.pengkhususan.add`/`.drop` → `kemaskini-bidang.pengarah` → `.kp` (`PengkhususanService`). **(Fully available — verify)**
- [ ] Lawyer deactivate/reactivate + death-redistribution works — `peguam-panel.nyahaktif`/`.aktif` (`PeguamLifecycleService::redistributeActiveCases`). **(Fully available — verify)**

### 1B. Case records (RK origin)
- [ ] Peringkat 1 intake — `kes.store` (`KesController`) auto-derives umur, cawangan from session, dup-IC guard `kes.semak-nokp`. **(Fully available — verify)**
- [ ] Peringkat 2 keputusan — `kes.lulus`/`kes.tolak` (`KeputusanController`, gated `kes.keputusan`). **(Verify)**
- [ ] **(BLOCKED — matrix B)** 30-day rule, `keputusan_menteri` override (`kelulusan='Perlu'`), and `batal` (Pembatalan Borang 1 → `Dibatalkan`/`Tamat`) confirmed present. Matrix flags these as "not confirmed in the lean controller" — verify or fill.
- [ ] No. Fail generation — `NoFailGenerator` (23-branch `$jbgMap`), transactional/unique (legacy COUNT race fixed). **(Verify uniqueness under concurrency — see TS-13.)**
- [ ] Tutup Fail (Peringkat 7) — `kes.tutupfail`; `fail-tutup` list via `kes.tutup`. **(Verify)**
- [ ] OYD registry CRUD — `oyd.store`/`oyd.update` (`OydController`), unique `kp_oyd`. **(Verify)**
- [ ] Case attachments — `lampiran.store`/`lampiran.download`/`lampiran.destroy` (private disk, auth-streamed). **(Verify)**

### 1C. Mediation / court
- [ ] **(BLOCKED — G-M6)** Pengantaraan write-path fully ported — `pengantaraan.update` populates `alasan_*`/`tarikh_perjanjian` so wide exports stop degrading to `-Tiada Maklumat-`.
- [ ] Tangguh Sidang log — `sidang.tangguh` (`sejarah_sidang`). **(Verify)**
- [ ] Court section + Laporan Kes — `mahkamah.update`, `laporan.store`/`laporan.destroy`. **(Verify)**
- [ ] **(BLOCKED — G-L4)** Mediator Cuti/Elaun (`detail_elaun`) decision: in-scope-and-built OR formally dropped. Currently no `elaun` controller.

### 1D. Advisory / appointments / citizen portal (ADV origin)
- [ ] KN intake (staff + citizen) — `khidmat.store` (`KhidmatNasihatController`) and `awam.permohonan.store` (`Awam\PermohonanController`); saringan re-asserted server-side. **(Verify)**
- [ ] Slot engine — `slot.generate` (`SlotGenerator`), `slot.tarikh`/`slot.masa` (`SlotAvailabilityService`, ≥4 working days). **(Verify — ADV backend never built this; 2in1 builds it real.)**
- [ ] Booking + reschedule + cancel — `KhidmatNasihatService::bookSlot` (`FOR UPDATE`), `awam.permohonan.reschedule`, `awam.permohonan.batal`. **(Verify)**
- [ ] "Buka Kes" advisory→litigation bridge — `khidmat.proses.buka-kes` (`KhidmatProsesService`, SELESAI + `id_forms===null`). **(Net-new value — verify.)**
- [ ] Maklum Balas — `maklum-balas.show`/`maklum-balas.store` (public, one per KN, unique index). **(Verify.)**
- [ ] Citizen document upload — `awam.lampiran.store`/`awam.lampiran.download` (owner-gated, MIME-derived). **(Verify.)**
- [ ] **(BLOCKED — G-M5)** Citizen (IC-login) password reset path exists. Only the email broker (`password.request`) exists; awam logs in by `nokp` — IC-users with no email cannot self-recover.

### 1E. Chat — see GATE 8 (chat business-rule conformance).

> **GATE 1 verdict today: FAIL** — blocked by G-H3 (public status checker), G-C3/G-H4 (cancellation/approval letters), G-M5 (citizen reset), G-M6 (mediation columns), plus the unverified Peringkat-2 sub-rules.

---

## GATE 2 — No incomplete business process (every workflow reaches a terminal state)

> Source: `status-and-workflow-governance.md` §11 (STUCK register) + `03-gap-analysis.md` §1–2.

- [ ] **(BLOCKED — B-1 / STUCK-1 / G-C1, CRITICAL)** Spine-issued offer reaches the lawyer. Today `AgihanService::kpLulus` writes numeric `status_agihan='1'` but `PeguamController::tawaran/dashboard/terima` (`peguam.tawaran`, `peguam.terima`) filter the **literal string** `'Ditawarkan'` → the offer never appears, never accepts, loops via `agihan:lebih-masa` forever. **The single highest-value fix; gate stays FAIL until `bucketValues([DITAWARKAN])` is used and accept writes numeric `DITERIMA`.**
- [ ] **(BLOCKED — B-2 / STUCK-2 / G-M1, CRITICAL)** Pengarah-rejected new case (`status_agihan='9'`, `agihan.pengarah.tolak`) has a recovery screen. Today `9` is in no bucket, `stage()`=null → orphaned with no re-open/route/close action.
- [ ] **(BLOCKED — G-1 / STUCK-3 / matrix F, CRITICAL)** No-show KN can close. Today `khidmat.proses.temu.kehadiran` (false) sets temu `TIDAK_HADIR` but `status_kn` stays `DALAM_PROSES` forever (`selesai` needs `HADIR`). Define `TIDAK_HADIR → SELESAI` or `→ MENUNGGU`.
- [ ] **(BLOCKED — G-2 / STUCK-4 / G-M4, HIGH)** Rejected appointment doesn't strand the KN. Today `khidmat.proses.temu.tolak` sets temu `BATAL` but leaves `status_kn` unchanged and gives no staff rebook path.
- [ ] **(BLOCKED — D-2 / STUCK-6, MED)** Approved lawyer can actually log in. `permohonan-peguam.keputusan` → `provisionLogin()` shows temp password **once in a flash, no email** — provisioned-but-uncredentialed lawyer is a process dead-end.
- [ ] **(BLOCKED — A-2 / STUCK-5, MED)** Approved case (`forms.status='Diterima'`) has an SLA/forced-next so it cannot silently stall indefinitely between keputusan and tutup-fail.
- [ ] **(BLOCKED — G-M3, MED)** KN payment confirmed. `KhidmatBayaran::kira()` computes `jumlah_bayaran`; `status_bayaran` is **never flipped to true** (no receipt route). Decide: build receipt step or formally mark fee advisory-only.
- [ ] Tarik Diri chain completes to a terminal row state (case `4`/`2`, history row `6`/`2`, `status_rekod='selesai'`) — `TarikDiriService::kpKeputusan`. **(Complete — verify; this is the model fully-guarded chain, C-3.)**
- [ ] Death-redistribution lands cases at `4` (recoverable, in `BUCKET_SEMULA`), not a dead state — `PeguamLifecycleService` (F-2). **(Verify.)**

> **GATE 2 verdict today: FAIL** — 4 CRITICAL/HIGH stuck-record classes (STUCK-1..4) unresolved.

---

## GATE 3 — No duplicated / unnecessary process remains

> Source: `06-redundancy-and-removal-list.md` §1–2.

- [ ] **(BLOCKED — WF-1/R-WF-01, HIGH-risk decision)** Exactly ONE case-assignment front-end is live. Today both `agihan.form`/`agihan.store` (single-step `AgihanController`, writes STRING) **and** the 3-tier spine `agihan.pengarah.*`/`agihan.ppuu.pilih`/`agihan.kp.keputusan` (`AgihanSpineController`+`AgihanService`, writes NUMERIC) mutate `forms.status_agihan` with no mutual guard. Decision required: retire single-step assign, keep `agihan.beban` (workload). Do not delete unilaterally — gate behind the write-normalisation migration + lawyer-Tawaran fix.
- [ ] **(BLOCKED — WF-2/ST-1, HIGH)** `forms.status_agihan` has ONE encoding. Today lawyer accept/reject + single-step write strings while the spine writes numerics; reconciled only at read (`StatusAgihan::LEGACY_STRING_MAP`). Verify a write-time normalisation + one-off data migration converging legacy string rows.
- [ ] **(BLOCKED — WF-3/C7, MED)** Case↔lawyer linkage keyed on a stable id (`peguam_panel.kp_peguam`/`users.id_peguam_panel`), not the `nama_peguam` string repeated in 4 matchers (`authorizeCase`, `@beban`, `redistributeActiveCases`, `hasActiveCaseInCategory`).
- [ ] **(BLOCKED — WF-4/G-M8, MED)** KN branch isolation comes from one scope, not 3 hand-rolled filters (`KhidmatProsesService`, `LaporanKnService`, report queries). Extend a `CawanganScope`-equivalent to `khidmat_nasihat`.
- [ ] Reporting layers are intentionally two-tier (narrow `laporan.index` + wide `laporan.penuh`), NOT accidental duplication (RP-1) — confirm no further dedup needed. **(Verify — keep both.)**
- [ ] `ref_kes` (litigation) and the `ref_kategori_kn` tree (advisory) are kept **separate by design** (TB-3 / memory `ref-kes-not-kn-tree`) — confirm NO merge was attempted. **(Verify.)**
- [ ] `mahkamah_sivil` / `mahkamah_syariah` left as-is unless schema cleanup is explicitly in scope (TB-2 — merge is a refactor, not a deletion). **(Confirm decision.)**

> **GATE 3 verdict today: FAIL** — the dual assignment front-end + dual encoding (WF-1/WF-2) are the defining "unnecessary duplicated process" and are still both live.

---

## GATE 4 — No record can get stuck (dead-end register cleared)

> Source: `status-and-workflow-governance.md` §11. This gate re-asserts GATE 2's stuck items as a hard register.

| Stuck ID | Record | Stuck state | Route that creates it | Cleared? |
|---|---|---|---|---|
| STUCK-1 | `forms` spine offer | `status_agihan='1'` numeric, invisible to lawyer | `agihan.kp.keputusan` (lulus) | [ ] **(BLOCKED — B-1)** |
| STUCK-2 | `forms` Pengarah-rejected new case | `status_agihan='9'` | `agihan.pengarah.tolak` | [ ] **(BLOCKED — B-2)** |
| STUCK-3 | `khidmat_nasihat` no-show | `status_kn='DALAM_PROSES'` + temu `TIDAK_HADIR` | `khidmat.proses.temu.kehadiran` (false) | [ ] **(BLOCKED — G-1)** |
| STUCK-4 | `khidmat_nasihat` rejected appt | `status_kn` unchanged + temu `BATAL` | `khidmat.proses.temu.tolak` | [ ] **(BLOCKED — G-2)** |
| STUCK-5 | `forms` approved-never-progressed | `status='Diterima'` (silent stall) | `kes.lulus` | [ ] (MED — A-2) |
| STUCK-6 | lawyer login provisioned, uncredentialed | `permohonan_status='1'`, no password delivered | `permohonan-peguam.keputusan` | [ ] (MED — D-2) |
| STUCK-7 | `butiran_peguam_panel_6` area | `checkbox_value_status='0'` unnamed limbo | `peguam.daftar.store` | [ ] (LOW — E-1) |

- [ ] A DB-level "one `status_rekod='aktif'` row per case" invariant exists on `sejarah_ppuu`/`sejarah_peguam_panel` (X-5) so the single-aktif-row rule can't silently break.

> **GATE 4 verdict today: FAIL** — STUCK-1..4 open (4 CRITICAL/HIGH), STUCK-5..7 tracked.

---

## GATE 5 — No obsolete code / dead schema remains (after the §11 safe-removal gate)

> Source: `06-redundancy-and-removal-list.md` §2 + `data-and-database-review.md` §7, §11.

- [ ] **(BLOCKED — F2/C2, HIGH)** ETL does not reference a non-existent table. `ImportLegacyData::importUsers()` selects from `users_peguam_panel_3`, which has **0 matches** in `sistemspk.sql` → `legacy:import` fatals. Repoint or drop the tier **before** any import run.
- [ ] `items` table + `Item` model + ETL `$verbatim` entry removed **only after** the 7-step §11 gate (archive → migration → ETL edit → `migrate:fresh` + `php artisan test` + sandbox `legacy:import`). (R-TB-01, confidence high.) **(Verify the gate ran, not just the drop.)**
- [ ] Legacy debug/secret/backdoor files (`phpinfo.php`, `test-emel.php`, `log_masuk_backdoor.php`, hardcoded-secret `config.php`, `cbjbg/main-commented-hero-serpapi-jwt.py`) confirmed **absent from the 2in1 tree** (R-CODE-01) — grep-clean. **(Verify; advisory.)**
- [ ] `StatusAgihan::TOLAK_KE_CAWANGAN ('14')` dead constant: keep its display label if legacy `14` rows exist; remove only the write-path expectation (R-CODE-02). **(Verify against dump.)**
- [ ] Decorative/unused seeded permissions (`menu.selenggara`, `peguam_panel.manage`, `peguam.permohonan.view`) handled (R-PERM-01) — grep `@can`/`->can`/`permission:` first; drop only zero-hit names; `permission:cache-reset`. **(Verify.)**
- [ ] **DO NOT remove** (confirm each is still present/intact): `model_has_permissions` (spatie-required), `jobs`/`job_batches`/`failed_jobs` (keep), `welcome.blade.php` (live KN landing — NOT the Laravel scaffold), `butiran_peguam_panel` v1 (still read by `PeguamPanel::butiran()` in 2 sites), `mahkamah_sivil`/`mahkamah_syariah`.
- [ ] Out-of-scope sibling leftovers (`sistem-rekod-kes-laravel/`, `spk-laravel/`) excluded from build/deploy, archived, NOT imported (keep former's migrations as a schema reference). **(Verify they are not in `composer.json`/autoload/routes.)**
- [ ] Spine status `9` dead-end is treated as a **gap to fill (GATE 2), not dead code to delete** (R-CODE-03). **(Confirm classification.)**

> **GATE 5 verdict today: FAIL** — the ETL `users_peguam_panel_3` fatal (F2) blocks any clean migration run; the rest are verifiable-after-procedure.

---

## GATE 6 — Roles & permissions correct

> Source: `roles-and-access-control.md` §3–6.

- [ ] **(BLOCKED — §4.1, CRITICAL)** A non-admin **cannot** mint or promote a user to `admin`. Today `pengguna.store`/`pengguna.update` (`UserController::syncRoles`) accept `role='admin'` from any `urus.pengguna` holder (pengarah/koordinator/ketua_pengarah); `UserRequest::authorize()` returns `true` with a false "admin-only" comment. **No test asserts this — add one (TS-25).**
- [ ] **(BLOCKED — §4.2, CRITICAL)** `peranan.akses.update` (`RolePermissionController`) cannot grant `urus.peranan`/`urus.pengguna`/`audit.view` to a non-admin role, nor empty `admin`'s matrix. Today `syncPermissions()` has no sensitive-permission allowlist.
- [ ] **(BLOCKED — §4.3 / G-H5 / F6, HIGH)** `awam` role + `awam.portal` permission are protected from rename/delete. Today seeded by **migration 130002**, absent from `RolePermissionSeeder::ROLES` and `RoleController::SYSTEM_ROLES` → `peranan.update`/`peranan.destroy` can break the citizen gate (`awam.portal`, `web.php:82`).
- [ ] **(BLOCKED — §4.4, HIGH)** The Akses matrix UI does not lie. The 11 decorative permissions (`kes.view/create/update`, `pengantaraan.manage`, `mahkamah.manage`, `lampiran.manage`, `cetakan.view`, `oyd.manage`, `kpi.view`, `peguam_panel.manage`, `peguam.permohonan.view`) are either **enforced at a real route** or removed — so revoking them in the UI actually changes access. Today they gate nothing (only outer `permission:system.view`).
- [ ] **(BLOCKED — §4.5, HIGH)** Read-side leaks closed. `agihan.senarai`/`agihan.maklumat`, `tarikdiri.senarai`/`tarikdiri.maklumat`, `kemaskini-bidang.index`, `permohonan-peguam.index`/`show`, and OYD (`oyd.index`, `butiran_oyd` PII) carry a real `permission:`/branch gate — not just outer `system.view`. None are `CawanganScope`-covered today → cross-branch read leak.
- [ ] **(BLOCKED — D-1, HIGH)** `permohonan-peguam.tarik` (`permohonan_status='3'`) requires an approval permission + from-guard. Today it is `auth`-only (no `can()`), reachable from any state incl. `1` Lulus.
- [ ] `khidmat.proses` asymmetry (granted to `pengarah`, NOT `ketua_pengarah`) reviewed — confirm intentional or grant KP (§4.7).
- [ ] Spatie middleware is pipe-delimited everywhere (`role:a|b`), guarded by `Batch7RbacMatrixTest::test_no_comma_delimited_spatie_middleware`. **(Verify test green.)**
- [ ] `Gate::before(admin)` super-admin bypass present and intentional (`AppServiceProvider:29`); `KhidmatNasihatPolicy` owner-gate enforced on `awam.permohonan.show/update/batal/reschedule/awam.lampiran.download`. **(Verify.)**

> **GATE 6 verdict today: FAIL** — two CRITICAL escalation paths (§4.1, §4.2) + four HIGH (awam-drift, decorative-matrix, read-leaks, under-gated tarik). `Batch7RbacMatrixTest` does **not** cover any of these.

---

## GATE 7 — Reports reflect correct data

> Source: `02-feature-comparison-matrix.md` §E + `03-gap-analysis.md` §3 (G-M6/M7) + `data-and-database-review.md` §4.

- [ ] **(BLOCKED — G-M7, MED)** SLA `khidmat` 60-day rule uses ONE end column. Today `StatistikSlaController`/`SlaMatrix` use `tarikh_persetujuan` while `kpi.index` (`KpiController`) uses `tarikh_selesai` → the two dashboards disagree for the same metric. Pick one; assert equality in a test.
- [ ] **(BLOCKED — G-M6, MED)** Wide-export mediation columns (`alasan_tidak_setuju_pengantara`, `alasan_gagal_pengantara`, `alasan_tangguh_sidang`, `alasan_tidak_rujuk_pengantaraan`, `kategori_kes2`, perjanjian dates) are populated, not `-Tiada Maklumat-` — `laporan.penuh` (`WideExport`). Tied to the GATE 1C pengantaraan write-path.
- [ ] **(BLOCKED — A-3, MED)** On-screen `forms.status` and report-derived status agree. `WideExport::statusPemfailan()`/`LaporanPenuhController::statusFilter()` derive "Selesai/Pemfailan Selesai/Belum Difailkan" from `status`+dates — they can diverge from the stored status for the same row.
- [ ] Statistik dashboard correct under branch scope — `statistik.index` (`StatistikController`, `CawanganScope` on `forms`), Excel `statistik.excel` + PDF `statistik.pdf`. **(Verify totals match a known fixture.)**
- [ ] SLA matrices correct — `statistik-sla.index` (`SlaMatrix`, 5 defs; legacy `*7.0` typo + Putrajaya bug fixed). **(Verify against `SlaMatrixTest`.)**
- [ ] Kesilapan No.Fail report catches duplicates — `statistik-kesilapan.index` (`KesilapanMatrix`). **(Verify `KesilapanMatrixTest`.)**
- [ ] Pengantaraan matrices correct — `statistik-pengantaraan.kategori/.bulanan/.pencapaian` (`PengantaraanMatrix`; F2 numerator port-deviation documented). **(Verify `PengantaraanMatrixTest`.)**
- [ ] KN reports (8) correct + branch-scoped — `laporan-kn.*` (`LaporanKnService`), Excel exports. **(Verify `Batch12LaporanKnTest`.)**
- [ ] Wide CSV envelope integrity — UTF-8 BOM, NoKP-as-text, BIL, ref_kes join, reason decode (`WideExportTest`). **(Verify.)**
- [ ] **(BLOCKED — §4.5 read-leak interaction)** Reports respect branch isolation for KN/OYD (no `CawanganScope` on those tables today → a report that forgets the manual filter leaks cross-branch rows).

> **GATE 7 verdict today: FAIL** — SLA/KPI end-date conflict + hollow mediation export columns are live data-correctness defects.

---

## GATE 8 — Chat follows the same business rules

> Source: `maps/04-chat-cbjbg.md` + `02-feature-comparison-matrix.md` §I. The governing business rule is:
> **the bot is a public, generic JBG/legal-aid Q&A assistant with ZERO access to 2in1 records, users, or
> permissions.** "Same business rules" = it must stay inside that boundary and respect the platform's
> rate-limit / auth posture.

- [ ] Bot has **no** access to 2in1 data/roles — confirmed no reference to any 2in1 table/model in `main-with-cors.py`; proxy sends only `message` + opaque `session_id` + display `name`. **(Boundary intact — verify.)**
- [ ] Proxy is the only ingress — `chatbot.ask` (`POST /chatbot/ask`, `ChatbotController@ask`) mints JWT server-side (creds never reach browser), `throttle:20,1`. **(Verify.)**
- [ ] Input validation enforced — `message` `required|string|max:1000`; graceful 503 (unconfigured) / 502 (transport) Malay messages. **(Verify — TS-50.)**
- [ ] Per-session isolation — stable `chatbot_sid` per Laravel session; no cross-session bleed. **(Verify.)**
- [ ] **(BLOCKED — chat security debt, HIGH/MED)** Pre-prod hardening done before go-live: rotate the 5 plaintext secrets (OpenAI/JWT/Basic user+pass/SerpAPI/DB pw), stop duplicating Basic creds across repos, lock `/docs`+`/redoc`, stop reflecting inbound headers in `/forward_message`, set `CORS_ORIGINS` to the real origin.
- [ ] Dead `news_today` tool (MySQL on JBG internal net, unreachable from HF Spaces) removed or re-pointed — does not error the agent. **(Verify.)**
- [ ] Widget surfacing decision made — currently only `welcome.blade.php`; if surfaced to authenticated areas, confirm no 2in1 identity/record context is leaked to the bot (it needs none today). **(Confirm decision.)**
- [ ] Bot scope-lock holds — refuses non-JBG/creative/coding/meta requests, 150-word cap, Malay greeting (system prompt). **(Spot-verify — TS-50.)**

> **GATE 8 verdict today: CONDITIONAL PASS** — the *business-rule boundary* (no record access) is correct and intact; the gate cannot be fully ticked until the operational security debt is cleared, but no functional chat business-rule is violated.

---

## GATE 9 — All end-to-end tests pass

- [ ] Existing suite green — `php artisan test` across `tests/Feature/*` + `tests/Unit/*` (Batch7 RBAC/seeder/scope/adminui, Batch8 masters, Batch9 KN/create/wakil, Batch10 slot/slotgen, Batch11 bukakes/officer, Batch12 laporan-kn/maklumbalas, LebihMasa, Notifikasi, Pengantaraan/Sla/Kesilapan exports, Hardening, Awam/*, Khidmat/*). **(Run.)**
- [ ] New coverage added for the gaps the existing suite misses (see Section 2 — none of TS-25/26 escalation, TS-01 spine→lawyer hand-off, TS-09/10 stuck states are covered today). **(Add before sign-off.)**
- [ ] 80% coverage threshold met on changed/added code (per project testing rule). **(Verify.)**

> **GATE 9 verdict today: PARTIAL** — current suite passes for what it covers, but it does **not** cover the CRITICAL defects (B-1, §4.1, G-1, G-2). Sign-off requires the new scenarios below.

---

# SECTION 2 — PHASE 12 END-TO-END TEST SCENARIOS

> Each scenario: **Type** (per brief) · **Flow/route(s)** · **Pre-state** · **Action** · **Expected** ·
> **Status today**. "Status today" flags whether the expected behaviour currently holds (per the audit) —
> ❌ = known-failing (maps to a blocker), ✅ = expected to pass, ⚠ = unverified/needs confirmation.

## 2.1 — Lawyer-panel assignment spine (the core, most-broken flow)

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-01 | **Normal** | spine: `agihan.pengarah.terima` → `agihan.ppuu.pilih` → `agihan.pengarah.keputusan`(sokong) → `agihan.kp.keputusan`(lulus) → `peguam.tawaran` → `peguam.terima` | new case `status_agihan='0'` | full PPUU→Pengarah→KP→lawyer accept | case ends `status_agihan=2` (DITERIMA, numeric); **offer appears in lawyer Tawaran**; `sejarah_ppuu` single-aktif rotates correctly | ❌ **B-1** — offer never reaches lawyer (string/numeric filter mismatch) |
| TS-02 | **Alternative** | `agihan.pengarah.keputusan`(tidak) | case `status_agihan='10'` | Pengarah declines the pick | case → `4` (PPUU_AGIH_SEMULA), back to PPUU pool, recoverable | ✅ (guarded path) |
| TS-03 | **Rejection** | `agihan.kp.keputusan`(tolak) | case `status_agihan='13'` | KP rejects | case → `15` → PPUU re-pick (`10`) | ✅ |
| TS-04 | **Rejection (dead-end)** | `agihan.pengarah.tolak` | new case `status_agihan='0'` | Pengarah rejects a NEW case | case → `9`; **must surface in a recovery/re-route queue** | ❌ **B-2** — `9` orphaned, no screen |
| TS-05 | **Correction** | lawyer `peguam.tolak` then re-offer | case at `1` (offered) | lawyer rejects offer | case → `4`, history `status='T'`, lawyer cleared, re-enters pool | ⚠ — works but **no `ensureStatus` from-guard** (B-5); also depends on TS-01 surfacing |
| TS-06 | **Cancellation (withdrawal)** | `peguam.tarikdiri.store` → `tarikdiri.ppuu` → `tarikdiri.pengarah` → `tarikdiri.kp`(lulus) | active case `status_agihan=2` | full Tarik Diri approve | case → `4`, history row → `6` `selesai`, lawyer cleared, new aktif PPUU row opened | ✅ (model chain, C-3) — note case/history divergence (C-1) is intentional |
| TS-07 | **Rejection (withdrawal)** | `tarikdiri.kp`(tolak) | TD at `17` | KP rejects withdrawal | case → `2`, lawyer keeps case, row `selesai` `keputusan_tarikDiriHQ='1'` | ✅ |
| TS-08 | **Integration-failure (timeout)** | scheduled `agihan:lebih-masa` (`LebihMasaService`, daily 07:00) | offer at `1` unanswered ≥7d | scheduler fires | case → `4`, history `status='7'`, notify Pengarah | ⚠ — works in isolation (`LebihMasaTest`), but with B-1 it fires on **every** spine offer (lawyer can't accept) → infinite re-loop |
| TS-09 | **Permission-restriction** | `agihan.pengarah.terima` as `ppuu`/`pegawai` | any | non-`agihan.pengarah` role POSTs | 403/redirect; only `pengarah` (and admin) allowed | ✅ (route `permission:`-gated) — but **read leak** TS-31 |
| TS-10 | **Concurrent-update** | two staff both `agihan.kp.keputusan` on same case | case at `13` | double-submit | second blocked by `AgihanSpineController::ensureStatus` (from-status assertion) | ✅ for spine; ❌ for lawyer `peguam.terima`/`peguam.tolak` (no `ensureStatus`, B-5) |
| TS-11 | **Duplicate-submission (parallel front-ends)** | `agihan.store` (single-step) on a case mid-spine | case at `10`/`13` | single-step assign overwrites | **must be refused** (case is mid-spine) | ❌ **B-4/WF-1** — no mutual guard; single-step clobbers spine state |

## 2.2 — Lawyer-panel application approval + lifecycle

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-12 | **Normal** | `peguam.daftar.store` → `permohonan-peguam.semak` → `.sokong` → `.keputusan`(lulus) | none | apply → vet → endorse → approve | `permohonan_status` 0→1; `promote()` creates `peguam_panel` + `users` (temp pw); **firma data copied from `_4`** | ⚠ — works but firma stubbed `'-'` (G-M9) and **no credential email** (D-2) |
| TS-13 | **Duplicate-submission** | `peguam.daftar.store` twice same IC; `kes.semak-nokp` | applicant exists | re-register same `nokp` | rejected/flagged duplicate (unique guard) | ⚠ — verify dup-IC enforcement on lawyer daftar |
| TS-14 | **Notification-failure** | `permohonan-peguam.keputusan`(lulus) with mail driver `log`/down | approved | provision login | lawyer still obtains credentials via a delivered channel (email OR printed letter OR admin-set) | ❌ **D-2/G-C2** — temp pw shown once in flash, no email → lawyer locked out if banner missed |
| TS-15 | **Permission-restriction** | `permohonan-peguam.tarik` (status→`3`) as `pegawai` | application at `1` Lulus | non-approver withdraws app | **must be blocked** + from-guard (only from pending) | ❌ **D-1/D-4** — `auth`-only, reachable from `1` |
| TS-16 | **Cancellation + cascade** | `peguam-panel.nyahaktif` (death/deactivate) | lawyer with N active cases | deactivate | login blocked; all active cases (`status_agihan ∈ bucketValues({1,2})`) → `4`, new aktif `sejarah_ppuu` rows | ✅ (F-1, correctly uses bucketValues) — but **notifies nobody** (G-H2) |
| TS-17 | **Resubmission** | `peguam-panel.aktif` after nyahaktif | deactivated lawyer | reactivate | login re-enabled; redistributed cases **not** auto-pulled back (by design) | ✅ (F-2) — confirm intended |
| TS-18 | **Bidang pengkhususan drop blocked** | `peguam.pengkhususan.drop` | lawyer has active case in that category | request drop | **blocked** by `hasActiveCaseInCategory()` | ⚠ — works but name+`LIKE` matched (E-2, fragile) |
| TS-19 | **Bidang add normal** | `peguam.pengkhususan.add` → `kemaskini-bidang.pengarah` → `.kp`(lulus) | area `checkbox_value_status='4'` | add request approved | `4→9→2` (AKTIF) | ✅ |

## 2.3 — Case records (intake → keputusan → closure)

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-20 | **Normal** | `kes.store` → `kes.lulus` → (edits) → `kes.tutupfail` | none | intake → approve → close | `status` blank→`Diterima`→`Fail Tutup`; `no_fail` generated; audit APPROVE/UPDATE | ⚠ — verify 30-day rule + `keputusan_menteri` override present (matrix B) |
| TS-21 | **Rejection** | `kes.tolak` | new case | director rejects | `status='Ditolak'`, `reason`, `tarikh_pemakluman`; terminal | ✅ |
| TS-22 | **Invalid-data** | `kes.store` with bad `nokp`/missing required | — | submit malformed | validation 422, no row created | ⚠ — confirm `KesController` request rules |
| TS-23 | **Missing-data** | `cetak.penugasan` on a case with no lawyer assigned | case unassigned | print penugasan | blocks/empty-state (no crash) — known guard | ✅ (penugasan blocks if no lawyer) |
| TS-24 | **Duplicate-submission** | `kes.semak-nokp` (AJAX) then `kes.store` | applicant in-process | same IC re-intake | dup modal warns; store still allowed only if intended | ✅ |
| TS-25 | **Concurrent No.Fail** | two `kes.lulus`/buka-kes concurrently in same branch+jenis | two approvals | both generate `no_fail` | **unique** file numbers (no COUNT race) — `NoFailGenerator` transactional | ⚠ — verify uniqueness under concurrency (legacy had the race) |

## 2.4 — Access-control / RBAC (the CRITICAL escalation set)

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-26 | **Permission-restriction (escalation)** | `pengguna.store`/`pengguna.update` (`UserController`) as `pengarah`/`koordinator`/`ketua_pengarah` | non-admin actor with `urus.pengguna` | create/update user with `role='admin'` | **MUST be rejected** — only an `admin` can mint an admin | ❌ **§4.1 CRITICAL** — currently allowed; no test exists |
| TS-27 | **Permission-restriction (matrix)** | `peranan.akses.update` (`RolePermissionController`) | admin | grant `urus.peranan`/`urus.pengguna` to `pegawai`, or empty `admin` | sensitive perms protected; `admin` matrix cannot be stripped | ❌ **§4.2 CRITICAL** |
| TS-28 | **Historical-access (gate break)** | `peranan.update`/`peranan.destroy` on `awam` role | admin | rename/delete `awam` | **blocked** (awam is a protected system role) | ❌ **§4.3/G-H5/F6** — currently allowed → breaks citizen portal |
| TS-29 | **Permission-restriction (decorative)** | revoke `kes.create` from `pegawai` in matrix, then `kes.store` as that pegawai | pegawai without `kes.create` | attempt case intake | **blocked** (matrix change must bite) | ❌ **§4.4** — `kes.create` is decorative; intake still works (only `system.view` gates it) |
| TS-30 | **Permission-restriction (read leak)** | `tarikdiri.senarai`/`kemaskini-bidang.index`/`permohonan-peguam.index`/`oyd.index` as `pembantu_tadbir` | clerk, other branch | open lifecycle/OYD queues | only own-branch + permitted rows | ❌ **§4.5** — no per-perm/branch gate; cross-branch PII leak (`butiran_oyd`) |
| TS-31 | **Permission-restriction (read leak, agihan)** | `agihan.senarai`/`agihan.maklumat` as any `system.view` holder | other branch | read agihan queue | branch-scoped read | ❌ **§4.5** — `agihan.manage` granted to all staff incl. clerks; not branch-scoped |
| TS-32 | **Permission-restriction (positive)** | `kes.lulus` as `pegawai` (no `kes.keputusan`) | pegawai | attempt keputusan | 403 (`KeputusanController::gate()`) | ✅ (in-controller `can('kes.keputusan')`) |
| TS-33 | **Branch isolation (forms)** | `kes.index` as `pengarah` of branch A | cases in A and B | list cases | only branch-A rows (`CawanganScope`); koordinator/KP see all (`cawangan.view-all`) | ✅ for `forms`; ❌ for KN/OYD/temu_janji (no scope) |

## 2.5 — Khidmat Nasihat + appointments (citizen + officer)

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-34 | **Normal (citizen)** | `awam.daftar.store` → `awam.permohonan.saringan` → `.create` → `.store` (book slot) → officer `khidmat.proses.assign` → `.temu.terima` → `.temu.kehadiran`(true) → `.temu.selesai` | none | full self-service advisory → completed session | `status_kn` DRAF/BAHARU→DALAM_PROSES→SELESAI; temu MENUNGGU→DISAHKAN→HADIR→SELESAI; unlocks Maklum Balas + Buka Kes | ✅ happy path (verify `Batch11OfficerProcessingTest`) |
| TS-35 | **Alternative (no-show)** | `khidmat.proses.temu.kehadiran`(false) | temu DISAHKAN | mark not-attended | KN reaches a terminal/recovery state (SELESAI-tanpa-kehadiran OR reschedule) | ❌ **G-1/STUCK-3** — `status_kn` stuck DALAM_PROSES forever |
| TS-36 | **Rejection** | `khidmat.proses.temu.tolak` | temu MENUNGGU | officer rejects appointment | KN gets explicit fate (BATAL + slot released, OR rebook path) | ❌ **G-2/STUCK-4** — temu BATAL but `status_kn` unchanged, no rebook |
| TS-37 | **Cancellation (citizen)** | `awam.permohonan.batal` | own KN, cancellable temu | citizen cancels | temu BATAL, slot released, `status_kn=BATAL` | ✅ (owner-gated `KhidmatNasihatPolicy`) |
| TS-38 | **Correction / resubmission (reschedule)** | `awam.permohonan.reschedule` | own KN | citizen reschedules | old slot released, new slot booked (≥4 working days) | ⚠ — `AwamRescheduleRequest` only `after:today` but `bookSlot` needs real ≥4-day slot → in-window-no-slot 422 (G-5) |
| TS-39 | **Invalid-data (saringan bypass)** | `awam.permohonan.store` without passing saringan | no `session('awam_saringan.lulus')` | skip means-test, submit | 403 (server re-asserts saringan) | ✅ (`Awam\PermohonanController:77`) |
| TS-40 | **Missing-data (slot)** | `slot.tarikh`/`slot.masa` then `.store` when no open slot | no available slot ≥4 days | attempt book | clear "no slot" response, no partial booking | ⚠ — verify UX (G-5) |
| TS-41 | **Duplicate-submission (slot race)** | two citizens `awam.permohonan.store` same slot | one open slot | concurrent book | exactly one wins (`bookSlot` `FOR UPDATE`); other re-prompted | ✅ (race-safe) |
| TS-42 | **Permission-restriction (ownership)** | `awam.permohonan.show`/`.download` on another citizen's KN | KN owned by user X | user Y opens | 403 (`KhidmatNasihatPolicy::owns`) | ✅ |
| TS-43 | **Historical-access (staff-created KN)** | citizen self-registers after a staff walk-in KN (`id_pengguna=null`) | staff-created KN exists | citizen logs in | citizen does NOT see the counter-created KN (by design) — confirm acceptable | ⚠ G-L6 — flag for consolidation |
| TS-44 | **Buka Kes bridge** | `khidmat.proses.buka-kes` | KN SELESAI, `id_forms=null` | officer opens litigation case | new `forms` row, back-link `id_forms`, `no_fail` generated | ✅ (verify `Batch11BukaKesTest`) |
| TS-45 | **Payment confirmation** | (no route today) | KN with `jumlah_bayaran` RM10/260 | confirm payment / receipt | `status_bayaran` flips true + receipt recorded | ❌ **G-M3** — no route flips `status_bayaran`; permanently "unpaid" |
| TS-46 | **Feedback (public)** | `maklum-balas.store` | KN SELESAI | submit feedback (twice) | one row per KN; second is idempotent success (unique index) | ✅ (verify `Batch12MaklumBalasTest`) |
| TS-47 | **Invalid status guard** | `maklum-balas.store` on a non-SELESAI KN | KN DALAM_PROSES | submit feedback early | rejected (server re-checks `status_kn===SELESAI`) | ✅ |

## 2.6 — Reports / data correctness

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-48 | **Data-correctness (SLA/KPI)** | compare `statistik-sla.index` vs `kpi.index` for the 60-day khidmat rule | same dataset | run both | identical pass/fail per branch×kategori | ❌ **G-M7** — `tarikh_persetujuan` vs `tarikh_selesai` diverge |
| TS-49 | **Missing-data (export columns)** | `laporan.penuh` pengantaraan export | cases with mediation reasons | export wide CSV | reason columns populated, not `-Tiada Maklumat-` | ❌ **G-M6** — columns hollow (write-path gap) |
| TS-50 | **Historical-access (closed files)** | `kes.tutup` (`fail-tutup`) + `cetak.laporan` | closed cases | browse + print closed file | closed files listed + printable; audit trail intact | ✅ (verify) |

## 2.7 — Chat-triggered

| # | Type | Flow / route(s) | Pre-state | Action | Expected | Today |
|---|---|---|---|---|---|---|
| TS-51 | **Chat-triggered (normal)** | `chatbot.ask` (`ChatbotController@ask`) | guest on `welcome` | ask a JBG/legal-aid question | `{reply}` from bot; scope-locked, ≤150 words, Malay; **no 2in1 record access** | ✅ (boundary intact) |
| TS-52 | **Chat-triggered (integration-failure)** | `chatbot.ask` with bot URL unset / token endpoint down | misconfig / outage | ask | graceful 503 (unconfigured) / 502 (transport), Malay message, no stack leak | ✅ (degrades gracefully — verify) |
| TS-53 | **Chat-triggered (rate-limit / abuse)** | `chatbot.ask` >20 req/min from one IP | burst | spam | `throttle:20,1` blocks excess (protects OpenAI/SerpAPI cost) | ✅ at proxy — ⚠ a direct caller with shared Basic creds bypasses it (bot has no own rate-limit) |
| TS-54 | **Chat-triggered (invalid-data)** | `chatbot.ask` with empty / >1000-char `message` | — | submit | 422 validation, no upstream call | ✅ |
| TS-55 | **Chat-triggered (business-rule boundary)** | `chatbot.ask` "what is the status of MY application?" | logged-in citizen | ask record-specific question | bot answers generically / declines — it has **zero** 2in1 data by design (not a regression) | ✅ (confirm the boundary is the intended rule) |

---

# SECTION 3 — CONSOLIDATED BLOCKER REGISTER (must be GREEN before go-live)

> The minimum set that flips the failing gates. Ordered by severity. Each maps a gate + scenario + the
> original finding ID across the prior deliverables.

| # | Blocker | Gate | Scenario | Finding | Sev |
|---|---|---|---|---|---|
| BL-1 | Spine→lawyer offer hand-off broken (`tawaran` filters string `'Ditawarkan'` vs numeric `1`) | 2,4 | TS-01 | B-1 / G-C1 / STUCK-1 | **CRITICAL** |
| BL-2 | Pengarah-reject `status_agihan='9'` dead-end, no recovery screen | 2,4 | TS-04 | B-2 / G-M1 / STUCK-2 | **CRITICAL** |
| BL-3 | KN no-show `TIDAK_HADIR` stuck DALAM_PROSES forever | 2,4 | TS-35 | G-1 / G-H7 / STUCK-3 | **CRITICAL** |
| BL-4 | Non-admin can mint/promote `admin` via `pengguna.store/update` | 6 | TS-26 | §4.1 | **CRITICAL** |
| BL-5 | `peranan.akses.update` can grant sensitive perms / empty admin matrix | 6 | TS-27 | §4.2 | **CRITICAL** |
| BL-6 | KN `tolak` strands KN appointment-less, no rebook | 2,4 | TS-36 | G-2 / G-M4 / STUCK-4 | HIGH |
| BL-7 | `awam` role renamable/deletable → breaks citizen portal | 6 | TS-28 | §4.3 / G-H5 / F6 | HIGH |
| BL-8 | Dual encoding + two assignment front-ends, no write-normalisation/guard | 3 | TS-11 | B-3/B-4 / WF-1/WF-2 / G-H1 | HIGH |
| BL-9 | Decorative permissions — Akses matrix lies; revoking does nothing | 6 | TS-29 | §4.4 | HIGH |
| BL-10 | Read-side leaks on lifecycle/OYD queues (no per-perm/branch gate) | 6,7 | TS-30/31 | §4.5 | HIGH |
| BL-11 | Credential delivery gap (temp pw flash-only, no email) | 1,2 | TS-14 | D-2 / G-C2 | HIGH |
| BL-12 | ETL fatal — `users_peguam_panel_3` not in any dump | 5 | (ETL run) | F2 / C2 | HIGH |
| BL-13 | Public application-status checker missing | 1 | TS-13 area | G-H3 | HIGH |
| BL-14 | Cancellation/approval-letter PDFs not generated | 1 | TS-06 / TS-12 | G-C3 / G-H4 | HIGH |
| BL-15 | `permohonan-peguam.tarik` under-gated (auth-only, any state) | 6 | TS-15 | D-1 / D-4 | HIGH |
| BL-16 | SLA vs KPI 60-day end-date conflict | 7 | TS-48 | G-M7 | MED |
| BL-17 | Mediation wide-export columns hollow | 1,7 | TS-49 | G-M6 | MED |
| BL-18 | KN payment never confirmed (`status_bayaran` dead) | 2 | TS-45 | G-M3 | MED |
| BL-19 | Chat secret rotation + `/docs` lock + header-reflection (pre-prod) | 8 | TS-53 | map04 §8 | HIGH (ops) |
| BL-20 | `forms` parity short ~8 cols + `laporan_kes.id_kes` type mismatch | 5,7 | TS-50 | F3 / G-M10 | HIGH |

**Sign-off rule:** GATES 1–9 may be declared PASS only when BL-1..BL-5 (CRITICAL) are closed and each HIGH is
either fixed or carries a written, dated accept-with-waiver from the JBG/BHEUU product owner. The current
state of the system is **NOT releasable** — five CRITICAL blockers (BL-1..BL-5) are open, and the existing
automated suite (`tests/Feature/*`) does not cover a single one of them. Add TS-01, TS-04, TS-11, TS-26,
TS-27, TS-28, TS-29, TS-30, TS-31, TS-35, TS-36 as failing regression tests first (RED), then remediate (GREEN).

---

## Appendix A — Coverage of the brief's required confirmations

| Brief requirement | Gate(s) | Verdict today |
|---|---|---|
| No important legacy feature missing | 1 | FAIL (G-H3, G-C3/H4, G-M5/M6) |
| No incomplete business process | 2 | FAIL (STUCK-1..4) |
| No duplicated/unnecessary process | 3 | FAIL (WF-1/WF-2) |
| No record can get stuck | 4 | FAIL (STUCK-1..4) |
| No obsolete code remains | 5 | FAIL pending ETL fix (F2); rest verifiable-after-§11 |
| Roles/permissions correct | 6 | FAIL (§4.1/4.2 CRITICAL, +4 HIGH) |
| Reports reflect correct data | 7 | FAIL (G-M6/M7, A-3) |
| Chat follows same business rules | 8 | CONDITIONAL PASS (boundary intact; ops debt open) |
| All end-to-end tests pass | 9 | PARTIAL (suite green but misses all CRITICALs) |

## Appendix B — Required confirmations the brief raised that resolve to "already correct" (do not regress)

- bcrypt auth + `must_change_password` + `ForcePasswordChange` (replaces legacy plaintext) — keep.
- No backdoor login / hardcoded secrets in the 2in1 tree (legacy `log_masuk_backdoor.php` not ported) — keep.
- `LebihMasaService` + `agihan:lebih-masa` scheduler IS implemented (corrects the stale map claim) — keep;
  but note it weaponises B-1 into an infinite loop until BL-1 is fixed.
- Tarik Diri chain (TS-06/07) is the fully-guarded model workflow (C-3) — keep as the pattern to copy.
- `maklum_balas` unique-index idempotency (TS-46/47) — best-modelled new table; keep.
- KN "Buka Kes" advisory→litigation bridge (TS-44) — net-new value over every legacy system; keep.
- Chat record-access boundary (TS-51/55) — the *intended* business rule; the absence of record access is
  correct, not a gap.
