# Deliverable 5 — End-to-End Process Flows (Consolidated 2in1)

> **Scope.** The five major processes of the consolidated Laravel app **2in1** (Malaysian legal-aid, JBG/BHEUU)
> at `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/2in1`, branch `main`, commit `735dd4f`:
> **(A)** Legal-aid case lifecycle, **(B)** Panel-lawyer lifecycle, **(C)** Khidmat Nasihat advisory,
> **(D)** Citizen self-service portal, **(E)** Chat-assisted query.
>
> **Method.** Each flow is documented against the **12 process elements**:
> 1 trigger · 2 responsible user · 3 required input · 4 validation · 5 processing · 6 approval · 7 status changes ·
> 8 notification · 9 output · 10 completion condition · 11 exception handling · 12 audit trail.
> Every route, controller, service, status value, and notification site was verified **directly against source**
> and cross-referenced with the sibling audit deliverables:
> `02-feature-comparison-matrix.md`, `03-gap-analysis.md`, `06-redundancy-and-removal-list.md`,
> `status-and-workflow-governance.md` (status IDs S1–S9, flags A–X, STUCK-1..7),
> `roles-and-access-control.md`, `data-and-database-review.md` (F1–F7), and maps `01`–`09`.
> Where this doc cites a finding it uses those existing IDs so the deliverables stay consistent.
> **READ-ONLY audit. Only this file was written.**

---

## 0. Executive summary — the dead-end / hanging-state register

Five processes contain **seven** states from which a record cannot legitimately advance through the UI.
This is the consolidated dead-end register; each is detailed inline in the relevant flow and traces to the
governance `STUCK-n` IDs.

| # | Flow | Dead-end / hanging state | Record stuck at | Why no exit | Sev | Gov ID |
|---|---|---|---|---|---|---|
| D1 | A/B (assignment) | **Spine offer never reaches lawyer** | `forms.status_agihan='1'` (numeric) | Lawyer Tawaran list queries literal `'Ditawarkan'`; offer invisible → 7-day `LebihMasa` bounces to `4` → re-loops spine forever | **CRITICAL** | STUCK-1 / B-1 / G-C1 |
| D2 | A (case) | **Pengarah-rejected new case** | `forms.status_agihan='9'` | In no list bucket; `stage()`=null; no re-open/route/close screen | **CRITICAL** | STUCK-2 / B-2 / G-M1 |
| D3 | C (advisory) | **No-show appointment** | `status_kn='DALAM_PROSES'` + temu `TIDAK_HADIR` | `selesai` needs temu `HADIR`; no reschedule/auto-close path | **CRITICAL** | STUCK-3 / G-1 / G-H7 |
| D4 | C (advisory) | **Officer-rejected appointment** | `status_kn` unchanged + temu `BATAL` | `tolak` leaves KN appointment-less; no staff rebook | **HIGH** | STUCK-4 / G-2 / G-M4 |
| D5 | B (lawyer onboard) | **Approved lawyer, no credentials** | `permohonan_status='1'` but temp password shown once, no email | Banner missed → login row exists, lawyer can't authenticate | **MED** | STUCK-6 / D-2 / G-C2 |
| D6 | A (case) | **Approved, never progressed** | `forms.status='Diterima'` | onward steps optional free-text; no SLA/forced-next | **MED** | STUCK-5 / A-2 |
| D7 | C (payment) | **Computed fee never confirmed** | `khidmat_nasihat.status_bayaran=0` | No route flips it to `1`; no receipt step | **MED** | G-3 / G-M3 |

**Two systemic root causes** (per `status-and-workflow-governance.md §14`) drive D1, D2, D3, D4:
(1) **dual string/numeric encoding on `forms.status_agihan`** reconciled only at read time, and
(2) **state machines whose terminal/exception branches were never given a next-action screen.**

---

# FLOW A — Legal-aid case lifecycle

`permohonan → keputusan pengarah → agihan spine (PPUU/Pengarah/KP) → pengantaraan or kes mahkamah → tutup fail`

The case spine is the wide `forms` table (92+ cols, `$timestamps=false`, `$guarded=['id']`).
Two distinct status columns drive it: **S1 `forms.status`** (free-text lifecycle) and
**S2 `forms.status_agihan`** (the assignment machine). They are independent and can disagree.

### A-stage 1 — Permohonan intake (Peringkat 1)

| # Element | Detail |
|---|---|
| 1 Trigger | Citizen walks in / counter intake; officer opens "Permohonan Baharu". |
| 2 Responsible | `pegawai` (front-line officer); any staff with `kes.create` (decorative — real gate is outer `permission:system.view`, see `roles §4.4`). |
| 3 Input | nama, `nokp` (→ auto-derive umur/jantina), OKU, bangsa, etnik, kaedah penerimaan, kategori_kes, cawangan (from session). |
| 4 Validation | Laravel `validate` in `KesController::store`; **AJAX duplicate-IC guard** `kes.semak-nokp` (`checkNokp`) blocks a second live application for the same IC. |
| 5 Processing | `KesController::store` inserts `forms` row; `cawangan` stamped from `auth()->user()->cawangan`; `CawanganScope` thereafter isolates it to the branch. |
| 6 Approval | none at intake. |
| 7 Status | `forms.status` left **blank** → displayed as `"baru"` (S1). `status_agihan` not yet set (Peringkat-1 case is not in the agihan spine). |
| 8 Notification | none. |
| 9 Output | A `forms` record; appears in `KesController::index` ("Senarai Kes / dalam proses"). **No `no_fail` yet** (file number generated only on approval/buka-kes). |
| 10 Completion | Hand-off to Peringkat 2 (keputusan). |
| 11 Exception | Duplicate IC → modal block. No partial-save draft for staff case intake (unlike KN). |
| 12 Audit | `Audit::log('forms', id, INSERT, …)` via `KesController` (record-level only; field diffs always NULL — see `data-and-database-review` F-audit). |

### A-stage 2 — Keputusan Pengarah (Peringkat 2: lulus / tolak)

Source: `KeputusanController` (verified). Gate: `abort_unless($user->can('kes.keputusan'))` — only **pengarah** and **ketua_pengarah** (seeder).

