# Phase 6 — Status & Workflow Governance (Consolidated 2in1)

> **Scope.** Every status/state field across the consolidated 2in1 Laravel app — the legal-aid case
> spine (`forms`), the 3-tier panel-lawyer assignment spine (`forms.status_agihan` + `sejarah_ppuu`),
> Tarik Diri Mewakili OYD withdrawal, lawyer panel-application approval (`permohonan_status`), bidang
> pengkhususan add/drop (`checkbox_value_status`), Khidmat Nasihat (`status_kn`), the appointment engine
> (`temu_janji.status`), and Maklum Balas.
> **Method.** Cross-referenced the nine audit maps (`docs/consolidation-audit/maps/01..09`) AND verified
> every status set, transition guard, and dead-end **directly against source** (`app/Support/*`,
> `app/Http/Controllers/*`, `app/Models/*`, `routes/console.php`). Where the maps and code disagreed,
> **code wins** and the discrepancy is flagged. As of commit `735dd4f`, branch `main`.
> **Read-only audit. Only this file was written.**

---

## 0. Executive summary — what the governance picture looks like

There are **9 distinct status fields** across 6 workflows. They split into two design families:

| Family | Fields | Encoding | Guarded? |
|---|---|---|---|
| **Formal state machines** (named consts + transition guards) | `forms.status_agihan` (spine), Tarik Diri, `permohonan_status`, `checkbox_value_status`, `status_kn`, `temu_janji.status` | numeric or `UPPER_SNAKE` string consts | mostly yes (controller-level `ensureStatus` / service `from`-checks) |
| **Free-text convention statuses** (no enum, written by typing) | `forms.status`, `status_pengantaraan`, `status_sidang`, `status_kes`, `keputusan` | bare Malay strings | no — any value accepted |

**The headline governance problems:**

1. **`forms.status_agihan` carries TWO encodings at once** — the canonical numeric machine (`'1'`,`'2'`,`'4'`…)
   AND legacy string labels (`'Ditawarkan'`,`'Diterima'`,`'Ditolak'`,`'T'`,`'S'`) written by the
   single-step `AgihanController` and the lawyer `terima`/`tolak`. Reconciled only on **read** (`StatusAgihan`).
2. **Spine→lawyer offer hand-off is broken.** The spine writes numeric `'1'`; the lawyer's Tawaran list
   queries the literal string `'Ditawarkan'`. A spine-approved offer never reaches the lawyer. (STUCK)
3. **Two terminal dead-ends with no UI exit:** `status_agihan='9'` (Ditolak Pengarah) and KN-`DALAM_PROSES`
   after a `TIDAK_HADIR` appointment. Both leave a record with no valid next action.
4. **`forms.status` and `forms.status_agihan` both use the literal `'Diterima'`** for unrelated meanings
   (case approved vs lawyer-accepted-assignment) — semantic collision on a shared value across two columns.
5. **Free-text statuses (`status`, `status_pengantaraan`, `status_sidang`) have no DB constraint** — a
   typo silently drops the row from every statistik gate that filters on the exact string.

---

## 1. Unified status model (master inventory)

| # | Field (table.column) | Type | Source of truth | Values | Workflow | Encoding family |
|---|---|---|---|---|---|---|
| S1 | `forms.status` | varchar | controllers (free-text) | `Diterima`, `Ditolak`, `Fail Tutup`, (blank→"baru") | Case lifecycle (peringkat 1–7) | free-text |
| S2 | `forms.status_agihan` | varchar(2) | `App\Support\StatusAgihan` | numeric `0,1,2,4,5,6,7,8,9,10,12,13,14,15,16,17` **+** legacy strings `Ditawarkan,Diterima,Ditolak,Diserah Semula,T,S` | 3-tier assignment spine + single-step + lawyer accept/reject | dual (numeric + string) |
| S3 | `sejarah_ppuu.statusAgihan` + `status_rekod` | varchar | `AgihanService` | mirrors S2 numeric on the active history row; `status_rekod ∈ {aktif, tutup}` | assignment spine audit/active-row | numeric + flag |
| S4 | `sejarah_peguam_panel.status` / `.status_agihan` / `.status_rekod` | varchar | `TarikDiriService`, `LebihMasaService`, lawyer/single-step | TD: `12,16,17,6,2`; lawyer reject: `T`/`Ditolak`; lebih-masa: `7`; single-step semula: `S`; `status_rekod ∈ {aktif, selesai}` | Tarik Diri + reassignment audit | numeric + ad-hoc strings |
| S5 | `butiran_peguam_panel_2.permohonan_status` (+ `semakan_ppuu`, `sokonganPengarah`) | varchar | `PermohonanPeguamController::STATUS` | `0` Baharu, `1` Lulus, `2` Tidak Lulus, `3` Tarik Diri; gates `semakan_ppuu ∈ {0,1}`, `sokonganPengarah ∈ {0,1}` | Panel-lawyer application approval (3-tier) | numeric |
| S6 | `butiran_peguam_panel_6.checkbox_value_status` | int | `ButiranPeguamPanel6` consts | `0` (daftar, unnamed), `1` LEGACY_AKTIF, `2` AKTIF, `3` DROP_MOHON, `4` ADD_MOHON, `7` DROP_DISOKONG, `9` ADD_DISOKONG | Bidang pengkhususan add/drop (2-tier) | numeric |
| S7 | `peguam_panel.statusAktif` (+ `sebabTidakAktif`) | varchar | `PeguamPanel` / `PeguamLifecycleService` | `1` aktif, `0` tidak aktif (NULL treated active) | Lawyer lifecycle + death-redistribution | boolean-ish |
| S8 | `khidmat_nasihat.status_kn` | enum-string | `KhidmatNasihat::STATUS_KN` | `DRAF`, `BAHARU`, `DALAM_PROSES`, `SELESAI`, `BATAL` | Khidmat Nasihat advisory | UPPER_SNAKE string |
| S9 | `temu_janji.status` | enum-string | `TemuJanji::STATUS` + `KhidmatProsesService::TEMU_TRANSITIONS` | `MENUNGGU`, `DISAHKAN`, `HADIR`, `TIDAK_HADIR`, `SELESAI`, `BATAL` | Appointment engine | UPPER_SNAKE string |
| — | `khidmat_nasihat.status_bayaran` (bool) | bool | `KhidmatBayaran` | `0`/`1` (computed, **never flipped to 1**) | Payment | boolean (dead-write) |
| — | `forms.status_pengantaraan` | varchar | typing (`PengantaraanRequest`) | `Ya`/`Tidak`/`Tidak Dirujuk` (convention) | Mediation | free-text |
| — | `forms.status_sidang` | varchar | `PengantaraanController::tangguhSidang` | only `Tangguh` written; `Selesai`/`Gagal` legacy-only | Hearing | free-text |
| — | `laporan_kes.status_kes` | varchar | typing | free court-report status | Court report | free-text |
| — | `maklum_balas` (existence + unique) | row | `MaklumBalasController` | submitted / not-submitted (1 per KN, unique index) | Feedback | presence flag |
| — | `posters.status` / `pegawai_jbg.status_aktif` / `ref_*.aktif*` | varchar/bool | respective CRUD | aktif/tidak aktif | reference-data activation | boolean |

