# Phase 8 — Data & Database Review

> **Audit scope:** the consolidated Laravel app **"2in1"** at
> `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/2in1`,
> cross-referenced against the four source systems (legacy `sistem-peguam-panel`,
> legacy `sistem-rekod-kes`, legacy iGuaman advisory `be_iguaman`/`fe-iguaman`, chat `cbjbg`).
> **READ-ONLY.** No source/schema/migration files were modified. This document and the
> existing audit map files are the only artefacts written.
>
> **Inputs:** map 08 (platform-db), maps 01–03 (legacy), maps 05–07/09 (2in1), every file under
> `database/migrations/` (28), `database/schema/legacy-domain.sql`, `database/seeders/`,
> all `app/Models/*` (37), `app/Console/Commands/ImportLegacyData.php`, the KN/agihan services,
> and grep verification against the 51.7 MB `../sistemspk.sql` data dump.

---

## 0. TL;DR — the headline findings

| # | Finding | Severity |
|---|---|---|
| F1 | **Two legacy dumps disagree on which tables exist.** `legacy-domain.sql` (used by the import migration) has `peguam_panel`, `items`, `butiran_peguam_panel` v1 but **NOT** `_3/_4/_5/_6`, `sejarah_ppuu`, `ref_cawangan`. The 51.7 MB `sistemspk.sql` data dump has the inverse. A **third** schema source exists (`sistem-rekod-kes-laravel` 29-table migration set per map 02). Three competing sources of truth for the lawyer-panel detail tables + `sejarah_ppuu` + `forms`. | **HIGH** |
| F2 | **ETL reads a table that does not exist in the dump.** `ImportLegacyData::importUsers()` selects from `users_peguam_panel_3`; grep of `sistemspk.sql` returns **0** matches. `legacy:import` will fatal on a real run. `schema-design.md` also claims `_3`=116 rows. | **HIGH** |
| F3 | **`forms` parity is short by ~8 columns + a type-mismatch FK.** 2in1 `forms` base = 92 cols (+4 drifted = 96), not the documented 98. 8 rekod-kes columns are absent (listed §8). `laporan_kes.id_kes varchar(20)` vs `forms.id int` blocks the only remaining case→report FK. | **HIGH** |
| F4 | **No-FK "soft links" carry live orphan risk.** `khidmat_nasihat.id_temu_janji ⇄ temu_janji.id_khidmat_nasihat`, `id_pegawai_kn` (duplicated in two tables), `id_forms`, `id_mahkamah`, `id_negeri`, all `uploaded_files` link cols, `sejarah_ppuu.id_kes/idPPUU` — written/read by services with **no DB FK**. | **HIGH** |
| F5 | **Lawyer entity fragmented across 6 tables joined by `kpBaru` string, no FK.** `butiran_peguam_panel_2…_6` + `uploaded_files.kpBaru` + the `peguam_panel` master (keyed by `kp_peguam`, a *different* column). One logical lawyer = up to 7 unkeyed rows. | **HIGH** |
| F6 | `awam` role seeded by migration, missing from `RolePermissionSeeder::ROLES` + `RoleController::SYSTEM_ROLES` → renamable/deletable → breaks citizen gate. | **HIGH** |
| F7 | Duplicate-structure tables: `mahkamah_sivil` ≡ `mahkamah_syariah`; `butiran_peguam_panel` v1 vs `_2`. Collation/charset splits (`ref_cuti` latin1, `posters` `utf8mb4_0900_ai_ci`). Dead/near-dead tables (`items`, `ref_lokasi_berguam`, `jobs/*`, `model_has_permissions`). | **MEDIUM** |

Every removal proposal below carries the mandatory **safe-removal procedure** (§11). **Nothing here is a recommendation to drop without that procedure.**

---

## 1. How the schema is assembled (provenance)

| Layer | Source | Files |
|---|---|---|
| Platform | Laravel stock migrations | `0001_01_01_000000..000002` (`users`, `password_reset_tokens`, `sessions`, `cache*`, `jobs*`) |
| RBAC | spatie stock | `2026_06_29_194444_create_permission_tables` |
| **Legacy 20 tables** | **raw mysqldump** `DB::unprepared()` | `2026_06_29_000001_import_legacy_domain_tables.php` → `database/schema/legacy-domain.sql` |
| Keys/FKs/indexes | Blueprint | `2026_06_29_000002_add_keys_and_foreign_keys.php` |
| New feature tables | Blueprint (batches 8–13) | `2026_06_30_100001 … 130004` |