| # Element | Detail |
|---|---|
| 1 Trigger | Director opens the case and presses Lulus or Tolak (`kes/show.blade.php:166` shows the buttons under `@can('kes.keputusan')`). |
| 2 Responsible | `pengarah` / `ketua_pengarah` (`KeputusanController::gate()`). |
| 3 Input | **lulus:** `kelulusan`, `sumbangan` (both nullable). **tolak:** `reason` (nullable, max 100). |
| 4 Validation | `$request->validate(...)`; gate re-checked server-side. **Gap:** the legacy 30-day rule + `keputusan_menteri` override (when `kelulusan='Perlu'`) are **not enforced** in this lean controller (matrix §B "Improve — verify 30-day + menteri-override + batal"). |
| 5 Processing | `lulus`: sets `keputusan='Diluluskan'`, `diterima='Ya'`, dates (`tarikh_perakuan`, `tarikh_pemakluman`, `tarikh_pengarahKemaskini`). `tolak`: `keputusan='Ditolak'`, `diterima='Tidak'`, `reason`, `tarikh_pemakluman`. |
| 6 Approval | this **is** the approval step (single-tier director decision; distinct from the 3-tier agihan approval that follows). |
| 7 Status | `forms.status`: blank → **`Diterima`** (lulus) or **`Ditolak`** (tolak) (S1). |
| 8 Notification | **none** — legacy emailed the applicant the lulus/tolak decision; 2in1 sends nothing (gap **G-H2**: "registration-decision email to applicant" dropped). |
| 9 Output | Approved case is now eligible for agihan / pengantaraan / mahkamah edits and eventual closure. |
| 10 Completion | `Diterima` = ready to progress; `Ditolak` = **terminal** (no reopen path — A-5, by design). |
| 11 Exception / **DEAD-END D6** | After `Diterima`, every onward step (agihan, pengantaraan, mahkamah) is **optional free-text**; nothing forces a next step. A case can sit at `Diterima` indefinitely with no SLA flag → **silent stall** (STUCK-5 / A-2). **Fix:** add an SLA timer / "awaiting agihan" worklist that surfaces approved-but-unassigned cases. |
| 12 Audit | `Audit::log('forms', id, APPROVE\|REJECT, "Permohonan diluluskan/ditolak: {nama}")` (`KeputusanController:48,69`). |

### A-stage 3 — Agihan spine (PPUU → Pengarah → KP → lawyer offer)

This is the **3-tier assignment spine** — the most critical and most broken sub-flow. Source of truth
`App\Support\StatusAgihan` (numeric consts); engine `AgihanService`; host/guard `AgihanSpineController`
(`ensureStatus()` = `abort_unless(normalise(status) IN allowed, 422)`). Status field **S2 `forms.status_agihan`**.
Full state dictionary in `status-and-workflow-governance §3`.

| Sub-step | Route → method | Gate | From→To (S2) | 8 Notification | 12 Audit |
|---|---|---|---|---|---|
| Pengarah accepts new case | `POST /agihan/{kes}/pengarah-terima` → `pengarahTerima` | `permission:agihan.pengarah` | `0→8` | `NotifikasiAgihan::pengarahTerima` → PPUU (`AgihanService:46`) | `UPDATE` |
| Pengarah rejects new case | `POST /agihan/{kes}/pengarah-tolak` → `pengarahTolak` | `agihan.pengarah` | `0→9` | `NotifikasiAgihan::pengarahTolak` → branch (`AgihanService:54`) | `UPDATE` |
| PPUU picks lawyer | `POST /agihan/{kes}/ppuu-pilih` → `ppuuPilih` | `agihan.ppuu` | `8/4/15→10` | `NotifikasiAgihan::ppuuPilih` → Pengarah (`:85`) | `UPDATE` |
| Pengarah endorses pick | `POST /agihan/{kes}/pengarah-keputusan` (sokong) | `agihan.pengarah` | `10→13` | (forwards KP) | `APPROVE` |
| Pengarah declines pick | same (tidak) | `agihan.pengarah` | `10→4` | — | `UPDATE` |
| KP approves → **offer** | `POST /agihan/{kes}/kp-keputusan` (lulus) → `kpLulus` | `agihan.kp` | `13→1` | **`KesDitawarkanMail` → lawyer email** (`AgihanService:238`) | `APPROVE` |
| KP rejects pick | same (tolak) → `kpTolak` | `agihan.kp` | `13→15` | `NotifikasiAgihan::kpTolak` (`:186`) | `REJECT` |

Each transition rotates the single-aktif `sejarah_ppuu` row (`status_rekod` aktif/tutup) — the assignment audit trail (S3).

**Reads:** `senarai/{bucket}` (baru/semasa/semula) + `{kes}/maklumat` carry **only outer `system.view`** — every
staff role reads every branch's queue (read leak, `roles §4.5`; `sejarah_ppuu` has no `CawanganScope`, F4).

#### A-stage 3 dead-ends

- **DEAD-END D1 (CRITICAL, STUCK-1/B-1/G-C1).** `kpLulus` writes numeric `status_agihan='1'` (DITAWARKAN).
  But the lawyer side — `PeguamController::dashboard()`/`tawaran()` — queries the **literal string**
  `where('status_agihan','Ditawarkan')` (verified `PeguamController.php:45,46,70`). A spine-offered case stored
  as `'1'` **never appears** in the lawyer's Tawaran list and cannot be accepted. The `KesDitawarkanMail` email
  *is* sent, but the in-app accept screen is empty. After 7 days the scheduled `agihan:lebih-masa` job
  (`routes/console.php`, daily 07:00 → `LebihMasaService`, which correctly uses `bucketValues([DITAWARKAN])`)
  bounces the unanswered offer to `4` (PPUU re-pick) → the spine re-loops to `1` → forever. **The entire 3-tier
  spine is non-functional end-to-end.** Only the parallel single-step `AgihanController` path (which writes the
  literal string `'Ditawarkan'`) surfaces offers to lawyers today.
  **Fix:** replace the literal `'Ditawarkan'`/`'Diterima'` filters in `tawaran/dashboard/terima` with
  `whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN]))`, and write numeric
  `StatusAgihan::DITERIMA` on accept (not the string `'Diterima'`). Single highest-value fix.

- **DEAD-END D2 (CRITICAL, STUCK-2/B-2/G-M1).** `pengarahTolak` sets `status_agihan='9'` (DITOLAK_PENGARAH).
  `9` is in **no bucket** (`BUCKET_BARU/SEMASA/SEMULA/TARIK_DIRI` all exclude it); `stage()` returns null.
  The case vanishes from every spine queue with **no screen to re-open, re-route, or close it**. The branch
  notification says "kemas kini sistem" but no such screen exists.
  **Fix:** add `9` to a recovery bucket and define a transition `9 → 0` (re-review) or `9 → tutup` (close);
  build the re-route screen.

