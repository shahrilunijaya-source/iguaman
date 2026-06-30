# 05 — 2in1 PEGUAM PANEL + AGIHAN Domain Map

> Source: NEW consolidated Laravel app `2in1` (`iGuaman/2in1`). Read-only audit of the panel-lawyer
> registration/approval lifecycle, the 3-tier case-assignment spine (PPUU → Pengarah → Ketua Pengarah),
> the lawyer self-service area, "Tarik Diri Mewakili OYD" withdrawal, bidang-pengkhususan add/drop, and
> the lawyer active/inactive lifecycle with death-redistribution. Statuses, transitions, role gates,
> and dead-ends are recorded as actually built. As of commit `735dd4f` (branch `main`).

---

## 0. Domain at a glance

| Sub-domain | Entry route(s) | Controller | Service (state machine) | Primary table(s) |
|---|---|---|---|---|
| Public lawyer application | `GET/POST /peguam/daftar` | `PeguamDaftarController` | — | `butiran_peguam_panel_2..6` + `uploaded_files` |
| Application approval (3-tier) | `/permohonan-peguam/*` | `PermohonanPeguamController` | inline (in controller) | `butiran_peguam_panel_2` (`permohonan_status`) → promotes to `peguam_panel` + `users` |
| Single-step assignment (legacy direct) | `/kes/{kes}/agih` | `AgihanController` | inline | `forms` (string `status_agihan`) + `sejarah_peguam_panel` |
| 3-tier assignment spine | `/agihan/*` | `AgihanSpineController` | `AgihanService` + `StatusAgihan` | `forms.status_agihan` (numeric) + `sejarah_ppuu` |
| Lawyer self-service area | `/peguam/*` (prefix, `permission:lawyer.area`) | `PeguamController` | — | `forms`, `butiran_peguam_panel_2..6`, `laporan_kes`, `uploaded_files` |
| Tarik Diri (withdrawal) | lawyer: `/peguam/kes/{kes}/tarik-diri`; staff: `/tarik-diri/*` | `PeguamController` + `TarikDiriController` | `TarikDiriService` | `forms.status_agihan` + `sejarah_peguam_panel` (TD cols) |
| Bidang Pengkhususan add/drop | lawyer: `/peguam/pengkhususan/*`; staff: `/kemaskini-bidang/*` | `PeguamController` + `KemaskiniBidangController` | `PengkhususanService` | `butiran_peguam_panel_6.checkbox_value_status` |
| Lawyer lifecycle + death-redistribution | `/peguam-panel/{peguam}/nyahaktif|aktif-semula` | `PeguamPanelController` | `PeguamLifecycleService` | `peguam_panel.statusAktif` + `forms` + `sejarah_ppuu` + `sejarah_peguam_panel` |
| Staff panel-lawyer view/edit | `/peguam-panel/{peguam}`, `/edit` | `PeguamPanelController` | — | `peguam_panel` + `butiran_peguam_panel` |
| Assignment workload | `/peguam-panel/beban` | `AgihanController@beban` | — | `forms` (count by lawyer name) |

Base tables `forms`, `peguam_panel`, `butiran_peguam_panel`, `butiran_peguam_panel_2`, `sejarah_peguam_panel`
are imported verbatim from `database/schema/legacy-domain.sql` (migration `2026_06_29_000001_import_legacy_domain_tables.php`).
Tables `sejarah_ppuu`, `butiran_peguam_panel_3..6`, the `peguam_panel.statusAktif` lifecycle columns, and the
`butiran_peguam_panel_2.semakan_ppuu` step are NEW (migrations `2026_06_30_000001..000005`).

---

## 1. Roles & permission gates (as built)

Roles (constants on `App\Models\User`): `admin`, `pengarah`, `koordinator`, `pegawai`, `peguam`,
`ppuu` (Penolong Pegawai Undang-Undang = case distributor), `pembantu_tadbir` (clerk),
`ketua_pengarah` (Director General = final approver). Lawyer login: `user_type = 'lawyer'`,
`role = 'peguam'`, linked to its `peguam_panel` master via `users.id_peguam_panel = peguam_panel.kp_peguam`
(`User::lawyerProfile()` BelongsTo).

Permission → role matrix (`database/seeders/RolePermissionSeeder.php`):