> **Naming note for consolidation:** `status`, `status_agihan`, `status_rekod`, `status_pengantaraan`,
> `status_sidang`, `status_kn`, `statusAktif`, `statusAgihan` (camel, on `sejarah_ppuu`), `status_kes`,
> `status_bayaran` are **10 different `status*` columns** with inconsistent casing and no shared convention.

---

## 2. WORKFLOW A — Case lifecycle (`forms.status`, S1)

**Field:** `forms.status` (varchar, **no enum/check constraint**, free-text). Source: `KeputusanController`,
`KesController`. Display fallback `"baru"` when blank. `KesController::index` derives the filter dropdown from
`DISTINCT status` (data-driven, not enum).

### Status dictionary

| Value | Meaning | Who assigns | Entry condition | Action required next | Exit / completion |
|---|---|---|---|---|---|
| *(blank)* → shown "baru" | Application just intaked (Peringkat 1) | `KesController::store` (any `kes.create` role) | `POST /kes` succeeds | Pengarah/KP decision (lulus/tolak) | leaves blank on first keputusan |
| `Diterima` | Application approved (Peringkat 2 lulus) | `KeputusanController::lulus` (`kes.keputusan` = pengarah/ketua_pengarah) | `POST /kes/{kes}/lulus` | optional onward edits (agihan, pengantaraan, mahkamah) then tutup fail | `tarikh_tutup_fail` set + `status='Fail Tutup'` |
| `Ditolak` | Application rejected (Peringkat 2 tolak) | `KeputusanController::tolak` (same gate) | `POST /kes/{kes}/tolak`; writes `reason`, `tarikh_pemakluman` | none (terminal) | terminal |
| `Fail Tutup` | File officially closed (Peringkat 7) | `KeputusanController::tutupFail` (same gate) | `POST /kes/{kes}/tutup-fail`; writes `sebab_tutup_fail`, `kos`, `tarikh_tutup_fail` | none (terminal) | terminal; appears in `fail-tutup` list |

### Transition table

| From | Event → route | Gate | To | Side-effects |
|---|---|---|---|---|
| *(blank)* | `kes.lulus` | `kes.keputusan` | `Diterima` | `keputusan='Diluluskan'`, `diterima='Ya'`, dates; Audit APPROVE |
| *(blank)* | `kes.tolak` | `kes.keputusan` | `Ditolak` | `keputusan='Ditolak'`, `diterima='Tidak'`, `reason`; Audit REJECT |
| `Diterima` | `kes.tutupfail` | `kes.keputusan` | `Fail Tutup` | `tarikh_tutup_fail`, `sebab_tutup_fail`, `kos`; Audit UPDATE |
| `Diterima` | (any of pengantaraan / mahkamah / agihan edits) | `system.view` + inline | *(status unchanged)* | free-text column writes only — **status does not advance** |

### Governance flags (A)

| ID | Sev | Finding |
|---|---|---|
| A-1 | **HIGH** | **No state machine, no enum, no constraint.** `forms.status` accepts any string. The 7-peringkat lifecycle is enforced only by which buttons appear; nothing prevents a tutup-fail before a keputusan, or writing an arbitrary status via mass-assignment (`$guarded=['id']`). |
| A-2 | **MED** | **Approved-but-stuck.** After `Diterima`, every onward step (agihan/pengantaraan/mahkamah) is optional free-text; a case can sit at `Diterima` indefinitely with no SLA flag or forced next step. Not a dead-end (closable) but a **silent stall**. |
| A-3 | **MED** | **Computed sub-statuses disagree with stored status.** "Selesai / Pemfailan Selesai / Belum Difailkan" exist only in `WideExport::statusPemfailan()` and `LaporanPenuhController::statusFilter()` — derived from `status` + `tarikh_selesai` + `tarikh_pemfailan_kes`. The on-screen status and the report status can diverge for the same row. |
| A-4 | **MED** | **`Diterima` value collision with `forms.status_agihan`.** The literal `'Diterima'` means "case approved" on `forms.status` but "lawyer accepted assignment" on `forms.status_agihan` (S2). Same string, two columns, two unrelated meanings — a reporting/ETL trap. |
| A-5 | LOW | `Ditolak` is terminal with **no reopen path** — correct by design, but there is no "batal/pembatalan" status surfaced (legacy `Dibatalkan`/`taraf=Tamat` not modelled here). |

---

## 3. WORKFLOW B — 3-tier assignment spine (`forms.status_agihan`, S2/S3)