- **Duplication risk (G-M2/WF-1).** Two assignment front-ends mutate the same `forms.status_agihan`:
  `AgihanController@store` (single-step, string) and the spine (numeric), **with no mutual guard**. A single-step
  assign can clobber a case mid-spine (e.g. at `10`/`13`), discarding the in-flight `sejarah_ppuu` chain.
  Consolidation must pick the spine (`06-redundancy R-WF-01`).

### A-stage 4 — Pengantaraan (mediation) — optional branch

| # Element | Detail |
|---|---|
| 1 Trigger | Officer opens an approved case and records mediation. |
| 2 Responsible | staff (`pengantaraan.manage` is decorative — real gate is `system.view`, `roles §4.4`). |
| 3 Input | mediator, `kaedah_sidang`, party locations, agreement dates (`PengantaraanController::update`). |
| 4 Validation | request validation; **`status_pengantaraan` is free-text convention** (`Ya`/`Tidak`/`Tidak Dirujuk`) with no enum (H-1). |
| 5 Processing | writes mediation fields free-text; `tangguhSidang` inserts `sejarah_sidang` rows (multi-row hearing-postponement log) and sets `status_sidang='Tangguh'`. |
| 6 Approval | none. |
| 7 Status | `forms.status` **unchanged** (still `Diterima`); `status_pengantaraan` set by typing; `status_sidang` only ever set to `Tangguh` (H-2 — `Selesai`/`Gagal` legacy-only, unmodelled). |
| 8 Notification | none. |
| 9 Output | mediation record; feeds `StatistikPengantaraanController` matrices and `WideExport`. |
| 10 Completion | no explicit "mediation done" status; case proceeds to mahkamah or closes. |
| 11 Exception | **A typo in `status_pengantaraan` silently drops the row** from every statistik/SLA gate that filters the exact string (H-1). Several wide-export reason columns degrade to `-Tiada Maklumat-` because the write-path isn't fully ported (**G-M6**). |
| 12 Audit | per `PengantaraanController` writes. |

### A-stage 5 — Kes Mahkamah + Laporan Kes — optional branch

| # Element | Detail |
|---|---|
| 1 Trigger | Officer records litigation (Peringkat 5). |
| 2 Responsible | staff (`mahkamah.manage` decorative). |
| 3 Input | nama_pihak/responden, mahkamah (`id_mahkamah` polymorphic via `jenis_mahkamah_pihak` → `mahkamah_sivil`/`mahkamah_syariah`, no FK, F4), pemfailan, perintah, kos. |
| 4 Validation | `MahkamahRequest`. |
| 5 Processing | `MahkamahController::update` writes court + closure-date fields; `storeLaporan/destroyLaporan` manage `laporan_kes` child (one row per court mention). |
| 6 Approval | none. |
| 7 Status | court fields on `forms`; no S1 status change. |
| 8 Notification | none. |
| 9 Output | court record + multi-row `laporan_kes`. |
| 10 Completion | feeds closure (kos completeness checked at tutup fail). |
| 11 Exception | **`laporan_kes.id_kes` is `varchar(20)` vs `forms.id` int → no FK** (G-M10/F3). Court-report rows can orphan or mis-join; integrity is app-only. |
| 12 Audit | `Audit::log('laporan_kes', …)`. |

### A-stage 6 — Tutup Fail (Peringkat 7)

| # Element | Detail |
|---|---|
| 1 Trigger | Director closes the file. |
| 2 Responsible | `pengarah` / `ketua_pengarah` (`KeputusanController::gate()`, `kes.keputusan`). |
| 3 Input | `sebab_tutup_fail` (incl. Pemindahan / Kesilapan Menjana Nombor Fail), `kos` (max 10). |
| 4 Validation | `$request->validate`; gate re-checked. |
| 5 Processing | `tutupFail` sets `tarikh_tutup_fail=now()`, `sebab_tutup_fail`, `kos`. |
| 6 Approval | director decision. |
| 7 Status | `forms.status` → **`Fail Tutup`** (S1, terminal). **Note:** `status_agihan` is **not** touched — it stays frozen at `2` (DITERIMA); spine const `5` SELESAI is never written by any transition (B-8). |
| 8 Notification | none. |
| 9 Output | case moves to `fail-tutup` list (`KesController`). |
| 10 Completion | **terminal**; `status='Fail Tutup'`. |
| 11 Exception | `forms.status` has **no enum/constraint** (A-1, X-2) and `$guarded=['id']` → a tutup-fail can in principle be written before a keputusan via mass-assignment; only button-visibility enforces order. |
| 12 Audit | `Audit::log('forms', id, UPDATE, "Fail ditutup: {nama}")` (`:91`). |

**Flow A status reconciliation note (A-4 / X-3):** the literal `'Diterima'` means **case approved** on
`forms.status` (S1) but **lawyer accepted assignment** on `forms.status_agihan` (S2) — same string, two columns,
two unrelated meanings → an ETL/reporting trap. Document in any data-warehouse mapping.

---

# FLOW B — Panel-lawyer lifecycle

`public daftar → 3-tier approval → active (provision login) → assignment/tawaran accept/reject → tarik diri → nyahaktif / death-redistribution`

Two status machines: **S5 `butiran_peguam_panel_2.permohonan_status`** (application approval) and
**S7 `peguam_panel.statusAktif`** (active/inactive lifecycle). The assignment/accept-reject portion shares
the agihan S2 column from Flow A.

### B-stage 1 — Public registration (daftar)

| # Element | Detail |
|---|---|
| 1 Trigger | Prospective panel lawyer opens `GET /peguam/daftar` (public, no login). |
| 2 Responsible | external lawyer (public). |
| 3 Input | 7-section wizard, ~70 fields, 18 PDF document types. |
| 4 Validation | `PeguamDaftarController::store`; **throttle 6/1 + honeypot**; session-sum captcha (weak, X-captcha). |
| 5 Processing | writes `butiran_peguam_panel_2..6` + `uploaded_files` (keyed by `kpBaru` string, no FK — F5). |
| 6 Approval | none yet. |
| 7 Status | `permohonan_status='0'` (Baharu, S5); `semakan_ppuu='0'`, `sokonganPengarah='0'`; bidang rows written with `checkbox_value_status='0'` (unnamed dead-state, E-1/STUCK-7). |
| 8 Notification | **none** — legacy emailed a registration-received acknowledgement (gap G-H2). |
| 9 Output | a pending application row visible in staff `permohonan-peguam.index`. |
| 10 Completion | hand-off to 3-tier approval. |
| 11 Exception / **DEAD-END (B-side)** | **No public status lookup** — legacy `semak.php`/`checkstatus.php` let an applicant check `permohonan_status` without login; **2in1 has no equivalent route** (gap **G-H3**). The applicant has no login until approved, so they are blind to progress. **Fix:** add a public "Semak Status Permohonan" by IC. |
| 12 Audit | per `PeguamDaftarController`. |