| Permission | Roles granted |
|---|---|
| `agihan.manage` | pengarah, koordinator, pegawai, ppuu, pembantu_tadbir, ketua_pengarah |
| `agihan.pengarah` | pengarah |
| `agihan.ppuu` | ppuu, koordinator |
| `agihan.kp` | ketua_pengarah |
| `peguam.permohonan.view` | pengarah, koordinator, pegawai, ppuu, pembantu_tadbir, ketua_pengarah |
| `peguam.semak` | ppuu, pembantu_tadbir, koordinator |
| `peguam.sokong` | pengarah |
| `peguam.keputusan` | ketua_pengarah |
| `lawyer.area` | peguam |

Gating styles differ by area (inconsistency, not a bug):
- Assignment spine (`/agihan/*`): per-route `permission:agihan.*` middleware.
- Application approval (`/permohonan-peguam/*`): NO per-route permission middleware (only `auth`); each
  controller action does an inline `$request->user()->can('peguam.semak'|'sokong'|'keputusan')` check.
- Tarik Diri / Kemaskini Bidang / lifecycle: `role:` middleware (e.g. `role:pengarah|admin`), NOT `permission:`.

---

## 2. FEATURE — Public lawyer panel application (daftar)

**What it does.** Prospective panel lawyer fills a 7-section public form (no login). Full parity with legacy
`daftar.php`. View `resources/views/peguam/daftar.blade.php` (588 lines). Throttled `6,1` + honeypot.

**Flow.** `GET /peguam/daftar` → `PeguamDaftarController@create` (loads `RefKes` practice-area options grouped
by `jenis_kes` ∈ {JEN, SIV, SYA, PG}, `RefNegeri` list). `POST` → `@store` validates via
`PeguamDaftarRequest`, then in one DB transaction writes:
- `butiran_peguam_panel_2` (section 2 biographical) with `permohonan_status='0'` (Baharu) + `tarikhMohon=now()`.
- `butiran_peguam_panel_3` (CLP / CSO 1-5 / YBGK / ADR / Sijil Ahli + Akreditasi / eVendor), keyed by `kpBaru` (IC).
- `butiran_peguam_panel_4` (firma + professional-indemnity insurance).
- `butiran_peguam_panel_5` (payment bank account).
- `butiran_peguam_panel_6` one row per selected practice area (`category` + `checkbox_value`, `checkbox_value_status=0`).
- 18 PDF docs via `LawyerDocuments::store` (doc types from `PeguamDaftarRequest::DOC_TYPES`).

Redirects back to `peguam.daftar` with `daftar_selesai=true` + `daftar_ref=<id>` (success banner).

**Completion condition.** All 5 detail rows + the selected-area rows + docs persisted; application now visible
in the staff approval queue as `permohonan_status='0'`.

**Status:** built.

---

## 3. FEATURE — Application approval workflow (3-tier) + promote to panel

**What it does.** Staff turn a Baharu application into an active panel lawyer. `PermohonanPeguamController`.
Tier chain: **PPUU semak → Pengarah sokong → Ketua Pengarah keputusan**. Approval promotes the application
into `peguam_panel` AND provisions a `users` login row.

**`permohonan_status` state set** (`PermohonanPeguamController::STATUS`):

| Code | Label |
|---|---|
| `0` | Baharu |
| `1` | Lulus |
| `2` | Tidak Lulus |
| `3` | Tarik Diri |

**Transitions:**

| Step | Route → action | Gate | Pre-condition | Effect |
|---|---|---|---|---|
| List | `GET /permohonan-peguam` → `index` | `auth` only | — | filter by status, pending=count(`status=0`) |
| Detail | `GET /permohonan-peguam/{butiran}` → `show` | `auth` only | — | renders detail + the right tier form |
| Tier 1 semak | `POST .../semak` | inline `can('peguam.semak')` | — | sets `semakan_ppuu` (0/1) + `ulasan_semakan_ppuu` + `tarikh_semakan_ppuu` |
| Tier 2 sokong | `POST .../sokong` | inline `can('peguam.sokong')` | `semakan_ppuu==='1'` else error `urutan` | sets `sokonganPengarah` (0/1) + `ulasan_sokonganPengarah` + date |
| Tier 3 keputusan (lulus) | `POST .../keputusan` | inline `can('peguam.keputusan')` | `sokonganPengarah==='1'` else error `urutan` | `permohonan_status='1'`, `ulasan_keputusanKP`, `tarikh_keputusanKP`; calls `promote()` |
| Tier 3 keputusan (tolak) | same route | same | same | `permohonan_status='2'`, `sebabTidakDiluluskan`, `tarikhTidakDiluluskan` |
| Tarik Diri | `POST .../tarik-diri` | `auth` only (no inline `can`) | — | `permohonan_status='3'`, `tarikhBatal`, `sebabBatal` |