The legacy domain tables' DDL lives in **raw SQL, not Blueprint** — so renames/index adds on those tables require either editing the `.sql` or a follow-on `ALTER` migration. That split is itself a maintenance hazard (two places define the same table).

**Two physical dumps, divergent table sets (verified by grep on `CREATE TABLE`):**

| Table | in `legacy-domain.sql` (import) | in `sistemspk.sql` (51.7 MB data) |
|---|:--:|:--:|
| `peguam_panel` | ✓ | ✗ |
| `items` | ✓ | ✗ |
| `butiran_peguam_panel` (v1) | ✓ | ✗ |
| `butiran_peguam_panel_2` | ✓ | ✓ |
| `butiran_peguam_panel_3/4/5/6` | ✗ | ✓ |
| `butiran_peguam_panel_2_original` | ✗ | ✓ (legacy backup) |
| `sejarah_ppuu` | ✗ | ✓ |
| `ref_cawangan` | ✗ | ✓ |
| `users_peguam_panel_2` | (excluded from import) | ✓ |
| `users_peguam_panel_3` | ✗ | **✗ (does not exist)** |
| `users` | (excluded) | ✓ |

> **F1 / F2 implication:** the `_3/_4/_5/_6` and `sejarah_ppuu` Blueprint shapes in 2in1 were
> *reconstructed from legacy PHP* (the migration docblocks say "never dumped"), yet they **are** in
> the big data dump. The ETL must be reconciled against the real dump, and `users_peguam_panel_3`
> must be removed from `ImportLegacyData` (or the dump it expects must be located) before
> `legacy:import` can run.

---

## 2. Authoritative table per MAJOR entity

The single source-of-truth assignment the rest of this review enforces:

| MAJOR entity | **Authoritative table** | Subordinate / drop-candidate | Why |
|---|---|---|---|
| **User / login** | `users` (unified, bcrypt) | legacy `users`, `users_peguam_panel_2`, `users_peguam_panel_3` (ETL sources only) | One `web` guard; `user_type`+`role` discriminate. Correct. |
| **Case (litigation spine)** | `forms` | — | The 92(+drift)-col monolith. Stays single; decompose later (§8). |
| **Court report** | `laporan_kes` | — | Child of `forms` via `id_kes` (needs type fix, §3). |
| **Applicant/beneficiary** | `butiran_oyd` | — | Keyed `(id, kp_oyd)`. |
| **Lawyer master** | `peguam_panel` (keyed `kp_peguam`) | — | Surrogate `id` added. But see F5 — key mismatch vs detail tables. |
| **Lawyer application** | `butiran_peguam_panel_2` (keyed `kpBaru`) | **`butiran_peguam_panel` v1** | v1 superseded; v2 is the active approval record. |
| **Lawyer profile detail** | `_3` (quals), `_4` (firm), `_5` (bank), `_6` (specialisation) | — | Child fragments of `_2`; should normalise (F5). |
| **Assignment history** | `sejarah_ppuu` (3-tier spine) + `sejarah_peguam_panel` (lawyer side) | — | Two history tables by design (PPUU vs lawyer). |
| **Officer-change history** | `sejarah_pegawai` | — | Only legacy table that ships its own FK. |
| **Hearing history** | `sejarah_sidang` | — | |
| **Branch** | `cawangan` (new, typed JBG/JKM/PENJARA) | legacy `ref_cawangan` (dropped) | `nama` mirrors legacy `forms.cawangan` string so CawanganScope keeps working. |
| **State** | `ref_negeri` | — | Legacy `int` PK; dependents use no-FK `unsignedInteger` by design. |
| **Court reference** | `mahkamah_sivil` + `mahkamah_syariah` | (collapse candidate → one table + `jenis`) | Identical structure (F7). |
| **Litigation case-type** | `ref_kes` | — | NOT the KN tree (memory note). |
| **KN advisory tree** | `ref_kategori_kn` → `ref_kategori_kes_kn` → `ref_subkategori_kn` | — | 3-level, real FKs cascade. Correct. |
| **Advisory record** | `khidmat_nasihat` | — | KN core. |
| **Appointment** | `temu_janji` + `slot_temu_janji` | — | |
| **Feedback** | `maklum_balas` | — | Unique per KN, real FK. Best-modelled new table. |
| **Holiday/leave** | `ref_cuti` | — | latin1 charset (F7). |
| **Officer registry** | `pegawai_jbg` | — | `jawatan` free-text normalised by `ref_jawatan`. |
| **Attachments** | `uploaded_files` | — | Soft-linked to case/KN/lawyer (F4/F5). |
| **Audit** | `audit_trail` | — | Denormalised log, no FK by design (correct). |
| **e-Poster** | `posters` | — | `utf8mb4_0900_ai_ci` collation (F7). |
| **Generic list** | `items` | **DROP candidate** | No controller/route (§9). |
| **Practice location** | `ref_lokasi_berguam` | near-dead lookup | No controller/route (§9). |