**Source of truth:** `App\Support\StatusAgihan` (numeric consts + `LABELS` + `LEGACY_STRING_MAP` + buckets).
**Engine:** `AgihanService` (transitions, `sejarah_ppuu` single-aktif-row rotation).
**Host page + guards:** `AgihanSpineController` (`ensureStatus()` = `abort_unless(normalise(status) IN allowed, 422)`).
**Competing writers:** `AgihanController` (single-step, writes string `'Ditawarkan'`/`'S'`) and `PeguamController`
(lawyer accept/reject, writes string `'Diterima'`/`'Ditolak'`/`'T'`). `LebihMasaService` (scheduled timeout).

### Status dictionary (numeric machine)

| Code | Const | Meaning | Assigned by | Entry condition | Allowed next | Required action | Exit |
|---|---|---|---|---|---|---|---|
| `0` | BARU_PENGARAH | New case awaiting Pengarah | (case routed to PP) | case agih to peguam panel | `8` (terima) / `9` (tolak) | Pengarah review | → 8 or 9 |
| `8` | DIAGIH_PPUU | Awaiting PPUU lawyer pick | `AgihanService::pengarahTerima` | from `0` | `10` | PPUU picks lawyer | → 10 |
| `10` | SOKONGAN_PENGARAH | Awaiting Pengarah endorsement of pick | `ppuuPilih` | from `8`/`4`/`15` | `13` (sokong) / `4` (tidak) | Pengarah endorse | → 13 or 4 |
| `13` | KELULUSAN_KP | Awaiting KP final approval | `pengarahSokong` | from `10` | `1` (lulus) / `15` (tolak) | KP decision | → 1 or 15 |
| `1` | DITAWARKAN | Offered to panel lawyer | `kpLulus` | from `13` | `2` / `4` (lebih-masa) / lawyer reject | **lawyer accept/reject** | → 2 (accept) or back to pool |
| `2` | DITERIMA | Lawyer accepted — case active | lawyer `terima` **(string)** | from `1` | `12` (tarik diri) / `5` (selesai) | handle case | → 12 or 5 |
| `4` | PPUU_AGIH_SEMULA | Bounced to PPUU re-pick | `pengarahTidakSokong`, `kpKeputusan(TD)`, `LebihMasa`, death-redistribute | many | `10` (re-pick) | PPUU re-picks | → 10 |
| `15` | KELULUSAN_KP_SEMULA | Re-submitted to KP after KP-reject | `kpTolak` | from `13` | `10` (re-pick) | PPUU re-picks | → 10 |
| `9` | DITOLAK_PENGARAH | Pengarah rejected new case | `pengarahTolakBaru` | from `0` | **none built** | (manual branch action) | **DEAD-END** |
| `5` | SELESAI | Case closed | (no spine transition writes 5) | — | none | — | terminal |
| `7` | LEBIH_MASA | Auto-reassign marker | only on `sejarah_peguam_panel` history row | offer unanswered 7d | (case itself goes to `4`) | — | history-only |
| `14` | TOLAK_KE_CAWANGAN | KP reject to branch | **never written** | — | — | — | **DEAD CONSTANT** |

### Tarik-Diri sub-states (live on the same column, see Workflow C): `12, 16, 17, 6`.

### Transition table (spine, all guarded by `ensureStatus`)

| # | Route → service method | Gate | From → To | Side-effects |
|---|---|---|---|---|
| B1a | `pengarah-terima` → `pengarahTerima` | `agihan.pengarah` | `0 → 8` | close aktif `sejarah_ppuu`; open new aktif row w/ `idPPUU`; notify PPUU |
| B1b | `pengarah-tolak` → `pengarahTolakBaru` | `agihan.pengarah` | `0 → 9` | notify branch; **no further path** |
| B2 | `ppuu-pilih` → `ppuuPilih` | `agihan.ppuu` | `8/4/15 → 10` | set pick (A/B), `nama_peguampanel`, `kpBaru_peguampanel`, `ulasanPPUU`; notify Pengarah |
| B3a | `pengarah-keputusan=sokong` → `pengarahSokong` | `agihan.pengarah` | `10 → 13` | `status_sokonganPengarah='0'`; forward KP |
| B3b | `pengarah-keputusan=tidak` → `pengarahTidakSokong` | `agihan.pengarah` | `10 → 4` | close aktif row; log `sejarah_peguam_panel`; open new aktif PPUU row |
| B4a | `kp-keputusan=lulus` → `kpLulus` | `agihan.kp` | `13 → 1` | set `forms.nama_pegawai_yang_dapat_kes`+`agih_kepada`; email offer |
| B4b | `kp-keputusan=tolak` → `kpTolak` | `agihan.kp` | `13 → 15` | close aktif row; open new aktif PPUU row; notify |
| B5 | lawyer `terima` (`PeguamController`) | `lawyer.area` + name-match | `1 → 2` **(writes string `'Diterima'`)** | **NO `ensureStatus` guard** |
| B6 | lawyer `tolak` | `lawyer.area` + name-match | `1 → 4` (string `'Ditolak'`→4) | log history `status='T'`; clear lawyer; **NO guard** |
| B7 | `agihan:lebih-masa` (scheduled daily 07:00) → `LebihMasaService` | system (no auth) | `1 → 4` | history row `status='7'`; clear assignee; notify Pengarah |

### Governance flags (B) — most critical workflow