**`promote()` (on lulus)** — `PermohonanPeguamController::promote/provisionLogin`:
- Idempotent by `kp_peguam`: creates `peguam_panel` master if none exists (firma fields stubbed `'-'` where
  the `_2` record lacks them — only `nama_firma` is carried over; address/poskod/negeri/tel default to `'-'`).
- `provisionLogin()`: idempotent by `id_peguam_panel`/`email`; creates a `users` row
  (`user_type=TYPE_LAWYER`, `role=ROLE_PEGUAM`, `is_active=true`, `must_change_password=true`), syncs the
  `peguam` role, generates a 10-char temp password (no symbols) surfaced in the flash message
  ("kata laluan sementara: …"). Audit logged.

**Hanging / dead-end notes:**
- The temp password is shown ONCE in a flash message; there is NO email delivery of credentials to the lawyer
  (the `daftar` flow itself never emails either). If the staff member misses the banner, the lawyer cannot log in
  until an admin resets — operational gap.
- `tarikDiri` (application withdrawal, status `3`) has no permission/role gate beyond `auth` — any authenticated
  staff (even `pegawai` without approval rights) can mark an application Tarik Diri. Likely under-gated.
- The promoted `peguam_panel` row carries placeholder `'-'` for firm address/phone even though section-4 firma
  data exists in `butiran_peguam_panel_4`; the richer firma data is NOT copied into the master at promotion.

**Status:** built (with the credential-delivery gap + the under-gated tarikDiri noted).

---

## 4. FEATURE — Single-step direct assignment (legacy `kes.agih`) — PARALLEL PATH

**What it does.** `AgihanController` (`agihan.manage`) — a SIMPLER, one-shot assign/reassign of a lawyer to a case,
distinct from the 3-tier spine. Routes: `GET/POST /kes/{kes}/agih`.

**Flow.** `@form` lists all `PeguamPanel` + prior `sejarah_peguam_panel`. `@store`:
- If the case already had a lawyer (`nama_pegawai_yang_dapat_kes` filled), logs the outgoing lawyer to
  `sejarah_peguam_panel` (`status='S'`, `status_agihan='S'` — "semula").
- Sets `forms.nama_pegawai_yang_dapat_kes` + `agih_kepada` + `tarikh_penugasan_peguam_panel=today` and
  **`status_agihan='Ditawarkan'` (STRING label, not numeric)**.
- Emails the lawyer (`KesDitawarkanMail`, best-effort).

**CRITICAL INCONSISTENCY.** This path writes the legacy STRING labels (`'Ditawarkan'`, and the lawyer
accept/reject in `PeguamController` writes `'Diterima'`/`'Ditolak'`) into `forms.status_agihan`, whereas the
3-tier spine + `AgihanService` use the canonical NUMERIC machine (`'1'`, `'2'`, `'4'`…). `StatusAgihan`
reconciles reads via `LEGACY_STRING_MAP` (`'Ditawarkan'→'1'`, `'Diterima'→'2'`, `'Ditolak'→'4'`,
`'Diserah Semula'→'5'`) and `bucketValues()` expands buckets to include both forms. So two assignment
front-ends mutate the same column with two encodings; lists work because of the alias map, but the data is
not normalised on write. There is no guard preventing a case in mid-spine (numeric status) from being
overwritten by the single-step `@store`.

**Workload (`@beban`).** `GET /peguam-panel/beban` — per-lawyer assigned-case count by matching
`forms.nama_pegawai_yang_dapat_kes` to `peguam_panel.nama_peguam` (string match), plus an unassigned count.
Note: workload is computed by NAME match, not by `kp_peguam`/id — fragile if two lawyers share a name or a
name changes.

**Status:** built (but it is a competing legacy path to the spine; encoding clash is the headline risk).

---

## 5. FEATURE — 3-tier assignment spine (PPUU → Pengarah → Ketua Pengarah)