### B-stage 2 — 3-tier application approval

Source: `PermohonanPeguamController`. The 3-tier latch is enforced by **inline `can()` + `urutan` checks**
(a third gating style — X-1), not route middleware.

| Tier | Route → method | Gate (in-controller `can()`) | Guard | S5 effect |
|---|---|---|---|---|
| PPUU semak | `POST /permohonan-peguam/{butiran}/semak` | `peguam.semak` (`:50`) | — | `semakan_ppuu='1'`, `tarikh_semakan_ppuu` |
| Pengarah sokong | `POST …/sokong` | `peguam.sokong` (`:67`) | requires `semakan_ppuu==='1'` else `urutan` error | `sokonganPengarah='1'`, date |
| KP keputusan (lulus) | `POST …/keputusan` | `peguam.keputusan` (`:88`) | requires `sokonganPengarah==='1'` | `permohonan_status='1'` (Lulus) → **`promote()`** |
| KP keputusan (tolak) | same | `peguam.keputusan` | from endorsed | `permohonan_status='2'`, `sebabTidakDiluluskan`, date |
| Tarik diri (app withdrawal) | `POST …/tarik-diri` | **`auth` only — NO `can()`** (D-1) | none (any state) | `permohonan_status='3'`, `tarikhBatal`, `sebabBatal` |

| # Element | Detail (approval path) |
|---|---|
| 8 Notification | **none at any tier** — legacy emailed the applicant the lulus/tolak decision (gap G-H2). |
| 9 Output (lulus) | `promote()` copies `_2` → `peguam_panel` master (**firm address/poskod/negeri/tel stubbed `'-'`** instead of copied from `_4` — G-M9) and calls `provisionLogin()`. |
| 11 Exception | **D-1:** `tarikDiri` (→`3`) is under-gated (`auth` only) — any authenticated staff can withdraw an application. **D-4:** `3` is reachable from **any** state incl. `1` Lulus → an already-promoted lawyer's application can be flipped to `3` while the `users`/`peguam_panel` rows remain (orphaned active login vs withdrawn application). **§4.1 escalation** (roles): the `urus.pengguna`-derived user CRUD lets non-admins mint an admin — adjacent risk on the same staff surface. |
| 12 Audit | per-tier `Audit::log`. |

#### B-stage 2 dead-end

- **DEAD-END D5 (MED, STUCK-6/D-2/G-C2).** `provisionLogin()` creates the `users` row
  (`is_active=true`, `must_change_password=true`) and returns a temp password that is appended to a **one-time
  flash message** (`PermohonanPeguamController:111`): *"…kata laluan sementara: {temp}…"*. **No email is sent.**
  If the approving clerk misses or closes the banner, the lawyer's login exists but the credential is lost — the
  lawyer cannot authenticate, while the application reads `1` Lulus (looks complete). Locked out until an admin
  manual reset. **Fix:** email the credential (or an activation link) on `provisionLogin`; do not rely on a
  transient flash. (Also affects bulk-migrated accounts: 966 ETL users have `must_change_password` but **no
  delivered initial credential** — G-C2.)

### B-stage 3 — Assignment / tawaran accept/reject (lawyer side)

Source: `PeguamController` (lawyer area, `permission:lawyer.area`). Uses the agihan S2 column.