---

## 3. Missing foreign keys (KEY FINDING)

Legacy shipped exactly **one** FK (`sejarah_pegawai.id_kes → forms.id`). The 2in1 `…000002` migration added two more (`sejarah_peguam_panel`, `sejarah_sidang`). Everything else that *logically* references another row is a **plain indexed column with no FK**. Inventory (table.column → intended target → why no FK):

| Table.column | Intended target | Status / risk |
|---|---|---|
| `laporan_kes.id_kes` | `forms.id` | **`varchar(20)` vs `int` — TYPE MISMATCH.** No FK possible without a migration to clean+cast. Orphaned reports invisible to integrity checks. |
| `khidmat_nasihat.id_temu_janji` | `temu_janji.id` | No FK; written by `KhidmatNasihatService` (`update(['id_temu_janji'=>…])`). Bidirectional soft link (see next). |
| `temu_janji.id_khidmat_nasihat` | `khidmat_nasihat.id` | No FK; set by `KhidmatNasihatService::…` (`'id_khidmat_nasihat'=>$khidmat->id`). **Two columns describe one relationship** → can disagree (F4). |
| `khidmat_nasihat.id_pegawai_kn` | `users.id` | Real FK (`constrained('users')->nullOnDelete`) — **good**. Migration docblock notes this is the *authoritative* PKN column, fixing a legacy bug where `temu_janji` dropped it. |
| `temu_janji.id_pegawai_kn` | `users.id` | **No FK** — a snapshot duplicate of the KN column. Denormalised duplicate of source-of-truth (§7). |
| `khidmat_nasihat.id_forms` | `forms.id` | No FK (reserved "Buka Kes" bridge). Written by `KhidmatProsesService::…` (`$fresh->id_forms = $form->id`). |
| `khidmat_nasihat.id_negeri` | `ref_negeri.id` | No FK by design (legacy `int` PK). Indexed. |
| `khidmat_nasihat.id_mahkamah` | `mahkamah_sivil.id`\|`mahkamah_syariah.id` | No FK — **polymorphic by `jenis_mahkamah_pihak`**, cannot have a single FK. Validated only at request layer (`KhidmatNasihatRequest`). |
| `uploaded_files.id_kes` | `forms.id` | No FK (`unsignedInteger`, indexed). |
| `uploaded_files.id_khidmat` | `khidmat_nasihat.id` | No FK (`unsignedBigInteger`, indexed). |
| `uploaded_files.kpBaru` | `butiran_peguam_panel_2.kpBaru` | No FK (string join). |
| `sejarah_ppuu.id_kes` | `forms.id` | **No FK** (`unsignedInteger`, indexed) — the 3-tier assignment spine is unprotected. |
| `sejarah_ppuu.idPPUU` | `users.id` | No FK (indexed). |
| `cawangan.negeri_id` | `ref_negeri.id` | No FK by design (legacy `int`). Indexed. |
| `audit_trail.record_id` | (any) | No FK by design — denormalised log. Correct. |

**Type-mismatch family (root cause of most missing FKs):** legacy tables use `int` PKs; new
Blueprint tables use `bigint` (`id()`/`foreignId`). A `bigint` FK cannot reference an `int` PK
without a column-width change. This is why every link from a *new* table into a *legacy* reference
table (`ref_negeri`, `mahkamah_*`, `forms`) is deliberately FK-less. **The cheapest durable fix is
to widen the legacy reference PKs to `bigint` and add the FKs**, done table-by-table behind the §11
procedure.

---

## 4. Missing indexes