**What it does.** The canonical legacy `pp-agihan` state machine. `AgihanSpineController` is the role-routed
host page; ALL transitions go through `App\Support\AgihanService`; the numeric state set lives in
`App\Support\StatusAgihan`. The PPUU-pick / endorsement / decision chain is recorded on `sejarah_ppuu` with
aktif/tutup rotation (one `aktif` row per case via `SejarahPpuu::aktif()`).

**`forms.status_agihan` numeric states** (`StatusAgihan`):

| Code | Const | Meaning |
|---|---|---|
| `0` | BARU_PENGARAH | new case awaiting Pengarah review |
| `1` | DITAWARKAN | offered to panel lawyer |
| `2` | DITERIMA | accepted by lawyer (case active) |
| `4` | PPUU_AGIH_SEMULA | bounced back to PPUU to re-pick |
| `5` | SELESAI | case closed |
| `6` | TARIK_DIRI_LULUS | withdrawal approved → returned to pool |
| `7` | LEBIH_MASA | auto re-assign (no PP response in 7 days) |
| `8` | DIAGIH_PPUU | awaiting PPUU lawyer selection |
| `9` | DITOLAK_PENGARAH | Pengarah rejected the new case |
| `10` | SOKONGAN_PENGARAH | awaiting Pengarah endorsement of PPUU pick |
| `12` | DALAM_PROSES_TARIK_DIRI | PP submitted withdrawal |
| `13` | KELULUSAN_KP | awaiting Ketua Pengarah final approval |
| `14` | TOLAK_KE_CAWANGAN | KP rejected back to branch (defined, see dead-code note) |
| `15` | KELULUSAN_KP_SEMULA | re-submitted to KP after rejection |
| `16` | SEMAKAN_PENGARAH_TD | withdrawal: awaiting Pengarah review |
| `17` | SEMAKAN_KP_TD | withdrawal: awaiting Ketua Pengarah review |

**List buckets** (`AgihanSpineController::BUCKETS`, route `GET /agihan/senarai/{bucket}`):
- `baru` = {0, 8, 10, 13}; `semasa` = {1, 2, 5}; `semula` = {4, 15}. (`tarik_diri` bucket {12,16,17} is the
  TarikDiri queue, handled by a different controller.)

**Detail + role-routing.** `GET /agihan/{kes}/maklumat` → `@show` computes `stage(status, user)`:

| Stage form | Active when status ∈ | AND user role |
|---|---|---|
| `pengarah_baru` | {0} | pengarah / admin |
| `ppuu_pilih` | {8, 4, 15} | ppuu / koordinator / admin |
| `pengarah_sokong` | {10} | pengarah / admin |
| `kp_keputusan` | {13} | ketua_pengarah / admin |

`resources/views/agihan/maklumat.blade.php` (180 lines) renders exactly one of the four `@if $stage` forms,
fully wired with CSRF + history tables (`sejarahPpuu`, `sejarahPp`).

**Transitions** (all via `AgihanService`, each guarded by `ensureStatus()` against stale/double-submit, 422 on mismatch):

| # | Route → action | Service method | Gate | From→To | Side-effects |
|---|---|---|---|---|---|
| T1a | `pengarah-terima` | `pengarahTerima` | `agihan.pengarah` | 0→8 | close any aktif `sejarah_ppuu`; create new aktif `sejarah_ppuu` with `idPPUU`; notify PPUU |
| T1b | `pengarah-tolak` | `pengarahTolakBaru` | `agihan.pengarah` | 0→9 | notify branch supervisors |
| T2a | `ppuu-pilih` | `ppuuPilih` | `agihan.ppuu` | 8/4/15→10 | set `pilihan_Agihan` (A/B), `nama_peguampanel`, `kpBaru_peguampanel`, `ulasanPPUU` on aktif row; notify Pengarah |
| T2b | `pengarah-keputusan` (sokong) | `pengarahSokong` | `agihan.pengarah` | 10→13 | `status_sokonganPengarah='0'`, forward to KP |
| T2c | `pengarah-keputusan` (tidak) | `pengarahTidakSokong` | `agihan.pengarah` | 10→4 | close aktif row, log `sejarah_peguam_panel` reassignment, open NEW aktif PPUU row |
| T3a | `kp-keputusan` (lulus) | `kpLulus` | `agihan.kp` | 13→1 | set `forms.nama_pegawai_yang_dapat_kes`+`agih_kepada` from the pick; email offer to lawyer |
| T3b | `kp-keputusan` (tolak) | `kpTolak` | `agihan.kp` | 13→15 | close aktif row, open new aktif PPUU row (re-pick); notify branch+Pengarah |