| ID | Sev | Finding |
|---|---|---|
| **B-1** | **CRITICAL (STUCK)** | **Spine→lawyer offer hand-off is broken.** `kpLulus` sets `status_agihan='1'` (numeric DITAWARKAN). But `PeguamController::tawaran()`/`dashboard()` query `where('status_agihan','Ditawarkan')` — the **literal string**, NOT `StatusAgihan::bucketValues([DITAWARKAN])`. A case offered through the spine **never appears** in the lawyer's Tawaran list → the lawyer can never accept it → the offer hangs at `1` until the 7-day `LebihMasa` job bounces it to `4`, then it re-loops the spine forever. **Only the single-step `AgihanController` (which writes the literal `'Ditawarkan'`) surfaces offers to lawyers.** This makes the entire 3-tier spine non-functional end-to-end. |
| **B-2** | **CRITICAL (DEAD-END)** | **Status `9` (Ditolak Pengarah) has no exit.** `pengarahTolakBaru` sets `9`; `9` is in **no bucket** (`BUCKET_BARU/SEMASA/SEMULA/TARIK_DIRI` all exclude it) and `stage()` returns `null` for it. The case vanishes from every spine queue with no screen to re-open, re-route, or close it. **STUCK record.** |
| **B-3** | **HIGH** | **Dual encoding on one column, no write normalisation.** `forms.status_agihan` simultaneously holds numeric (`'1'`,`'2'`,`'4'`) from the spine and strings (`'Ditawarkan'`,`'Diterima'`,`'Ditolak'`,`'T'`,`'S'`) from `AgihanController`/`PeguamController`. Reconciled only at read (`StatusAgihan::normalise/label/bucketValues`). No migration converges legacy rows; no guard stops a string-write from clobbering a mid-spine numeric state. |
| **B-4** | **HIGH** | **Two parallel assignment front-ends mutate the same case with no mutual guard.** `AgihanController@store` (single-step, `agihan.manage`) can overwrite a case that is mid-spine (e.g. at `10`/`13`) with `status_agihan='Ditawarkan'`, silently discarding the in-flight `sejarah_ppuu` chain. Pick **one** path in consolidation. |
| **B-5** | **MED** | **Lawyer `terima`/`tolak` have no `ensureStatus` guard.** Unlike every spine transition, `PeguamController::terima/tolak` only check `authorizeCase` (name match). A lawyer could accept a case that is no longer at `1` (e.g. already withdrawn/closed) because there is no from-status assertion. |
| B-6 | MED | **`status_agihan` is `varchar(2)`** — fits `'Ditawarkan'` (10 chars) only by MySQL silently truncating in non-strict mode, or rejecting in strict. The single-step writes `'Ditawarkan'`/`'Diterima'`/`'Diserah Semula'` which exceed 2 chars. Schema/value mismatch — verify column width vs the strings actually written. |
| B-7 | LOW | **Status `14` (TOLAK_KE_CAWANGAN) is a dead constant** — labelled, never written (KP reject goes to `15`). Retained for read-compat only. |
| B-8 | LOW | **Status `5` (SELESAI) is never written by any spine transition** — there is no "tutup agihan" action; case closure happens on `forms.status='Fail Tutup'` (S1), leaving `status_agihan` frozen at `2`. The `BUCKET_SEMASA` includes `5` but nothing produces it. |
| B-9 | LOW | **History-row status `'T'` and `'S'`** (lawyer reject / single-step semula) are written to `sejarah_peguam_panel.status_agihan` but are **not in `LEGACY_STRING_MAP`** → `StatusAgihan::label('T')` returns `'T'` (un-decoded) in any history display. |

> **Map correction:** Maps 05/06 stated "no scheduled command flips stale offers — offers can sit at `1`
> indefinitely (stub/missing automation)." **This is WRONG as of `735dd4f`.** `app/Console/Commands/AgihanLebihMasa.php`
> (signature `agihan:lebih-masa`) IS scheduled in `routes/console.php` (`->dailyAt('07:00')->withoutOverlapping()`)
> and `LebihMasaService::run()` IS implemented. The timeout automation exists. **However**, because of B-1,
> it fires on every spine-offered case (which the lawyer can never see/accept), turning a safety net into an
> infinite re-assignment loop — the spine offer can never resolve to `2`.

---

## 4. WORKFLOW C — Tarik Diri Mewakili OYD (withdrawal, S2/S4)

**Source:** `TarikDiriService` (state machine over `forms.status_agihan` + active `sejarah_peguam_panel` row).
**Guard:** `TarikDiriController::ensureStatus($kes, $expected)` (single expected from-status per stage).
**Active record:** the `sejarah_peguam_panel` row with `status_rekod='aktif'` and status ∈ `{12,16,17}`.

### Status dictionary + transition table

| Stage | Route → method | Gate | From → To (case) | History row | Required action |
|---|---|---|---|---|---|
| Submit | `POST /peguam/kes/{kes}/tarik-diri` → `ppSubmit` | `lawyer.area` + `authorizeCase` + `ensureKesDiterima` (status must normalise to `2`) | `2 → 12` | open aktif row `status=status_agihan=12` | lawyer picks reason (1 of 9), optional PDF |
| PPUU | `POST /tarik-diri/{kes}/ppuu` → `ppuuSemak` | `role:ppuu\|koordinator\|admin` | `12 → 16` | `ulasanPPUU` | PPUU review |
| Pengarah | `POST /tarik-diri/{kes}/pengarah` → `pengarahSemak` | `role:pengarah\|admin` | `16 → 17` | `ulasanPengarah` | Pengarah review |
| KP lulus | `POST /tarik-diri/{kes}/kp` → `kpKeputusan(approve)` | `role:ketua_pengarah\|admin` | `17 → 4` (case) | row → `6` `selesai`, `keputusan_tarikDiriHQ='0'` | clears lawyer; closes aktif `sejarah_ppuu`; opens new aktif PPUU row → re-assign pool |
| KP tolak | same | same | `17 → 2` (case) | row → `2` `selesai`, `keputusan_tarikDiriHQ='1'` | lawyer keeps case |

### Governance flags (C)

| ID | Sev | Finding |
|---|---|---|
| C-1 | **MED** | **Intentional case/history status divergence.** On approval, case → `4` (PPUU_AGIH_SEMULA) while the audit row → `6` (TARIK_DIRI_LULUS). Documented in code, not a bug, but an **ETL trap**: a query reading `forms.status_agihan` sees `4`, a query reading the history row sees `6` — they describe the same event. |
| C-2 | LOW | **`ensureKesDiterima` couples to S2's dual encoding.** Submit requires `normalise(status_agihan)===DITERIMA('2')`. A case the lawyer "accepted" via the broken string path holds `'Diterima'` which normalises to `2` — works. But a case never properly accepted (stuck per B-1) can never reach Tarik Diri. Downstream effect of B-1. |
| C-3 | LOW | This chain **is** fully guarded (`ensureStatus` per stage) and complete — the model workflow other state machines should follow. |