| # Element | Detail |
|---|---|
| 1 Trigger | Lawyer opens `GET /peguam/tawaran` to see offered cases. |
| 2 Responsible | `peguam` (panel lawyer). |
| 3 Input | accept or reject an offer. |
| 4 Validation | `authorizeCase` = **name-match** `forms.nama_pegawai_yang_dapat_kes` vs `peguam_panel.nama_peguam` (string, fragile — WF-3/G-M9). **No `ensureStatus` from-guard** (B-5) — unlike every spine transition. |
| 5 Processing | `terima`: `status_agihan='Diterima'` (string!). `tolak`: history row `status_agihan='T'`, case `status_agihan='Ditolak'`, clears lawyer. |
| 6 Approval | n/a (lawyer's own decision). |
| 7 Status (S2) | offer `1`(numeric, ideally) → accept `'Diterima'`(→normalises 2) / reject `'Ditolak'`/`'T'` (→4, back to pool). |
| 8 Notification | none to staff on accept/reject. |
| 9 Output | accepted case appears in `peguam.kes` ("kes saya", queried by `status_agihan='Diterima'`). |
| 10 Completion | accept → lawyer handles case until tarik-diri or tutup-fail. |
| 11 Exception / **DEAD-END D1** | **The lawyer never sees spine-offered cases** because the Tawaran list filters the literal string `'Ditawarkan'` while the spine stored numeric `'1'` (STUCK-1/B-1 — detailed in Flow A-stage 3). Accept also has **no from-status guard** (B-5): a lawyer could "accept" a case no longer at offer state. **Fix:** see D1 fix (use `bucketValues`, write numeric, add `ensureStatus`). |
| 12 Audit | `Audit::log('forms', id, UPDATE, "Tawaran kes diterima/ditolak …")` (`PeguamController:85,115`). |

### B-stage 4 — Tarik Diri Mewakili OYD (lawyer withdrawal from a case)

Source: `TarikDiriService` + `TarikDiriController` (fully guarded per stage via `ensureStatus`).
This is the **model workflow** — complete and well-guarded (C-3). Status on S2 + audit row S4.

| Stage | Route → method | Gate | From→To (case S2) | History row |
|---|---|---|---|---|
| Submit | `POST /peguam/kes/{kes}/tarik-diri` → `ppSubmit` | `lawyer.area` + `authorizeCase` + `ensureKesDiterima` (must normalise to `2`) | `2→12` | open aktif row `12`; lawyer picks 1 of 9 Seksyen-24 reasons + optional PDF |
| PPUU | `POST /tarik-diri/{kes}/ppuu` → `ppuuSemak` | `role:ppuu\|koordinator\|admin` | `12→16` | `ulasanPPUU` |
| Pengarah | `POST …/pengarah` → `pengarahSemak` | `role:pengarah\|admin` | `16→17` | `ulasanPengarah` |
| KP lulus | `POST …/kp` → `kpKeputusan(approve)` | `role:ketua_pengarah\|admin` | `17→4` (case re-pool) | row→`6` selesai; clears lawyer; opens new aktif PPUU row |
| KP tolak | same | same | `17→2` (lawyer keeps) | row→`2` selesai |

| # Element | Detail |
|---|---|
| 8 Notification | **none** — legacy generated a **"Surat Batal Penugasan" cancellation-letter PDF and emailed it to the lawyer** on KP approval; 2in1 does the status transitions but generates **no PDF and sends no email** (gap **G-C3**, CRITICAL — loss of an official legal document). **Fix:** port the cancellation-letter PDF + delivery. |
| 11 Exception | **C-1 (intentional ETL trap):** on approval the case → `4` while the audit row → `6` — same event, two different reads. Document it. **C-2:** a case stuck per D1 (never properly accepted) can never reach Tarik Diri (`ensureKesDiterima` requires normalise→`2`). |
| 12 Audit | `Audit::log` per stage; row status_rekod aktif→selesai. |

### B-stage 5 — Nyahaktif / death-redistribution

Source: `PeguamLifecycleService` + `PeguamPanelController`. Status S7.

| # Element | Detail |
|---|---|
| 1 Trigger | Admin/coordinator/director deactivates a lawyer (resignation, death, suspension). |
| 2 Responsible | `role:admin\|koordinator\|pengarah\|ketua_pengarah`. |
| 3 Input | sebab + date (and the implicit "this lawyer is inactive"). |
| 4 Validation | role middleware on `POST /peguam-panel/{peguam}/nyahaktif`. |
| 5 Processing | `statusAktif='0'`+sebab+date; **blocks the lawyer's `users` login**; `redistributeActiveCases()` (transactional) moves every active case (`status_agihan ∈ bucketValues({1,2})` — **correctly expands both encodings**, F-1) to `forms.status_agihan='4'` (PPUU re-pick) and opens a new aktif `sejarah_ppuu` row. |
| 6 Approval | the deactivation itself is the authority action. |
| 7 Status | S7 `1`→`0`; redistributed cases S2 → `4` (BUCKET_SEMULA → recoverable, F-2 — **no dead-end here**). |
| 8 Notification | **none** — legacy emailed a deceased-lawyer reassignment notice to Pengarah/PPUU/KP; 2in1 updates DB but **notifies nobody** (gap G-H2). |
| 9 Output | lawyer marked inactive; all their cases back in the re-pick pool. |
| 10 Completion | reactivate (`aktif-semula`) restores login + `statusAktif='1'` but **does NOT pull redistributed cases back** (by design). |
| 11 Exception | `isAktif()` treats anything except `'0'` as active (legacy NULL=active) — a stray bad value reads as active; no enum constraint (F-3). |
| 12 Audit | per `PeguamLifecycleService`. |

---

# FLOW C — Khidmat Nasihat advisory

`citizen/staff create → saringan → permohonan → slot booking → officer pengesahan → kehadiran → selesai → maklum balas → (optional) buka-kes bridge to litigation`

Two state machines run in lockstep but are **separately guarded and can desync** (G-4):
**S8 `khidmat_nasihat.status_kn`** (DRAF/BAHARU/DALAM_PROSES/SELESAI/BATAL) and
**S9 `temu_janji.status`** (MENUNGGU/DISAHKAN/HADIR/TIDAK_HADIR/SELESAI/BATAL). Full dictionaries in
`status-and-workflow-governance §8`. Two entry points: staff (`KhidmatNasihatController`, `khidmat.manage`)
and citizen (`Awam\PermohonanController`, `awam.portal`) — both share `KhidmatNasihatService`.

### C-stage 1 — Saringan (eligibility screening)

| # Element | Detail |
|---|---|
| 1 Trigger | Applicant/clerk opens `…/permohonan/saringan` before the wizard. |
| 2 Responsible | citizen (`awam.permohonan.saringan`) or `pegawai`/clerk (`khidmat.saringan`, `khidmat.manage`). |
| 3 Input | income (RM 50,000 threshold), sumbangan path, criminal-companion bypass, `tiada_perkara_dikecualikan`. |
| 4 Validation | `saringanSemak`; on pass sets `session('awam_saringan.lulus')=true` (or staff equivalent). |
| 5 Processing | screening only; no DB record yet. |
| 6 Approval | n/a (means-test). |
| 7 Status | none yet. |
| 8 Notification | none. |
| 9 Output | a session flag that **gates wizard entry**. |
| 10 Completion | pass → wizard opens. |
| 11 Exception | server **re-asserts** `session(...lulus)===true` on `store` (403 otherwise, `Awam\PermohonanController:77`) — fixes the legacy client-only gating. Wakil/draft paths bypass. |
| 12 Audit | none (pre-record). |

### C-stage 2 — Permohonan + slot booking

| # Element | Detail |
|---|---|
| 1 Trigger | wizard submit (`POST /awam/permohonan` or staff `POST /khidmat-nasihat`). |
| 2 Responsible | citizen (DIRI_SENDIRI only) or staff (DIRI_SENDIRI / SEBAGAI_WAKIL: PENJARA/JKM/MAHKAMAH). |
| 3 Input | Maklumat → Bayaran → Slot → Perakuan; kategori (3-level KN tree `ref_kategori_kn`→`ref_kategori_kes_kn`→`ref_subkategori_kn`), cawangan, bilik, chosen slot. |
| 4 Validation | `KhidmatNasihatRequest` / `AwamPermohonanRequest`; throttle 10/1; slot must be a real open slot **≥4 working days out** (`SlotAvailabilityService::MIN_WORKING_DAYS=4`, skips weekend/holiday/closure). |
| 5 Processing | `KhidmatNasihatService::bookSlot` locks the slot **`FOR UPDATE`** (race-safe), creates `temu_janji` (MENUNGGU), flags slot taken, back-links `khidmat_nasihat.id_temu_janji ⇄ temu_janji.id_khidmat_nasihat` (two soft-link cols, no FK — F4). `KhidmatBayaran::kira()` computes `jumlah_bayaran` (RM0 free/prison/JKM · RM10 default · RM260 sumbangan) and stores `status_bayaran=0`. |
| 6 Approval | none at booking. |
| 7 Status | S8 `DRAF` (if saved without submit) → **`BAHARU`** on hantar; S9 `temu_janji='MENUNGGU'`. |
| 8 Notification | none (no booking-confirmation email/SMS). |
| 9 Output | a submitted KN with a pending appointment; appears in citizen dashboard + officer worklist. |
| 10 Completion | hand-off to officer pengesahan. |
| 11 Exception / **DEAD-END D7 (payment)** | `status_bayaran` defaults `0` and **no route ever flips it to `1`** — no receipt/`nomborResit` step (G-3/G-M3). The fee is informational; if payment ever becomes a gate, every paid KN reads "unpaid". **C-citizen reschedule mismatch (G-5):** `AwamRescheduleRequest` enforces only `after:today` while `bookSlot` requires a real slot ≥4 working days out → an in-window-but-no-slot date 422s. **Fix:** add a payment-confirmation step; align reschedule lead-time validation with `MIN_WORKING_DAYS`. |
| 12 Audit | per `KhidmatNasihatService`. |

### C-stage 3 — Officer pengesahan (assign → terima/tolak)

Source: `KhidmatProsesController` + `KhidmatProsesService` (`permission:khidmat.proses` — held by pegawai,
koordinator, pengarah; **not ketua_pengarah** — asymmetry, `roles §4.7`). Transitions hard-coded in
`KhidmatProsesService::TEMU_TRANSITIONS`.

| Sub-step | Route → method | S8 effect | S9 effect | Guard |
|---|---|---|---|---|
| Assign PKN | `POST /khidmat-proses/{khidmat}/agih` → `assign` | `BAHARU→DALAM_PROSES` | — | guard `status_kn===BAHARU` |
| Accept appt | `…/temu/terima` | — | `MENUNGGU→DISAHKAN` | |
| Reject appt | `…/temu/tolak` | **unchanged** | `MENUNGGU→BATAL` (records `ulasan_pegawai`) | |

| # Element | Detail |
|---|---|
| 1 Trigger | officer opens branch-scoped worklist (`/khidmat-proses`). |
| 2 Responsible | `pegawai` (PKN) / koordinator / pengarah. |
| 3 Input | which PKN to assign; accept or reject the appointment (+ `ulasan_pegawai` on reject). |
| 4 Validation | `khidmat.proses` permission; branch filter applied **manually** in `KhidmatProsesService:60` (KN has no `CawanganScope` — G-M8/F4); transition from-guards in the service. |
| 5 Processing | `assign` sets `id_pegawai_kn` (authoritative FK on `khidmat_nasihat`, fixes legacy drop bug); accept/reject update `temu_janji`. |
| 6 Approval | officer triage. |
| 7 Status | see table. |
| 8 Notification | none to citizen on assign/accept/reject. |
| 9 Output | a confirmed (DISAHKAN) or cancelled (BATAL) appointment. |
| 10 Completion | DISAHKAN → attendance step. |
| 11 Exception / **DEAD-END D4 (HIGH, STUCK-4/G-2/G-M4)** | `tolak` sets `temu_janji=BATAL` + writes `ulasan_pegawai` but **does NOT change `status_kn`** (stays `BAHARU` or `DALAM_PROSES`). The KN now has **no live appointment and no staff-side rebook path** — it can't progress to SELESAI and isn't BATAL. **Appointment-less orphan.** **Fix:** on `tolak`, explicitly set `status_kn=BATAL` (release slot) **or** open a rebook path to a new `temu_janji MENUNGGU`. |
| 12 Audit | per `KhidmatProsesService`. |

### C-stage 4 — Kehadiran → Selesai

| Sub-step | Route → method | S9 effect | S8 effect | Guard |
|---|---|---|---|---|
| Attendance present | `…/temu/kehadiran` (true) | `DISAHKAN→HADIR` | — | |
| Attendance no-show | `…/temu/kehadiran` (false) | `DISAHKAN→TIDAK_HADIR` | **unchanged** | |
| Complete | `…/temu/selesai` | `HADIR→SELESAI` | **`DALAM_PROSES→SELESAI`** (atomic, both written) | requires temu `HADIR` |

| # Element | Detail |
|---|---|
| 8 Notification | none. |
| 9 Output | a completed advisory session; **unlocks Maklum Balas + Buka Kes**. |
| 10 Completion | `status_kn=SELESAI` (terminal). |
| 11 Exception / **DEAD-END D3 (CRITICAL, STUCK-3/G-1/G-H7)** | `kehadiran(false)` sets temu `TIDAK_HADIR` but leaves `status_kn=DALAM_PROSES` **forever**. `selesai` requires temu `HADIR`, so a no-show **cannot complete, reschedule, or reopen** — it is a permanent hanging state that distorts worklists and KN statistics indefinitely. **Legacy could close a no-show** ("Selesai Tanpa Kehadiran Pelanggan" → `statusKN=SELESAI`); 2in1 cannot. **Fix:** define `TIDAK_HADIR → SELESAI` (close as completed-without-attendance, set `status_kn=SELESAI`) **or** `TIDAK_HADIR → MENUNGGU` (reschedule). |
| 12 Audit | `selesai` writes both statuses atomically (the **only** transition that ties S8↔S9 — G-4). |

### C-stage 5 — Maklum Balas (feedback)

| # Element | Detail |
|---|---|
| 1 Trigger | citizen opens `GET /maklum-balas/{no_permohonan}` after SELESAI. |
| 2 Responsible | citizen (PUBLIC — no auth). |
| 3 Input | how-heard checkboxes, satisfaction rating, suggestions. |
| 4 Validation | server re-checks `status_kn===SELESAI`; throttle 6/1; **DB unique index on `khidmat_nasihat_id`** + app guard (`ER_DUP_ENTRY` swallowed as success — idempotent). |
| 5 Processing | `MaklumBalasController::store` inserts one `maklum_balas` row (real FK — best-modelled new table, F-feedback). |
| 6 Approval | none. |
| 7 Status | presence flag (1 per KN), not a status field. |
| 8 Notification | none. |
| 9 Output | feeds KN reports 2 (Cara Mengetahui) & 7 (Kepuasan). |
| 10 Completion | one feedback per advisory. |
| 11 Exception | clean — no governance issue (H, public-by-design). |
| 12 Audit | row existence is the trail. |

### C-stage 6 — Buka Kes bridge (advisory → litigation)

| # Element | Detail |
|---|---|
| 1 Trigger | officer presses "Buka Kes" on a SELESAI KN with `id_forms===null`. |
| 2 Responsible | `pegawai` (`khidmat.proses`). |
| 3 Input | the completed KN. |
| 4 Validation | guard: `status_kn===SELESAI` **and** `id_forms===null` (prevents double-bridge). |
| 5 Processing | `bukaKes` creates a `forms` litigation row, back-links `khidmat_nasihat.id_forms`, generates `no_fail` (`NoFailGenerator`), `normalizeNokp` for the legacy column. |
| 6 Approval | officer action. |
| 7 Status | new `forms` row enters Flow A at intake (blank `status`). |
| 8 Notification | none. |
| 9 Output | a litigation case seeded from the advisory — **the integration seam ADV→RK, net-new value not in any legacy system** (matrix §F "Retain"). |
| 10 Completion | one case per KN; the KN is now bridged. |
| 11 Exception | staff-created KN (`id_pengguna=null`) is **invisible to the citizen portal** even if the same person later self-registers (G-L6/G-6, by design — flag for consolidation). |
| 12 Audit | per `KhidmatProsesService`. |

---

# FLOW D — Citizen self-service portal (Awam)

`public register (IC) → login → dashboard → own KN journey (create/cancel/reschedule/upload/download)`

Source: `Awam\PublicAuthController`, `Awam\PortalController`, `Awam\PermohonanController`. Gate:
`permission:awam.portal` + `KhidmatNasihatPolicy` ownership. This portal **wraps** Flow C from the citizen's
self-service side; documented here for the citizen-specific elements.

### D-stage 1 — Register + login (IC-based)

| # Element | Detail |
|---|---|
| 1 Trigger | citizen opens `GET /awam/daftar` or `/awam/login` (guest-only group). |
| 2 Responsible | citizen (public). |
| 3 Input | `nokp` (IC) + name + password; login by IC, not email. |
| 4 Validation | session-sum captcha + honeypot; throttle 6/1 (daftar), 10/1 (login); `nokp` unique index. |
| 5 Processing | creates a `users` row `user_type='awam'`, role `awam`; `Auth::attempt` by `nokp`. |
| 6 Approval | none (open self-registration). |
| 7 Status | account active. |
| 8 Notification | none. |
| 9 Output | an authenticated citizen session. |
| 10 Completion | redirect to `awam.dashboard`. |
| 11 Exception / **DEAD-END (citizen reset, D-portal)** | citizens log in by **IC**, but the only password reset is the **email broker** (`/password/forgot`). A citizen who forgot their password and whose account email is blank/unknown has **no reset path** (gap **G-M5**) — the highest-volume user_type has the weakest recovery. **Also G-H5/F6:** the `awam` role itself is seeded by migration `130002`, **absent from `RolePermissionSeeder::ROLES` + `RoleController::SYSTEM_ROLES`**, so an admin can **rename/delete it** via the Peranan UI and silently break the entire portal gate. **Fix:** add an IC+secret reset path; protect the `awam` role. |
| 12 Audit | login per `PublicAuthController`. |

### D-stage 2 — Dashboard + self-service KN actions

| # Element | Detail |
|---|---|
| 1 Trigger | citizen lands on `GET /awam` (`PortalController@index`). |
| 2 Responsible | citizen (owner only). |
| 3 Input | own KN list (paginate 10); actions: create (Flow C), cancel, reschedule, upload, download. |
| 4 Validation | **every action `Gate::authorize`d via `KhidmatNasihatPolicy::owns()`** = `isAwam() && id_pengguna===user.id` (`Awam\PermohonanController` show:107, update:119, download:141, cancel:152, reschedule:165). Download is owner-gated **and** file-scoped to `id_khidmat` (L143). |
| 5 Processing | cancel → `assertCancellable` + `releaseSlot` (`status_kn=BATAL`, temu released); reschedule → release + rebook; upload → `AwamLampiranRequest` (mimes pdf/jpg/png ≤5MB, MIME-derived type, throttle 20/1) to private disk. |
| 6 Approval | none (self-service). |
| 7 Status | cancel → S8 `BATAL`; reschedule → new temu `MENUNGGU`. |
| 8 Notification | none. |
| 9 Output | citizen manages own bookings + documents. |
| 10 Completion | per action. |
| 11 Exception | a staff-created KN (`id_pengguna=null`) **fails the ownership policy** → invisible to the citizen even if it is "theirs" (G-6/G-L6). Reschedule lead-time mismatch (G-5, see Flow C-2). |
| 12 Audit | per controller; owner gate enforced server-side. |

---

# FLOW E — Chat-assisted query

`landing-page widget → Laravel proxy → Python/FastAPI microservice → AI answer`

Source: `ChatbotController@ask` (verified, full read) + Blade widget `partials/chatbot.blade.php`.
This is an **information-only Q&A assistant** — it has **zero access to 2in1 data, records, or roles**
(matrix §I "Missing by design — Retain"). It is the only flow with no DB writes, no status, and no audit.

| # Element | Detail |
|---|---|
| 1 Trigger | a visitor types a question into the chat widget on the public landing page (`welcome.blade.php`). |
| 2 Responsible | public (guest) — also available to any logged-in user the widget is surfaced to. |
| 3 Input | `message` (free text). |
| 4 Validation | `validate(['message'=>'required\|string\|max:1000'])`; route `POST /chatbot/ask` throttled **20/1**; config presence check (returns 503 "belum dikonfigurasi" if `chatbot.url/user/pass` unset). |
| 5 Processing | server-side proxy: (a) `POST {base}/generate_token` with **basic auth** (creds from config, never reach browser) → JWT; (b) `POST {base}/forward_message` with bearer token + `session_id` (`chatbot_sid`, a per-session random int) + `user_name` (`''` for guests to satisfy the bot's Pydantic str type). Returns `content_raw`. |
| 6 Approval | none. |
| 7 Status | **none** — stateless from 2in1's side; conversation memory lives **in-RAM in the Python process** (`user_conversations` dict), lost on restart, not shared across replicas (matrix §I "Improve — persist memory"). |
| 8 Notification | none. |
| 9 Output | a chat reply (`reply` JSON); rendered in the widget. |
| 10 Completion | per message; multi-turn threaded by `session_id`. |
| 11 Exception | every upstream failure is caught and returns a **graceful Malay fallback** with the right HTTP code: token fail → 502 "tidak tersedia"; message fail → 502 "berlaku ralat"; unconfigured → 503; unreachable/throwable → 502 "tidak dapat dihubungi" (logged via `Log::warning/error`). **No dead-end** — the flow always resolves to a user-visible message. |
| 12 Audit | **none** in `audit_trail` (no DB touch). Only `Log::` lines on failure. By design — but note the chat has no record-level trail. |

**Chat operational debt (not a flow dead-end, carried from matrix §I / map 04):** 5 plaintext secrets to
**rotate**, shared Basic creds duplicated across repos, open `/docs`, reflected request headers, and the dead
`news_today` MySQL tool (internal DB unreachable from the cloud host). These are deployment-hardening items,
not process-flow gaps. The widget is currently surfaced **only on the public landing page** (matrix §I "Improve
— decide wider surfacing").

---

## 6. Cross-flow dead-end remediation priority

Ordered by severity × frequency (consistent with `status-and-workflow-governance §11–12` and `03-gap-analysis`):

| Priority | Dead-end | Fix (one-liner) | Effort | Source-of-truth ID |
|---|---|---|---|---|
| 1 | **D1** spine offer invisible to lawyer | `tawaran/dashboard/terima` use `StatusAgihan::bucketValues([DITAWARKAN])`; write numeric on accept; add `ensureStatus` | small (3 query sites) | STUCK-1 / B-1 / G-C1 |
| 2 | **D3** KN no-show hangs | define `TIDAK_HADIR → SELESAI` (or → MENUNGGU); set `status_kn` accordingly | small | STUCK-3 / G-1 / G-H7 |
| 3 | **D2** Pengarah-reject `9` orphan | add `9` to a recovery bucket + a `9 → 0`/`tutup` re-route screen | medium (new screen) | STUCK-2 / B-2 / G-M1 |
| 4 | **D4** KN reject orphan | on `tolak` set `status_kn=BATAL` or open rebook | small | STUCK-4 / G-2 / G-M4 |
| 5 | **D5** credential not delivered | email temp password / activation link in `provisionLogin` | small | STUCK-6 / D-2 / G-C2 |
| 6 | **D7** payment never confirmed | add receipt/payment-confirmation route that flips `status_bayaran` | medium | G-3 / G-M3 |
| 7 | **D6** approved-but-stalled | add an SLA timer / "awaiting agihan" worklist | medium | STUCK-5 / A-2 |
| — | citizen reset / `awam` role unprotected (Flow D) | add IC reset path; add `awam` to protected SYSTEM_ROLES + seeder | small | G-M5 / G-H5 / F6 |

**Structural prerequisites that unblock the cleanest fixes** (per `status-and-workflow-governance §12.2`):
(1) normalise `forms.status_agihan` on **write** (one encoding) + a one-off migration via
`StatusAgihan::LEGACY_STRING_MAP`; (2) promote each status to a PHP 8.3 backed enum with a per-workflow
`transition($from,$event)` table enforced at the model layer (closes the mass-assignment gap X-2); (3) pick a
single assignment front-end (retire single-step `AgihanController@store`, keep `@beban`).

---

## 7. Element-coverage matrix (every flow × 12 elements)

`Y`=present/clean · `~`=present-but-flawed · `N`=absent · `DEAD`=contains a dead-end.

| Element | A Case | B Lawyer | C Advisory | D Portal | E Chat |
|---|:--:|:--:|:--:|:--:|:--:|
| 1 Trigger | Y | Y | Y | Y | Y |
| 2 Responsible user | Y | Y | Y | Y | Y |
| 3 Required input | Y | Y | Y | Y | Y |
| 4 Validation | ~ (no 30-day/menteri) | ~ (name-match, under-gated tarikDiri) | Y (saringan re-asserted) | Y (owner policy) | Y |
| 5 Processing | Y | ~ (firm stub `'-'`) | Y (race-safe slot) | Y | Y (proxy) |
| 6 Approval | Y (director) | Y (3-tier) | Y (officer triage) | N (self-service) | N |
| 7 Status changes | ~ (dual encoding) | DEAD (D1) | DEAD (D3,D4) | ~ | N (stateless) |
| 8 Notification | N (no decision email) | N (no credential/withdrawal email) | N | N | N |
| 9 Output | Y | ~ (no cancellation letter) | Y (+ buka-kes bridge) | Y | Y |
| 10 Completion condition | DEAD (D6 stall) | DEAD (D5 credential) | DEAD (D3,D4,D7) | Y | Y |
| 11 Exception handling | ~ (no enum, mass-assign) | ~ (escalation, under-gate) | ~ (desync S8/S9) | ~ (no IC reset) | Y (graceful fallbacks) |
| 12 Audit trail | Y (record-level) | Y | Y | Y | N (no DB audit) |

---

## 8. File index (verified during this audit)

| Flow | Files |
|---|---|
| A case | `app/Http/Controllers/KesController.php`, `KeputusanController.php` (lulus:28 / tolak:54 / tutupFail:75), `PengantaraanController.php`, `MahkamahController.php`, `CetakanController.php`, `app/Support/NoFailGenerator.php` |
| A/B agihan spine | `app/Http/Controllers/AgihanSpineController.php`, `AgihanController.php` (single-step), `app/Support/AgihanService.php` (kpLulus:145, mail:238), `StatusAgihan.php`, `LebihMasaService.php`, `app/Console/Commands/AgihanLebihMasa.php`, `routes/console.php`, `app/Mail/{KesDitawarkanMail,AgihanTransisiMail,KesLebihMasaMail}.php`, `app/Support/NotifikasiAgihan.php` |
| B lawyer | `PeguamDaftarController.php`, `PermohonanPeguamController.php` (semak:50 / sokong:67 / keputusan:88 / provisionLogin:170, temp-pass flash:111), `PeguamController.php` (dashboard:39 / tawaran:65 / terima:80 — literal `'Ditawarkan'`:46,70), `TarikDiriController.php` + `app/Support/TarikDiriService.php`, `PeguamPanelController.php` + `app/Support/PeguamLifecycleService.php`, `KemaskiniBidangController.php` + `PengkhususanService.php` |
| C advisory | `KhidmatNasihatController.php`, `KhidmatProsesController.php` + `app/Support/KhidmatProsesService.php` (TEMU_TRANSITIONS), `app/Support/{KhidmatNasihatService,SlotAvailabilityService,SlotGenerator,KhidmatBayaran}.php`, `MaklumBalasController.php`, `app/Models/{KhidmatNasihat,TemuJanji}.php` |
| D portal | `app/Http/Controllers/Awam/{PublicAuthController,PortalController,PermohonanController}.php`, `app/Policies/KhidmatNasihatPolicy.php` |
| E chat | `app/Http/Controllers/ChatbotController.php`, `resources/views/partials/chatbot.blade.php`, `routes/web.php:57` |
| Routes | `routes/web.php` (awam 62-99, agihan/spine/tarik-diri/peguam 342-441, khidmat 384-435, maklum-balas 503-508) |

> **Consistency note.** Every dead-end (D1–D7) in this document reuses the `STUCK-n` IDs from
> `status-and-workflow-governance.md §11` and the `G-*` gap IDs from `03-gap-analysis.md`; the matrix
> Action/Status verdicts in `02-feature-comparison-matrix.md` agree. No new findings were introduced —
> this deliverable re-frames the verified findings as end-to-end process flows.