After T3a (status `1`), the lawyer accepts/rejects in their own area (see §6) → `2` or back to pool.

**Notifications.** `NotifikasiAgihan` fans out `AgihanTransisiMail` to the next actor (best-effort, outside the
DB transaction; failures `report()`-ed, never thrown). Recipients resolved by role (HQ-wide) or branch
(`branchSupervisors()` by `users.cawangan` matching `forms.cawangan`).

**Hanging / dead-end states:**
- **Status `9` (Ditolak Pengarah) is terminal with no UI exit.** After `pengarahTolakBaru`, the case is `9`,
  which is in NO list bucket (baru/semasa/semula/tarik_diri all exclude `9`) and `stage()` returns null for it.
  The case effectively disappears from the spine queues — no built path to re-open or re-route a Pengarah-rejected
  new case. (The notification tells the branch to "kemas kini sistem", implying a manual/branch action that has
  no corresponding screen here.) **Dead-end.**
- **Status `7` (LEBIH_MASA / auto re-assign after 7-day no-response) is defined but never produced.**
  `PeguamController::OFFER_DEADLINE_DAYS = 7` exists and the `tawaran` view shows a deadline, but there is NO
  scheduled job/command that flips an un-actioned offer (`1`) to `7`. The auto-reassignment-on-timeout legacy
  behaviour is NOT implemented — offers can sit at `1` indefinitely. **Stub / missing automation.**
- **Status `14` (TOLAK_KE_CAWANGAN) is defined and labelled but never written** by any transition (KP reject
  goes to `15`, not `14`). Dead constant retained for read-compat only.
- `ensureStatus()` correctly blocks stale double-submits, but there is no optimistic-lock/version column —
  concurrency relies on the single-aktif-row invariant.

**Status:** built (core chain complete and guarded) — but the `7`-timeout automation is missing and `9` is a dead-end.

---

## 6. FEATURE — Lawyer self-service area (`/peguam/*`, `permission:lawyer.area`)

`PeguamController`. Profile linked via `User::lawyerProfile` (`id_peguam_panel = kp_peguam`). The signed-in
lawyer's IC (`lawyerKp()` = `users.id_peguam_panel ?: users.nokp`) keys `butiran_peguam_panel_2..6`.

| Screen | Route | What |
|---|---|---|
| Dashboard | `GET /peguam` | counts: kes_saya (status `Diterima`), tawaran (status `Ditawarkan`) — uses STRING statuses |
| Kes saya | `GET /peguam/kes` | paginated cases assigned by name (or `1=0` empty set if no profile) |
| Tawaran | `GET /peguam/tawaran` | offered cases (status `Ditawarkan`) + 7-day deadline display |
| Accept offer | `POST /peguam/kes/{kes}/terima` | `authorizeCase`; `status_agihan='Diterima'` (string) |
| Reject offer | `POST /peguam/kes/{kes}/tolak` | log `sejarah_peguam_panel` (`status='T'`); clear lawyer; `status_agihan='Ditolak'` |
| Case detail | `GET /peguam/kes/{kes}` | loads `laporanKes` |
| Lawyer report | `POST /peguam/kes/{kes}/laporan` | creates `laporan_kes` row |
| Profile view | `GET /peguam/profil` | renders `_2..6` + pengkhususan |
| Profile edit | `GET/POST /peguam/profil/kemaskini` | `PeguamProfilUpdateRequest`; rewrites `_2/_3/_4/_5` + re-uploads all 18 docs (`LawyerDocuments::store`) |

**Authorization.** `authorizeCase()` aborts 403 unless `forms.nama_pegawai_yang_dapat_kes === profile->nama_peguam`
(NAME match — same fragility as workload).