---

## 5. WORKFLOW D — Panel-lawyer application approval (`permohonan_status`, S5)

**Source:** `PermohonanPeguamController::STATUS = ['0'=>Baharu,'1'=>Lulus,'2'=>Tidak Lulus,'3'=>Tarik Diri]`.
**Sub-gates on the row:** `semakan_ppuu ∈ {0,1}`, `sokonganPengarah ∈ {0,1}` (the 3-tier sequence latches).

### Status dictionary + transition table

| Status / sub-gate | Meaning | Who assigns | Entry condition | Next | Action |
|---|---|---|---|---|---|
| `0` Baharu | New application from public `daftar` | `PeguamDaftarController` (public) | form submitted | semak | — |
| `semakan_ppuu=1` | PPUU vetted | `semak` (inline `can('peguam.semak')`) | from `0` | sokong | sets `tarikh_semakan_ppuu` |
| `sokonganPengarah=1` | Pengarah endorsed | `sokong` (inline `can('peguam.sokong')`) | **guard:** `semakan_ppuu==='1'` else error `urutan` | keputusan | sets `tarikh_sokonganPengarah` |
| `1` Lulus | Approved → promote to panel | `keputusan(lulus)` (inline `can('peguam.keputusan')`) | **guard:** `sokonganPengarah==='1'` | terminal | `promote()` → `peguam_panel` + `users` login (temp password) |
| `2` Tidak Lulus | Rejected | `keputusan(tolak)` (same gate) | from endorsed | terminal | `sebabTidakDiluluskan`, `tarikhTidakDiluluskan` |
| `3` Tarik Diri | Application withdrawn | `tarikDiri` (**`auth` only, NO `can`**) | any | terminal | `tarikhBatal`, `sebabBatal` |

### Governance flags (D)

| ID | Sev | Finding |
|---|---|---|
| D-1 | **HIGH** | **`tarikDiri` (app withdrawal → status `3`) is under-gated** — only `auth`, no `can()`/`role:`. Any authenticated staff (even a `pegawai` with no approval rights) can mark a panel application Tarik Diri. Should require an approval permission. |
| D-2 | MED | **Credential delivery gap (not a status bug but a workflow dead-end).** On `promote()` the lawyer's temp password is shown **once** in a flash message; **no email**. If staff miss the banner, the lawyer's `users` row exists (`is_active=true`) but they can never obtain credentials → locked out until admin reset. The application status reads `1` Lulus (looks complete) while the lawyer cannot log in. |
| D-3 | LOW | The 3-tier latch (`semakan_ppuu`→`sokonganPengarah`→`permohonan_status`) is enforced by **inline `can()` + `urutan` checks**, not by middleware — consistent but a third gating style (see §10). |
| D-4 | LOW | Status `3` (Tarik Diri) is reachable from **any** state (no from-guard), so an already-`Lulus` lawyer's application could be flipped to `3` while the promoted `users`/`peguam_panel` rows remain — orphaned active login vs withdrawn application. |

---

## 6. WORKFLOW E — Bidang pengkhususan add/drop (`checkbox_value_status`, S6)

**Source:** `ButiranPeguamPanel6` consts. **Engine:** `PengkhususanService`. **Staff guard:** `role:pengarah|admin`, `role:ketua_pengarah|admin`.

### Status dictionary + transition table

| Code | Const | Meaning | Assigned by | Next | Action |
|---|---|---|---|---|---|
| `0` | *(unnamed)* | Selected at registration (daftar) | `PeguamDaftarController` | — | **never surfaced** in kemaskini queue (not in `PENGARAH_PENDING`) |
| `1` | LEGACY_AKTIF | Approved at registration | promotion/legacy | `3` (drop request) | active in assignment matching |
| `2` | AKTIF | Approved & active | `kpDecide(add, true)` | `3` (drop request) | active |
| `4` | ADD_MOHON | Lawyer requested addition | `requestAdd` (`lawyer.area`) | `9` / deleted | Pengarah review |
| `3` | DROP_MOHON | Lawyer requested removal | `requestDrop` (owner; **blocked if active case uses category**) | `7` / revert AKTIF | Pengarah review |
| `9` | ADD_DISOKONG | Pengarah backed addition | `pengarahReview(add,true)` | `2` (KP lulus) / deleted (KP tolak) | KP decision |
| `7` | DROP_DISOKONG | Pengarah backed removal | `pengarahReview(drop,true)` | row DELETED (KP lulus) / revert AKTIF | KP decision |

Transitions: `4→9` / `3→7` (Pengarah sokong); `4`→deleted / `3`→AKTIF (Pengarah tolak); `9→2` / `7`→deleted (KP lulus); both→AKTIF/deleted (KP tolak).

### Governance flags (E)

| ID | Sev | Finding |
|---|---|---|
| E-1 | LOW | **`checkbox_value_status=0` is an unnamed mild dead-state.** Registration writes `0`; it is in no named const, not in `PENGARAH_PENDING`/`KP_PENDING`/`AKTIF_STATES`. A `0` row is "selected at daftar" but can never be acted on through kemaskini and is not counted active in `AKTIF_STATES={1,2}` — so a freshly-registered area sits in limbo until promotion writes `1`/`2`. Confirm promotion path actually upgrades `0`→`2`. |
| E-2 | LOW | Drop-request guard `hasActiveCaseInCategory()` matches **lawyer name + `forms.kategori_kes LIKE %category%`** — name-based + substring LIKE; fragile (same as the project-wide name-linkage smell). |
| E-3 | LOW | This chain is otherwise well-guarded (owner check + state-pending sets). Add/drop deletes rows on rejection rather than parking them — no audit trail of rejected requests beyond `audit_trail`. |

---

## 7. WORKFLOW F — Lawyer lifecycle + death-redistribution (`statusAktif`, S7)