Present indexes (verified): `forms(no_fail, nokp, cawangan, status)`; `users(user_type,role)`,
`users(username)`, unique `users.nokp`, unique-ish `users.email`(nullable); `sejarah_peguam_panel.id_kes`,
`sejarah_sidang.id_kes`; the `kpBaru`/`doc_type`/`id_kes`/`id_khidmat` indexes on `uploaded_files`;
`sejarah_ppuu(id_kes, idPPUU, status_rekod)`; `cawangan(jenis)`, unique `cawangan.nama`, `cawangan.negeri_id`;
`khidmat_nasihat.status_kn`, `khidmat_nasihat.id_negeri`; `(cawangan_id, tarikh_slot)`,
`(cawangan_id, tarikh_temu_janji)`, `(cawangan_id, tarikh_mula)`.

**Gaps (hot paths with no covering index):**

| Table | Missing index | Why it matters |
|---|---|---|
| `laporan_kes` | `id_kes`, `no_fail` | `Form::laporanKes()` joins on `id_kes`; map 06 shows court-report screens JOIN `laporan_kes ↔ forms`. Full scan today. |
| `khidmat_nasihat` | `id_pengguna`, `id_pegawai_kn`, `cawangan_id`, `id_temu_janji` | Officer queue filters by `id_pegawai_kn` (`KhidmatProsesService`), citizen list by `id_pengguna` (`ByUser`), branch list by `cawangan_id`. FK cols get an index automatically *only* where `constrained()` was used — `id_temu_janji`/`id_forms`/`id_mahkamah` were added as plain cols → **no index**. |
| `temu_janji` | `id_khidmat_nasihat`, `id_pegawai_kn`, `status` | The KN⇄TJ reverse lookup and PKN dashboards scan. |
| `butiran_peguam_panel_2` | (`kpBaru` is unique — ok); add `permohonan_status`, `semakan_ppuu` | Application queues filter by status (map 01 §4A). |
| `butiran_peguam_panel` v1 | `kpBaru` | `PeguamPanel::butiran()` joins on `kpBaru`; v1 has no index on it. |
| `peguam_panel` | `kp_peguam` | Lawyer master looked up by IC everywhere; only the surrogate `id` is keyed. |
| `pegawai_jbg` | `cawangan`, `status_aktif` | Officer pick-lists filter by branch + active. |
| `posters` | `status_poster` | Board lists by status. |
| `forms` | `status_agihan`, `agih_kepada` | Map 02 §admin-badge counts `forms WHERE status_agihan IN ('9','14')`; map 01 agihan queues filter `status_agihan` constantly. Currently only `status` (not `status_agihan`) is indexed. |
| `maklum_balas` | (`khidmat_nasihat_id` unique — ok) | — |

---

## 5. Source-of-truth conflicts (KEY FINDING)

| # | Conflict | Detail |
|---|---|---|
| C1 | **Three schema sources for lawyer-panel + `sejarah_ppuu` + `forms`** | (a) 2in1 reconstructed Blueprints, (b) `sistemspk.sql` actual data dump, (c) `sistem-rekod-kes-laravel/database/migrations/2026_06_29_154458_*` (29 tables, "best schema reference" per map 02). They are not guaranteed column-identical. Pick ONE (recommend the data dump for `_3..6`/`sejarah_ppuu`, since it is what real data must load into) and reconcile the others to it. |
| C2 | **`users_peguam_panel_3` exists in code (ETL) but not in any dump** | `ImportLegacyData` + `schema-design.md` reference it; `sistemspk.sql` has 0 occurrences. ETL will fatal. |
| C3 | **`forms` column count** | model docblock says "94 cols"; map 08 says "≈98"; actual `legacy-domain.sql` block = **92 base** + 4 drifted = **96**; map 02 documents 8 more rekod-kes columns absent from 2in1 (§8). No single number is correct. |
| C4 | **PKN officer** | `khidmat_nasihat.id_pegawai_kn` (FK, authoritative) vs `temu_janji.id_pegawai_kn` (snapshot, no FK). Two homes for "who is the advisory officer". The migration explicitly says the case "owns it" — so `temu_janji.id_pegawai_kn` is a denormalised copy that can drift. |
| C5 | **KN ⇄ TemuJanji link** | `khidmat_nasihat.id_temu_janji` and `temu_janji.id_khidmat_nasihat` both persisted, neither FK. Either can be set without the other (services set them in separate `update()` calls). |
| C6 | **Branch identity** | legacy `forms.cawangan` is a **string**; new domain uses `cawangan.id`. `cawangan.nama` is kept = the legacy string to bridge them. Until legacy `forms` migrates to `cawangan_id`, the branch key is a string in one half of the system and an id in the other. |
| C7 | **Lawyer key** | master `peguam_panel.kp_peguam` vs detail `_2..6.kpBaru` vs login `users.id_peguam_panel`/`username`. Three column names for the same IC across the lawyer subsystem. `PeguamPanel::butiran()` even bridges `kp_peguam ↔ kpBaru`. |
| C8 | **Role matrix seeded by two sources** | `RolePermissionSeeder` (8 roles) + migration `130002` (awam). The `awam` role/permission is invisible to the seeder's protection list (F6). |