**Encoding note.** The lawyer-side accept/reject writes STRING statuses (`'Diterima'`, `'Ditolak'`), feeding the
same dual-encoding situation as §4. After T3a the spine sets numeric `1` (DITAWARKAN) but the lawyer's
`tawaran`/`dashboard` filters on the STRING `'Ditawarkan'` — these are reconciled at READ time only by
`StatusAgihan::label/normalise`, NOT in these direct `where('status_agihan','Ditawarkan')` queries.
**Functional risk:** a case offered via the SPINE (numeric `1`) will NOT appear in the lawyer's `tawaran` list,
because `tawaran()` filters `where('status_agihan','Ditawarkan')` (literal string) and does not expand via
`StatusAgihan::bucketValues`. Only cases offered via the single-step `AgihanController` (which writes the
literal `'Ditawarkan'`) surface to the lawyer. **This is a cross-path integration gap between §4/§5 and §6.**

**Status:** partial — screens all built, but the spine→lawyer offer hand-off is broken by the string/numeric
status mismatch in `tawaran()`/`dashboard()`/`terima()`.

---

## 7. FEATURE — Tarik Diri Mewakili OYD (lawyer withdrawal, 4-stage)

**What it does.** A panel lawyer withdraws from representing an assisted person. Lawyer initiates; staff review
in PPUU → Pengarah → Ketua Pengarah chain. `TarikDiriService` (state machine), lawyer side in
`PeguamController`, staff side in `TarikDiriController`. Active record = the `sejarah_peguam_panel` row with
`status_rekod='aktif'` and status ∈ {12,16,17} (`TarikDiriService::aktif`).

**9 reasons** (`TarikDiriService::REASONS`, ref Seksyen 24 Akta Bantuan Guaman 1971): Konflik kepentingan;
Masalah kesihatan; Komitmen peribadi/keluarga; Beban kes terlalu tinggi; Anak guam tidak memberi kerjasama;
Kes bertentangan dengan prinsip peribadi; Tidak mahu sambung sebagai panel; Kesalahan fakta semasa penugasan;
Anak guam mohon menukar peguam panel.

**Transitions:**

| Stage | Route → action | Service | Gate | From→To | Notes |
|---|---|---|---|---|---|
| Submit | `POST /peguam/kes/{kes}/tarik-diri` | `ppSubmit` | `lawyer.area` + `authorizeCase` + `ensureKesDiterima` (status must be `2`) | 2→12 | creates aktif `sejarah_peguam_panel`; optional `akuanTarikDiri` PDF (≤5MB) to `uploaded_files` |
| PPUU | `POST /tarik-diri/{kes}/ppuu` | `ppuuSemak` | `role:ppuu\|koordinator\|admin` | 12→16 | writes `ulasanPPUU` |
| Pengarah | `POST /tarik-diri/{kes}/pengarah` | `pengarahSemak` | `role:pengarah\|admin` | 16→17 | writes `ulasanPengarah` |
| KP lulus | `POST /tarik-diri/{kes}/kp` | `kpKeputusan(approve)` | `role:ketua_pengarah\|admin` | 17→**4** (case) / row→6 | clears lawyer; closes aktif `sejarah_ppuu`; opens NEW aktif PPUU row → returns case to re-assign pool |
| KP tolak | same | `kpKeputusan(reject)` | same | 17→**2** (case) | lawyer continues; row marked `selesai`, `keputusan_tarikDiriHQ='1'` |

Staff queue: `GET /tarik-diri/senarai` (bucket {12,16,17}); detail `GET /tarik-diri/{kes}/maklumat`
(`stage()` role-routes, view `tarik-diri/maklumat.blade.php`, 93 lines, wired). `ensureStatus()` guards each stage.

**Note on approval target.** On approval the case goes to `4` (PPUU_AGIH_SEMULA) while the history row's own
`status`/`status_agihan` is set to `6` (TARIK_DIRI_LULUS) — i.e. the case status (`4`) and the audit row
status (`6`) intentionally differ. Documented in code; not a bug, but worth noting for any ETL.

**Status:** built (chain complete + guarded).

---

## 8. FEATURE — Bidang Pengkhususan add/drop (2-tier: Pengarah → Ketua Pengarah)

**What it does.** A lawyer adds or drops a practice area. `PengkhususanService` over
`butiran_peguam_panel_6.checkbox_value_status`. Lawyer initiates in `PeguamController`; staff review in
`KemaskiniBidangController`.

**`checkbox_value_status` machine** (`ButiranPeguamPanel6`):

| Code | Const | Meaning |
|---|---|---|
| `1` | LEGACY_AKTIF | approved at registration |
| `2` | AKTIF | approved & active |
| `3` | DROP_MOHON | lawyer requested removal |
| `4` | ADD_MOHON | lawyer requested addition |
| `7` | DROP_DISOKONG | Pengarah recommended removal → KP deletes |
| `9` | ADD_DISOKONG | Pengarah recommended addition → KP activates |