**Source:** `peguam_panel.statusAktif` (`'1'`/`'0'`, NULL=active via `isAktif()`). **Engine:** `PeguamLifecycleService`.

| Action | Route → method | Gate | Effect on status | Cascade |
|---|---|---|---|---|
| Deactivate | `POST /peguam-panel/{peguam}/nyahaktif` | `role:admin\|koordinator\|pengarah\|ketua_pengarah` | `statusAktif='0'`+sebab+date | block `users` login; **redistribute** every active case (`status_agihan ∈ {1,2}+aliases`) → `forms.status_agihan='4'`, open new aktif `sejarah_ppuu` |
| Reactivate | `POST /peguam-panel/{peguam}/aktif-semula` | same | `statusAktif='1'`, clear sebab/date | re-enable login; does **NOT** pull redistributed cases back (by design) |

### Governance flags (F)

| ID | Sev | Finding |
|---|---|---|
| F-1 | LOW | `redistributeActiveCases` matches active cases by `status_agihan ∈ bucketValues({1,2})` — correctly expands string aliases, so it catches both encodings. **Good** — this is the one place that does it right; the lawyer-offer queries (B-1) should copy this pattern. |
| F-2 | LOW | A case redistributed via death-redistribution lands at `4` (PPUU_AGIH_SEMULA) which IS in `BUCKET_SEMULA` → recoverable. No dead-end here. |
| F-3 | INFO | `isAktif()` treats anything except `'0'` as active (legacy NULL=active) — a stray bad value (e.g. `'2'`) would read as active. No enum constraint. |

---

## 8. WORKFLOW G — Khidmat Nasihat advisory (`status_kn`, S8) + Appointment engine (`temu_janji.status`, S9)

These two state machines run in lockstep but are **separately guarded** and can desynchronise.

### G1 — `status_kn` (`KhidmatNasihat::STATUS_KN`)

| Status | Meaning | Assigned by | Entry | Allowed next | Required action | Exit |
|---|---|---|---|---|---|---|
| `DRAF` | Draft (no slot) | citizen/staff create | save w/o submit | `BAHARU` (hantar) / `BATAL` / deleted | edit (only DRAF editable) | submit |
| `BAHARU` | Submitted + slot booked (temu `MENUNGGU`) | `store`(hantar) | saringan passed + slot booked | `DALAM_PROSES` (assign) / `BATAL` (cancel) | officer assign PKN | → DALAM_PROSES |
| `DALAM_PROSES` | PKN assigned | `KhidmatProsesService::assign` (`khidmat.proses`; guard `status_kn===BAHARU`) | from `BAHARU` | `SELESAI` (after attendance) / `BATAL` | run appointment → attendance → complete | → SELESAI |
| `SELESAI` | Session completed | `selesai` (guard temu `HADIR`) | temu HADIR→SELESAI | terminal | unlocks Maklum Balas + Buka Kes | terminal |
| `BATAL` | Cancelled (slot released) | citizen `cancel` / (not set by `tolak`) | cancellable temu | terminal | — | terminal |

### G2 — `temu_janji.status` (`KhidmatProsesService::TEMU_TRANSITIONS`)

| From | Event | Gate | To |
|---|---|---|---|
| `MENUNGGU` | `terima` | `khidmat.proses` | `DISAHKAN` |
| `MENUNGGU` | `tolak` (records `ulasan_pegawai`) | `khidmat.proses` | `BATAL` |
| `DISAHKAN` | `kehadiran(true)` | `khidmat.proses` | `HADIR` |
| `DISAHKAN` | `kehadiran(false)` | `khidmat.proses` | `TIDAK_HADIR` |
| `HADIR` | `selesai` (also `status_kn=SELESAI`) | `khidmat.proses` | `SELESAI` |
| any cancellable | citizen cancel / reschedule | owner | `BATAL` (release) / re-book |

### Governance flags (G) — second-most-critical workflow