---

## 6. Duplicate tables & duplicate columns

### Duplicate / overlapping TABLES
- **`butiran_peguam_panel` (v1) vs `butiran_peguam_panel_2`** — two lawyer-application tables; v1 is in `legacy-domain.sql` but **absent from the big data dump** → likely empty/superseded. v2 is the live approval record. Candidate to retire v1 after data check (§11).
- **`mahkamah_sivil` vs `mahkamah_syariah`** — byte-identical column shape (`nama_mahkamah, negeri_mahkamah, lokaliti_mahkamah, jenis_mahkamah`). Could be ONE `mahkamah` table + `jenis` discriminator (mirrors the `cawangan.jenis` pattern the project already adopted). The KN `id_mahkamah` polymorphic no-FK link (F4) is a direct symptom of this split.
- **`butiran_peguam_panel_2_original`** — legacy backup table in the data dump; **not** carried into 2in1 (correct).
- **`items`** — generic demo table, no consumer (§9).

### Duplicate / repeated COLUMNS (within `forms`)
The monolith carries multiple columns of overlapping intent (case-type captured ~5 ways):
`kategori_kes`, `kategori_kes2`, `kategori_kes_borang`, `jenis_kategori`, `pengantaraan_kategori_kes`,
plus `jenis_kes` (→`ref_kes.id_kes`), `jenis_jenayah`. Denormalised actor-name strings duplicate identity
already held elsewhere: `nama_pegawai`, `nama_pegawai_yang_dapat_kes`, `didaftarkan_oleh` (and rekod-kes
adds `nama_pegawai_pengesahan`). These are **repeated-data-entry** smells — the same fact typed into
several free-text columns with no constraint tying them together.

### Duplicate columns ACROSS tables (denormalisation)
- `temu_janji.id_pegawai_kn` duplicates `khidmat_nasihat.id_pegawai_kn` (C4).
- `cawangan` string lives on `forms`, `pegawai_jbg`, and (as `nama`) `cawangan` (C6).
- Lawyer IC under three names: `kp_peguam` / `kpBaru` / `id_peguam_panel` (C7).
- `kelulusanAkademik`, `tahunPengalaman`, `bilanganKes`, `keteranganKes`, firm/bank/CLP/CSO fields
  exist in **both** `butiran_peguam_panel` (v1) and `butiran_peguam_panel_2` (+ now split into `_3/_4/_5`).

---

## 7. Unused columns / tables, orphaned-record & invalid-relationship risks

### Unused / dead tables (candidates — DO NOT drop without §11)
| Table | Model | Controller/route | Verdict |
|---|---|---|---|
| `items` | `Item` (exists) | **none** (grep: only the model file + ETL list reference it; 0 routes) | **DEAD candidate.** Also absent from the big data dump. |
| `ref_lokasi_berguam` | `RefLokasiBerguam` | **none** (no controller, no route) | **Near-dead lookup** — only feeds legacy lawyer-panel `lokasiBerguam*` form data. Keep as read-only ref or fold into `_3`. |
| `butiran_peguam_panel` (v1) | `ButiranPeguamPanel` | none active (only `PeguamPanel::butiran()` HasOne) | Superseded by `_2`; empty in data dump. Retire candidate. |
| `jobs`, `job_batches`, `failed_jobs` | none | none | Queue infra present, **no jobs/workers defined** in the app. Keep (cheap, future use) but flag as currently unused. |
| `model_has_permissions` | (spatie) | none | Direct-grant pivot — **unused** (seed grants via roles only). Keep (spatie contract). |
| `cache`, `cache_locks` | none | none | Only used if cache driver=database. Keep. |

### Unused columns (candidates)
- `khidmat_nasihat.id_forms` — written by the "Buka Kes" bridge but the docblock marks slice-C as
  "gated on a pending product decision." Verify it is actually exercised before trusting it.