`AKTIF_STATES={1,2}`, `PENGARAH_PENDING={3,4}`, `KP_PENDING={7,9}`. (Note: registration writes `0` —
`checkbox_value_status=0` — which is NOT in any named const; `0` rows sit as "new at registration" and are not
surfaced in the kemaskini queue, only the add/drop request states 3/4/7/9 are. The `pengarahReview` guard
`PENGARAH_PENDING` excludes `0`, so a `0` row can never be acted on here — it is effectively just "selected at
daftar". Mild dead-state.)

**Transitions:**

| Step | Route → action | Service | Gate | Effect |
|---|---|---|---|---|
| Add request | `POST /peguam/pengkhususan/tambah` | `requestAdd` | `lawyer.area` | idempotent; creates `_6` row status `4` (ADD_MOHON), `jenisKemaskini='TAMBAH'` |
| Drop request | `POST /peguam/pengkhususan/{row}/gugur` | `requestDrop` | `lawyer.area` + owner check | row must be AKTIF; **blocked if an active case uses that category**; sets status `3` |
| Pengarah sokong | `POST /kemaskini-bidang/{row}/pengarah` | `pengarahReview(true)` | `role:pengarah\|admin` | 3→7 / 4→9 |
| Pengarah tolak | same | `pengarahReview(false)` | same | drop→revert to AKTIF; add→row deleted |
| KP lulus | `POST /kemaskini-bidang/{row}/kp` | `kpDecide(true)` | `role:ketua_pengarah\|admin` | drop(7)→row DELETED; add(9)→status `2` AKTIF |
| KP tolak | same | `kpDecide(false)` | same | drop→revert AKTIF; add→row deleted |

Staff queue: `GET /kemaskini-bidang` (`index`, view 75 lines) lists `PENGARAH_PENDING ∪ KP_PENDING`, resolving
lawyer names from `butiran_peguam_panel_2.namaPeguam` by IC. Drop-guard `hasActiveCaseInCategory()` matches
lawyer name + `forms.kategori_kes LIKE %category%` over active statuses.

**Status:** built.

---

## 9. FEATURE — Lawyer active/inactive lifecycle + DEATH-REDISTRIBUTION

**What it does.** Deactivate/reactivate a panel lawyer. Deactivating one who still holds active cases triggers
**death-redistribution**: every case they handle is returned to the PPUU re-assignment pool so no assisted
person is left unrepresented. `PeguamLifecycleService`; staff entry `PeguamPanelController`.

`peguam_panel` lifecycle columns (migration `…000005`): `statusAktif` (`'1'` aktif / `'0'` tidak aktif,
default `'1'`), `sebabTidakAktif`, `tarikhTidakAktif`. Deactivation reasons (`PeguamPanel::SEBAB_LIST`):
`Tindakan JK Disiplin`, `Meninggal Dunia`, `Lain-lain` (+ free-text required if Lain-lain).

| Action | Route → method | Gate | Effect |
|---|---|---|---|
| Deactivate | `POST /peguam-panel/{peguam}/nyahaktif` | `role:admin\|koordinator\|pengarah\|ketua_pengarah` | set `statusAktif='0'`+sebab+date; **block the lawyer's `users` login (`is_active=false`)**; redistribute active cases; returns count |
| Reactivate | `POST /peguam-panel/{peguam}/aktif-semula` | same | `statusAktif='1'`, clear sebab/date, re-enable login. Does NOT auto-reassign cases back |

**`redistributeActiveCases()`** — for every `forms` row where `nama_pegawai_yang_dapat_kes == lawyer name`
AND status ∈ bucketValues({DITAWARKAN, DITERIMA}) (i.e. `1`/`2` + their string aliases):
1. Close any aktif `sejarah_ppuu`; open a NEW aktif `sejarah_ppuu` at status `4` (PPUU_AGIH_SEMULA).
2. Log a `sejarah_peguam_panel` row (outgoing lawyer + `kp_pp_lama`, alasan "Peguam dinyahaktifkan: …").
3. Set `forms.status_agihan='4'`, clear `nama_pegawai_yang_dapat_kes`/`agih_kepada`/`tarikh_penugasan_peguam_panel`.

Returns case count; surfaced in the flash ("{n} kes aktif telah dikembalikan untuk agihan semula").

**Notes:**
- Redistribution matches active cases by lawyer NAME (`nama_peguam`), consistent with the rest of the domain
  but fragile if names collide.
- Reactivation deliberately does NOT pull redistributed cases back (matches legacy — once redistributed, a case
  follows the spine).
- `peguam_panel.isAktif()` treats anything except `'0'` as active (so legacy NULL = active).

**Status:** built (the flagged "most dangerous legacy parity gap" is implemented and transactional).

---

## 10. FEATURE — Staff panel-lawyer view / edit / workload

| Screen | Route → method | Gate | What |
|---|---|---|---|
| Show | `GET /peguam-panel/{peguam}` | `auth` (reachable via the authenticated staff group) | master + `butiran_peguam_panel` (v1) + up to 50 assigned cases by name; view `peguam-panel/show.blade.php` (81 lines) + `_butiran` partial |
| Edit | `GET /peguam-panel/{peguam}/edit` | `auth` | basic master edit form |
| Update | `PUT /peguam-panel/{peguam}` | `auth` | validates + updates 12 master fields; Audit logged |
| Workload | `GET /peguam-panel/beban` | `agihan.manage` | per-lawyer assigned-case count (see §4) |

**Gating note.** `peguam-panel.show/edit/update` sit OUTSIDE any `permission:`/`role:` group (they are declared in
the authenticated block right after the kemaskini-bidang routes) — only `auth` + the outer `permission:system.view`
on the parent group gates them. Any staff with `system.view` can view/edit a panel-lawyer master record. The
nyahaktif/aktif-semula actions are the only lifecycle-gated ones.

**Status:** built.

---

## 11. Cross-cutting findings (consolidation risks)

| # | Severity | Finding |
|---|---|---|
| C1 | HIGH | **Dual status encoding on `forms.status_agihan`.** Single-step `AgihanController` + lawyer accept/reject write STRING labels (`'Ditawarkan'`/`'Diterima'`/`'Ditolak'`); the spine + `AgihanService` write NUMERIC codes. Reconciled only at read time via `StatusAgihan::LEGACY_STRING_MAP`/`bucketValues`. No write-time normalisation, no migration to converge legacy rows. |
| C2 | HIGH | **Spine→lawyer offer hand-off broken.** `PeguamController::tawaran/dashboard/terima` filter `where('status_agihan','Ditawarkan')` (literal string). A case offered through the SPINE (numeric `1`) never reaches the lawyer's Tawaran list. Only the single-step assign path surfaces offers. |
| C3 | MED | **No 7-day offer-timeout automation.** Status `7` (LEBIH_MASA) defined; `OFFER_DEADLINE_DAYS=7` + deadline UI exist, but no scheduled command flips stale offers. Offers can hang at `1`/`'Ditawarkan'` forever. |
| C4 | MED | **Status `9` (Ditolak Pengarah) is a dead-end** — in no list bucket, no `stage()` form; a Pengarah-rejected new case leaves the spine with no built recovery screen. |
| C5 | MED | **Two parallel assignment front-ends** (`AgihanController` single-step vs `AgihanSpineController` 3-tier) both mutate the same case with no guard preventing one from clobbering the other's in-flight state. Consolidation must pick one. |
| C6 | LOW | **Name-based linkage throughout.** Case↔lawyer ownership, workload, redistribution, and category-drop guard all match on `nama_peguam` string rather than `kp_peguam`/id. Fragile on duplicate/renamed names. |
| C7 | LOW | **Approval credentials not delivered.** Lawyer login temp password shown once in a flash; no email. Missed banner = locked-out lawyer until admin reset. |
| C8 | LOW | **Inconsistent gate styles** — spine uses `permission:`, approval uses inline `->can()`, tarik-diri/kemaskini/lifecycle use `role:`. `peguam-panel.show/edit/update` + `tarikDiri` (app withdrawal) are effectively only `auth`-gated. |
| C9 | LOW | Status `14` (TOLAK_KE_CAWANGAN) defined+labelled but never written (dead constant). `checkbox_value_status=0` is an unnamed/unhandled "selected at daftar" state. |

No `TODO`/`FIXME`/`stub` markers exist in any of these controllers/services (verified by grep). All Blade views
referenced by the routes exist and are fully wired (no placeholder views).