| ID | Sev | Finding |
|---|---|---|
| **G-1** | **CRITICAL (STUCK)** | **`TIDAK_HADIR` is a terminal dead-end on the KN side.** When `kehadiran(false)` sets temu `TIDAK_HADIR`, `status_kn` stays `DALAM_PROSES` **forever**. `selesai` requires temu `HADIR` (so it can't complete), there is no reschedule-after-no-show, no auto-close, no reopen. The KN record is permanently stuck `DALAM_PROSES` with a dead appointment. **STUCK record.** Verified in `KhidmatProsesService` — no transition handles `TIDAK_HADIR`. |
| **G-2** | **HIGH (STUCK)** | **`tolak` (reject appointment) orphans the KN.** `tolak` sets temu `BATAL` and writes `ulasan_pegawai`, but **does NOT change `status_kn`** (stays `BAHARU` or `DALAM_PROSES`). The KN now has no live appointment and there is **no staff-side rebook path** — it can't progress to SELESAI and isn't BATAL. Confirmed: the `tolak` branch only updates `ulasan_pegawai`. **STUCK / appointment-less.** |
| G-3 | **MED** | **Payment never confirmed.** `jumlah_bayaran` is computed and stored; `status_bayaran` defaults `false` and **no route/controller ever flips it to `true`**. The fee is informational only — there is a `status_bayaran` field with a single dead value. If payment becomes a gate, every paid KN is permanently "unpaid". |
| G-4 | MED | **`status_kn` / `temu_janji.status` can desync.** They are updated in separate service methods; only `selesai` writes both atomically. `assign` advances KN without touching temu; `terima`/`tolak`/`kehadiran` advance temu without touching KN (except the two stuck cases above). No invariant ties them. |
| G-5 | LOW | **Citizen reschedule lead-time mismatch.** `AwamRescheduleRequest` enforces only `after:today`; `bookSlot` then requires a real open slot ≥4 working days out → an in-window-but-no-slot date 422s. UX-rough, not a data hole. |
| G-6 | LOW | **Staff-created KN (`id_pengguna=null`) invisible to citizen portal** (policy requires ownership). By design; flag for consolidation so a walk-in applicant who later self-registers doesn't see "their" record. |

---

## 9. WORKFLOW H — Maklum Balas (feedback) + free-text statuses

- **Maklum Balas** is a presence flag, not a status field: one row per KN (DB unique index on `khidmat_nasihat_id`
  + app guard; `ER_DUP_ENTRY` swallowed as success). Gated by `status_kn===SELESAI` (server re-checked). **No
  governance issue** — clean, idempotent, public-by-design.
- **`forms.status_pengantaraan`** (`Ya`/`Tidak`/`Tidak Dirujuk`), **`forms.status_sidang`** (only `Tangguh`
  ever written; `Selesai`/`Gagal` are legacy-only), **`laporan_kes.status_kes`** — all **free-text, no enum**.

### Governance flags (H)

| ID | Sev | Finding |
|---|---|---|
| H-1 | MED | **Convention-only `status_pengantaraan` silently drops rows on typo.** Every pengantaraan statistik/SLA gate filters the exact string (`status_pengantaraan='Ya'`). A `'ya'`/`' Ya'`/`'YA'` value vanishes from `penugasan-pengantaraan`, SLA `fail-terlibat`, and `PengantaraanMatrix`. No constraint, no normalisation. |
| H-2 | LOW | **`status_sidang` only ever writes `Tangguh`.** The legacy `Selesai`/`Gagal` outcomes are not produced by `PengantaraanController` — the hearing-outcome state is effectively unmodelled; downstream reports that expect `Selesai` degrade to NO_DATA. |
| H-3 | LOW | `laporan_kes.status_kes` is uncontrolled free text per court mention — no governance, low risk (display-only). |

---

## 10. Cross-cutting governance findings

| ID | Sev | Finding |
|---|---|---|
| X-1 | **HIGH** | **Three gating mechanisms, inconsistently applied.** Route `permission:` (spine, KN), inline `->can()` (panel-application approval), and `role:` (tarik-diri, kemaskini-bidang, lifecycle). Several declared permissions are seeded but **never used as middleware** (`kes.keputusan`, `peguam.semak/sokong/keputusan`, etc.) — gating happens inside the controller. A status transition's true gate cannot be read off the route table alone; each controller must be audited. |
| X-2 | **HIGH** | **No DB-level enum/check constraint on ANY status column.** `forms.status`, `status_agihan`, `status_pengantaraan`, `status_kn`, `temu_janji.status`, `permohonan_status`, `checkbox_value_status`, `statusAktif` are all bare varchar/int. The only structural guard anywhere is the `maklum_balas` unique index. `$guarded=['id']` on `Form` means any status is mass-assignable. |
| X-3 | **MED** | **Value collisions across columns.** `'Diterima'` means two unrelated things on `forms.status` vs `forms.status_agihan` (A-4). `'2'` means DITERIMA (agihan), AKTIF (pengkhususan), and Tidak-Lulus (permohonan_status) on three different columns. Codes are context-dependent — safe in code (separate consts) but a reporting/data-warehouse hazard. |
| X-4 | **MED** | **Read-time reconciliation, not write-time normalisation.** `StatusAgihan::normalise/bucketValues` is the load-bearing fix for the dual encoding (B-3). Any query that forgets to call `bucketValues()` (like the lawyer Tawaran list, B-1) silently mis-filters. This is brittle by construction. |
| X-5 | LOW | **`status_rekod` flags (`aktif`/`tutup`/`selesai`) on `sejarah_ppuu`/`sejarah_peguam_panel`** are themselves un-enumerated and carry the single-aktif-row invariant with no DB unique constraint enforcing "one aktif per case" — relies entirely on `closeAktif()` discipline in the service. |

---

## 11. STUCK-RECORD register (records that can dead-end with no valid next action)

| # | Record | Stuck state | Why no exit | Trigger frequency | Sev |
|---|---|---|---|---|---|
| STUCK-1 | `forms` (spine offer) | `status_agihan='1'` (numeric, spine-offered) | Lawyer Tawaran list queries literal `'Ditawarkan'` (B-1); lawyer never sees/accepts → `LebihMasa` bounces to `4` → re-loops spine to `1` forever | **every** spine-completed assignment | CRITICAL |
| STUCK-2 | `forms` (Pengarah-rejected new case) | `status_agihan='9'` | In no bucket, `stage()`=null; no re-open/route/close screen (B-2) | every Pengarah `tolak` of a new case | CRITICAL |
| STUCK-3 | `khidmat_nasihat` (no-show) | `status_kn='DALAM_PROSES'` + temu `TIDAK_HADIR` | `selesai` needs temu `HADIR`; no reschedule/close path (G-1) | every recorded no-show | CRITICAL |
| STUCK-4 | `khidmat_nasihat` (rejected appointment) | `status_kn` unchanged (BAHARU/DALAM_PROSES) + temu `BATAL` | `tolak` leaves KN appointment-less; no staff rebook (G-2) | every officer `tolak` | HIGH |
| STUCK-5 | `forms` (approved, never progressed) | `status='Diterima'` | onward steps optional; no SLA/forced-next (A-2) | passive — accumulates | MED |
| STUCK-6 | `peguam_panel` lawyer login | `permohonan_status='1'` but no credentials delivered | temp password shown once, no email (D-2) | any missed approval banner | MED |
| STUCK-7 | `butiran_peguam_panel_6` area | `checkbox_value_status='0'` | unnamed, not in any pending set (E-1) | every freshly-registered area until promotion | LOW |

---

## 12. Recommended validation rules (to prevent invalid transitions)

### 12.1 Immediate fixes for the 4 STUCK criticals/highs

| Target | Rule |
|---|---|
| **STUCK-1 (B-1)** | In `PeguamController::tawaran/dashboard/terima`, replace `where('status_agihan','Ditawarkan')` with `whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN]))` and write numeric `StatusAgihan::DITERIMA` on accept (not string `'Diterima'`). This is the single highest-value fix — it makes the spine functional end-to-end. |
| **STUCK-2 (B-2)** | Add `9` to a bucket (e.g. a new `BUCKET_DITOLAK` queue) AND a `stage('pengarah_tolak_semula')` action that re-routes a rejected case to `0` (re-review) or closes it. Define the allowed transition `9 → 0` (re-open) / `9 → tutup`. |
| **STUCK-3 (G-1)** | Define `TIDAK_HADIR` transitions: either `TIDAK_HADIR → SELESAI` (close as completed-without-attendance, mirroring legacy "Selesai Tanpa Kehadiran") and set `status_kn=SELESAI`, OR `TIDAK_HADIR → MENUNGGU` (reschedule). Currently neither exists. |
| **STUCK-4 (G-2)** | On `tolak`, decide the KN's fate explicitly: set `status_kn=BATAL` (and release), OR open a rebook path back to a new `temu_janji MENUNGGU`. Never leave KN appointment-less with no transition. |

### 12.2 Structural validation (all workflows)

1. **Promote every status to a PHP enum** (PHP 8.3 backed enums) and add a single `transition($from, $event): $to`
   table per workflow. Make `Form`, `KhidmatNasihat`, `TemuJanji` reject illegal `$to` at the model layer
   (mutator/observer), not just in the controller — closes the mass-assignment gap (X-2).
2. **Add `ensureStatus`-style from-guards to the two ungated transitions:** lawyer `terima`/`tolak` (B-5) must
   assert `normalise(status_agihan)===DITAWARKAN` before writing.
3. **Normalise `forms.status_agihan` on write, not just read (B-3, X-4).** Stop `AgihanController` and
   `PeguamController` writing string labels; write numeric consts everywhere and run a one-off migration to
   converge legacy string rows (`'Ditawarkan'→'1'`, `'Diterima'→'2'`, `'Ditolak'/'T'→'4'`, `'Diserah Semula'/'S'→'5'`).
   Then drop `LEGACY_STRING_MAP` reliance.
4. **DB-level guards:** `CHECK` constraints (MySQL 8 supports them) or enum columns for `status_kn`,
   `temu_janji.status`, `permohonan_status`, `checkbox_value_status`, `statusAktif`. Add a **unique partial
   index** enforcing one `status_rekod='aktif'` row per `id_kes` on `sejarah_ppuu` (X-5).
5. **Resolve the two parallel agihan front-ends (B-4):** pick the spine; make `AgihanController@store` refuse to
   overwrite a case whose `status_agihan` is mid-spine (`∈ {8,10,13,12,16,17}`).
6. **Gate `permohonan_status='3'` (Tarik Diri) (D-1, D-4):** require an approval permission and a from-guard
   (only from `0`/vetted, not from `1` Lulus).
7. **Widen `forms.status_agihan` beyond `varchar(2)` (B-6)** if any string label is retained transitionally,
   or (preferred) eliminate strings entirely so `varchar(2)` holds only `0`–`17`.
8. **Constrain the free-text statuses (H-1):** normalise `status_pengantaraan` to an enum (`Ya`/`Tidak`/
   `Tidak Dirujuk`) so statistik gates stop dropping typo rows; model `status_sidang` outcomes (`Selesai`/`Gagal`/`Tangguh`).
9. **Add an invariant linking `status_kn` ↔ `temu_janji.status` (G-4):** a KN cannot be `SELESAI` unless its
   temu is `SELESAI`; a KN cannot be `DALAM_PROSES` with a `BATAL`/`TIDAK_HADIR` temu and no recovery action queued.

### 12.3 Reporting/ETL guards

- Document the **intentional case-vs-history divergence** (C-1: case `4` / row `6` on TD approval) and the
  **`'Diterima'` cross-column collision** (A-4) in any data-warehouse mapping.
- Treat `status_agihan='7'` and history `'T'`/`'S'` as **history-only markers** — never a `forms` resting state.

---

## 13. Map corrections logged during verification (code-grounded)

| Map claim | Reality in `735dd4f` |
|---|---|
| Maps 05/06: "Status `7` LEBIH_MASA defined but never produced; no scheduled command; offers hang at `1` forever (stub/missing)." | **Partially wrong.** `AgihanLebihMasa` command IS scheduled (`routes/console.php`, daily 07:00) and `LebihMasaService` IS implemented. Status `7` lands on the **history row**; the case goes to `4`. The real defect is B-1 (offers never reach the lawyer), which turns the working timeout job into an infinite loop — not a missing job. |
| Map 05: spine transitions "each guarded by `ensureStatus()` against stale/double-submit." | **Confirmed for the spine + tarik-diri controllers** (`AgihanSpineController`/`TarikDiriController`). **NOT true** for lawyer `terima`/`tolak` (no guard — B-5). |
| Map 07: only `TIDAK_HADIR` flagged HIGH. | Confirmed; additionally `tolak`-orphaning (G-2) is an equally STUCK path — both are appointment-dead with no KN exit. |

---

## 14. Severity roll-up

| Severity | Count | IDs |
|---|---|---|
| CRITICAL (stuck/dead-end) | 3 | B-1/STUCK-1, B-2/STUCK-2, G-1/STUCK-3 |
| HIGH | 6 | B-3, B-4, D-1, G-2/STUCK-4, X-1, X-2 |
| MEDIUM | 9 | A-1, A-2, A-3, A-4, B-5, C-1, G-3, G-4, H-1, X-3, X-4 |
| LOW / INFO | remainder | B-6..B-9, C-2/3, D-2..4, E-1..3, F-1..3, G-5/6, H-2/3, X-5 |

**The two systemic root causes** behind most CRITICAL/HIGH findings: (1) **dual string/numeric encoding on
`forms.status_agihan` reconciled only at read time**, and (2) **state machines whose terminal/exception branches
(`9`, `TIDAK_HADIR`, rejected-appointment) were never given a next-action screen**. Fix those two and the spine +
KN workflows become end-to-end traversable.