- `forms` drifted cols `justifikasi_rujuk_pp`, `justifikasi_lain_rujuk_pp`, `status_rekod`,
  `tarikh_mohon_khidmat_pp` — migration says "currently unpopulated in source." Confirm consumers exist.
- `posters.modified_by/modified_at`, `forms.alasan_kesilapan_no_fail`, `forms.alasan_pemindahan_fail`
  — low-traffic legacy columns; verify report usage before any cleanup.

### Orphaned-record risks (consequence of the missing FKs in §3)
- **`laporan_kes` rows** whose `id_kes` no longer matches a `forms.id` (varchar vs int — cast bugs)
  become invisible orphans; no FK to catch them.
- **`uploaded_files`** with `id_kes`/`id_khidmat`/`kpBaru` pointing at deleted parents → orphaned blobs.
- **`sejarah_ppuu` / `temu_janji` / `khidmat_nasihat`** soft-links: deleting a `forms` row or a
  `temu_janji` row leaves dangling integer pointers (no `nullOnDelete`/`cascade` because no FK).
- **Lawyer fragments** (`_3..6`, `uploaded_files.kpBaru`) orphan if the `_2` master IC changes or the
  application is deleted — string join, no cascade.

### Invalid / fragile relationships
- `laporan_kes.id_kes varchar(20) → forms.id int` — **type-invalid**; the only "relationship" relying
  on string↔int coercion.
- `PeguamPanel::butiran()` joins `peguam_panel.kp_peguam ↔ butiran_peguam_panel.kpBaru` — **cross-named
  string keys**, no index on the v1 side, no FK.
- `khidmat_nasihat.mahkamah()` resolves to two tables at runtime by `jenis_mahkamah_pihak` — a
  **polymorphic pointer** that no FK can enforce (symptom of the sivil/syariah split, §6).
- `User::lawyerProfile()` belongsTo `PeguamPanel` via `id_peguam_panel ↔ kp_peguam` — docblock itself
  says "Tentative key; confirm at ETL." Unverified relationship.

---

## 8. The `forms` monolith — column-level review

`forms` is the litigation case spine. **Verified counts:** the `legacy-domain.sql` `CREATE TABLE forms`
block defines **92 columns** (last is `is_duplicate`); the `…000004` migration adds **4 drifted** cols
→ **96 total** in 2in1. Map 08 says "≈98"; the model docblock says "94". **None match** (C3).

**Columns documented in legacy rekod-kes (map 02 §3) but ABSENT from 2in1 `forms`** (verified by grep
on `legacy-domain.sql` + the drifted migration — all return 0):

| Missing column | Domain (rekod-kes) |
|---|---|
| `nama_pegawai_pengesahan` | decision — verifying officer |
| `tarikh_pengesahan` | decision — verification date |
| `alasan_pembatalan` | decision — cancellation reason |
| `jenis_kes_lain` | case-type "other" free text |
| `nyatakanLain` | misc "specify other" |
| `alasan_tidak_rujuk_pengantaraan` | mediation — not-referred reason |
| `alasan_gagal_pengantara` | mediation — failure reason |
| `alasan_tidak_setuju_pengantara` | mediation — disagreement reason |

> **Parity finding:** if these are live in production `sistem-rekod-kes`, the 2in1 `forms` ETL will
> silently drop them (the `sharedColumns()` intersect logic skips any column not in the target).
> Confirm against the **live** `sistemspk.forms` (not the stale `.sql`) and add the missing columns
> before ETL, or accept documented data loss.

**Column-name smells inside `forms`** (cosmetic, deferred): mixed casing (`tarikh_KPKemaskini`,
`sebab_Tidak_Diluluskan`, `tarikh_pengarahKemaskini`) beside snake_case; the 5-way case-type duplication
(§6); free-text status (`forms.status`, `status_sidang`, `status_agihan` are unconstrained strings with
historical drift `'1'`/`'TOLAK'`/`'Dalam Proses …'` per map 02).

**Decomposition (deferred, per model docblock):** `forms` → `kes` (core) + `kes_keputusan` +
`kes_pengantaraan` + `kes_mahkamah` + `kes_agihan` detail tables. **Not yet done; out of scope to
execute here** — listed so the §11 procedure is applied if/when attempted.

---

## 9. Inconsistent naming & charset (verified)

| Issue | Evidence |
|---|---|
| **camelCase vs snake_case** | Lawyer tables: `kpBaru`, `noTelBimbit`, `statusAktif`, `namaFirma`, `tarikhMohon`. Everywhere else snake_case. Reconstructed `_3..6` deliberately keep camelCase "to match `_2` + legacy". |
| **`ref_cuti` is `CHARSET=latin1`** | `legacy-domain.sql:372`. Every other table is `utf8mb4`. JOIN/`LIKE`/collation-mismatch risk. |
| **`posters` is `utf8mb4_0900_ai_ci`** | `legacy-domain.sql:359`. All others `utf8mb4_general_ci`. Mixed collation → implicit-conversion errors on cross-table string compares. |
| **PK naming varies** | `id` / `id_cuti` (`ref_cuti`) / composite `(id, kp_oyd)` (`butiran_oyd`) / surrogate-added `peguam_panel.id`. |
| **Integer width split** | legacy `int` PKs vs new `bigint` (`id()`/`foreignId`) — root of the FK-mismatch family (§3). |
| **Lawyer IC naming** | `kp_peguam` (master) vs `kpBaru` (details) vs `id_peguam_panel`/`username` (login). (C7) |
| **Repeated branch string** | `forms.cawangan`, `pegawai_jbg.cawangan`, `cawangan.nama` all hold the same human string. (C6) |

---

## 10. Repeated data entry

- **Case-type** captured up to 5 times per case in `forms` (§6) instead of once via FK to `ref_kes`.
- **Actor names** (`nama_pegawai`, `nama_pegawai_yang_dapat_kes`, `didaftarkan_oleh`,
  `khidmat_nasihat.cipta_oleh`/`kemaskini_oleh`, `sejarah_*` `modifiedBy`/`createdBy`) stored as
  free-text strings rather than `users.id` — the same person re-typed across rows; map 01 §9 flags the
  lawyer↔case join on **name string** (`forms.nama_pegawai_yang_dapat_kes = butiran_*.namaPeguam`) as
  brittle.
- **PKN officer** entered into both `khidmat_nasihat` and `temu_janji` (C4).
- **Lawyer profile** re-keyed across `_2.._6` by IC string (F5) — one logical edit touches up to 5 tables
  with no transactional FK guarantee.

---

## 11. Safe removal / change procedure (MANDATORY before ANY drop or rename)

No column or table identified above may be dropped on the strength of this audit alone. For **each**
proposed removal/rename, run this gate and record the evidence:

1. **Search usage (code).**
   `grep -rniE "<table>|<column>"` across `app/`, `routes/`, `resources/views/`, `database/`,
   `config/`, and every legacy source repo (`sistem-peguam-panel`, `sistem-rekod-kes`,
   `be_iguaman`/`fe-iguaman`). Include Eloquent `$table`/`$casts`/relationship method names and Blade.
2. **Search usage (data).**
   `SELECT COUNT(*)`, `COUNT(<column>)`, and `COUNT(*) WHERE <column> IS NOT NULL` on **live**
   `sistemspk` *and* `iguaman_2in1`. A column "unused in code" may still hold historical values needed
   for reports/audit.
3. **Check reports / exports / APIs / jobs.**
   Grep the statistik/laporan controllers, dompdf/maatwebsite exports, the chatbot proxy payloads, and
   `app/Console/Commands/ImportLegacyData.php` (`$verbatim`, `$peguamPanelCols`, `importUsers`). A
   column dropped here will break the ETL's `sharedColumns()` intersect or an export header.
4. **Confirm historical / regulatory dependence.**
   For legal-aid case data (JBG/BHEUU), confirm retention rules. `audit_trail`, `forms` decision
   columns, and financial (`nilai_sumbangan`, `jumlah_bayaran`) fields are presumed retained.
5. **Plan the migration.**
   Write a reversible Blueprint migration (`up`/`down`). For legacy raw-SQL tables, ALTER in a new
   migration — never edit `legacy-domain.sql` after it has run anywhere. For FK adds, first widen the
   referenced legacy `int` PK to `bigint`, backfill, then `foreign()…`.
6. **Archive before destruct.**
   `CREATE TABLE <t>_archive_YYYYMMDD AS SELECT * FROM <t>` (or `mysqldump` the table) and store the
   artefact. For columns, snapshot the column into the archive table before `dropColumn`.
7. **Test.**
   Run the full migration up+down on a clone, run `legacy:import --fresh` against a sandbox `sistemspk`,
   execute the seeders, and smoke-test the affected screens + every report that referenced the target.
   Only then promote.

**Worked example — retiring `items`:**
(1) grep → only `app/Models/Item.php` + the ETL `$verbatim` list, **0 routes/views**.
(2) data → check `iguaman_2in1.items` row count (legacy had 13; absent from big dump).
(3) reports → none. (4) historical → demo/scaffold data, no regulatory value.
(5) migration: drop `Item` model + remove `'items'` from `ImportLegacyData::$verbatim` + a
`dropIfExists('items')` migration. (6) archive `items_archive_YYYYMMDD`. (7) test ETL + boot.
→ Only after all 7 pass is the drop safe. **This audit stops at "candidate"; it does not drop.**

---

## 12. Prioritised remediation backlog (no code changed here)

| Pri | Action | Addresses |
|---|---|---|
| P0 | Remove/repoint `users_peguam_panel_3` in `ImportLegacyData`; locate the dump that actually has it, or drop the tier. | F2 / C2 |
| P0 | Reconcile `_3/_4/_5/_6` + `sejarah_ppuu` Blueprints against the **real** `sistemspk.sql` data dump; pick one source of truth. | F1 / C1 |
| P0 | Add the 8 missing `forms` columns (or sign off documented loss) before ETL. | F3 / C3 / §8 |
| P0 | Add `awam` to `RolePermissionSeeder::ROLES` + `RoleController::SYSTEM_ROLES`. | F6 / C8 |
| P1 | Clean+cast `laporan_kes.id_kes` to int, then add FK → `forms.id`. | F3 / §3 |
| P1 | Decide the PKN + KN⇄TJ single home; drop or sync the duplicate. | C4 / C5 |
| P1 | Add the missing indexes in §4 (esp. `forms.status_agihan`, `laporan_kes.id_kes`, `khidmat_nasihat.id_pegawai_kn`/`id_pengguna`/`cawangan_id`, `temu_janji.id_khidmat_nasihat`). | §4 |
| P2 | Widen legacy `int` reference PKs to `bigint` and add the deferred FKs (`sejarah_ppuu.id_kes`, `uploaded_files.*`, `khidmat_nasihat.id_*`). | §3 |
| P2 | Normalise charsets: `ref_cuti` latin1→utf8mb4, `posters`→`utf8mb4_general_ci`. | F7 / §9 |
| P3 | Collapse `mahkamah_sivil`+`mahkamah_syariah` → one `mahkamah` + `jenis`; retire `id_mahkamah` polymorphism. | F7 / §6 |
| P3 | Run the §11 gate on `items`, `ref_lokasi_berguam`, `butiran_peguam_panel` v1. | §7 |
| P3 | Decompose `forms` monolith (deferred). | §8 |

---

## 13. Files read during this review (evidence trail)

- **Maps:** `docs/consolidation-audit/maps/{01,02,03,08}.md` (+ skim of 05–07/09 via map 08).
- **Schema:** `database/schema/legacy-domain.sql` (full); `../sistemspk.sql` (CREATE-TABLE name grep only — 51.7 MB, not read whole).
- **Migrations (all 28):** `0001_01_01_000000..000002`; `2026_06_29_000001..000005`; `2026_06_29_194444`; `2026_06_30_000001..000010`; `2026_06_30_100001..100003`; `2026_06_30_110001..110004`; `2026_06_30_120001/120002/120010`; `2026_06_30_130001..130004`.
- **Models:** `Form`, `User`, `UploadedFile`, `LaporanKes`, `PeguamPanel`, `ButiranPeguamPanel`, `ButiranPeguamPanel2`, `Oyd`, `MahkamahSivil/Syariah`, `Cawangan`, `RefNegeri`, `RefKes`, `AuditTrail`, `SejarahPpuu`, `SejarahPeguamPanel`, `KhidmatNasihat`, `TemuJanji`, `Item`.
- **ETL / services:** `app/Console/Commands/ImportLegacyData.php`; grep of `KhidmatProsesService`, `KhidmatNasihatService`, `KhidmatNasihatController`, `KhidmatProsesController`, `KhidmatNasihatRequest`.
- **Seeders:** `RolePermissionSeeder.php` (full); `DatabaseSeeder`/`Batch8MastersSeeder`/`RefNegeriSeeder` (inventory).
- **Verification greps:** `items`/`ref_lokasi_berguam` route usage (none); `CREATE TABLE` name sets in both dumps; `users_peguam_panel_3` occurrence count (0); rekod-kes drift columns presence in 2in1 `forms` (0/8).
