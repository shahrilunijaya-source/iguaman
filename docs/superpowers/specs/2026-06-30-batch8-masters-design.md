# Batch 8 — Foundations / Reference Masters — Design Spec

**Date:** 2026-06-30
**Status:** Approved (design); **revised to align with the parity-map** (`docs/superpowers/plans/2026-06-30-iguaman-janjitemu-parity-map.md`, the FE-source-of-truth read); pending final spec review.
**Scope:** Build the reference masters the advisory/appointment subsystem (batches 9–13) consumes — the cawangan family (`cawangan_jbg` + `bilik`, `cawangan_jkm`, `cawangan_penjara`), the 3-level advisory category tree (`kn_kategori` → `kn_kategori_kes` → `kn_subkategori`), and `ref_jawatan` — with seed data, Tetapan CRUD UI, and RBAC gating.

> Part of `context/port-plan-iguaman-janjitemu.md`; complements the parity-map. Depends on **batch 7 (RBAC)** merged to `main`. This spec covers **batch 8 only**. **Build to the FE contract; the .NET snapshot is schema/seed hints only.**

---

## 1. Goals / Non-goals

**Goals**
- Create masters: `cawangan_jbg`, `bilik`, `cawangan_jkm`, `cawangan_penjara`, `kn_kategori`, `kn_kategori_kes`, `kn_subkategori`, `ref_jawatan`.
- Seed them: `cawangan_jbg` reconciled from live data (scope continuity) + source; the rest ported from .NET `InitialData` (where present); `kn_subkategori` from FE/admin (absent in .NET).
- Tetapan CRUD UI for each family, gated by new granular `selenggara.*` permissions.
- Zero disruption to `CawanganScope` (string-keyed on `cawangan_jbg.kod`).

**Non-goals (explicit)**
- **No `cawangan_mahkamah` table** — reuse existing `mahkamah_sivil` / `mahkamah_syariah` (consolidate at consumption in batch 9+).
- **No `negeri` CRUD** — reuse existing `ref_negeri` (16 states) read-only.
- **No `cawangan_id`/master FK migration** of `users`/`forms` — scope stays string-keyed on `cawangan_jbg.kod`.
- No advisory application, appointment, slot, or calendar logic (batches 9–13) — these masters are consumed there. (`bilik` is built here as a cawangan_jbg child; slot generation that USES bilik is batch 10.)
- No public-facing screens (batch 13).

---

## 2. Locked decisions

| # | Decision | Source |
|---|---|---|
| Kategori | **3-level tree, 3 tables**: `kn_kategori` (top = jenis: Sivil/Jenayah/Syariah) → `kn_kategori_kes` → `kn_subkategori`. NOT an enum. | parity-map §1 (FE has 3-level CRUD) |
| Cawangan | **Separate** `cawangan_jbg` (+`bilik` rooms), `cawangan_jkm`, `cawangan_penjara`. Mahkamah reuses `mahkamah_*`. | parity-map §1 |
| Jawatan | **Build `ref_jawatan`** this batch | parity-map §8 |
| Negeri | Reuse `ref_negeri` read-only | parity-map §1/§5 |
| Master perms | Granular per family: `selenggara.cawangan` (jbg+bilik+jkm+penjara), `selenggara.kategori` (tree), `selenggara.jawatan` | brainstorm + grouping rationale §5 |
| Cawangan ↔ scope | String-key: `cawangan_jbg.kod` = existing scope string; lookup table, no FK migration | port plan / batch-7 |
| Naming | `cawangan_*`, `kn_*`, `ref_*` prefixes | parity-map |

---

## 3. Schema

Laravel Blueprint migrations (greenfield — NOT the raw `legacy-domain.sql` dump). **Int PKs** to match the legacy/`ref_negeri` int convention: `$table->increments('id')`, FK cols `$table->unsignedInteger('negeri_id')->nullable()`. Laravel `timestamps()` on these new tables (greenfield). All models `$guarded = ['id']`.

### 3a. `cawangan_jbg` (service branches — scoped)
| Column | Type | Notes |
|---|---|---|
| id | increments | |
| kod | string unique | **= scope key** (= existing `users.cawangan`/`forms.cawangan`) |
| nama | string | |
| negeri_id | unsignedInteger nullable → ref_negeri | |
| hari_minggu | string nullable | weekend config (e.g. `SAT_SUN` / `FRI_SAT`) for slot engine (batch 10) |
| aktif | boolean default true | |

`Cawangan` model name clash risk — name it `CawanganJbg`. `belongsTo(RefNegeri,'negeri_id')`, `hasMany(Bilik,'cawangan_jbg_id')`.

### 3b. `bilik` (rooms within a JBG branch)
| Column | Type |
|---|---|
| id | increments |
| cawangan_jbg_id | unsignedInteger → cawangan_jbg (index) |
| nama | string |
| aktif | boolean default true |

`Bilik belongsTo(CawanganJbg)`. (Consumed by slot generation in batch 10; built here as the branch child master.)

### 3c. `cawangan_jkm` / `cawangan_penjara` (referring-agency lookups — NOT scoped)
| Column | Type |
|---|---|
| id | increments |
| kod | string unique |
| nama | string |
| negeri_id | unsignedInteger nullable → ref_negeri |
| aktif | boolean default true |

Models `CawanganJkm`, `CawanganPenjara`. Drive fee-waiver in batch 9.

### 3d. `kn_kategori` (top — advisory case type)
| Column | Type | Notes |
|---|---|---|
| id | increments | |
| nama | string | e.g. Sivil / Jenayah / Syariah (seeded from .NET `Kategori`) |
| aktif | boolean default true | |

### 3e. `kn_kategori_kes` (mid — category under a kategori)
| Column | Type |
|---|---|
| id | increments |
| kategori_id | unsignedInteger → kn_kategori (index) |
| nama | string |
| aktif | boolean default true |

### 3f. `kn_subkategori` (leaf — FE-only, absent in .NET)
| Column | Type |
|---|---|
| id | increments |
| kategori_kes_id | unsignedInteger → kn_kategori_kes (index) |
| nama | string |
| aktif | boolean default true |

Models: `KnKategori hasMany KnKategoriKes hasMany KnSubkategori` (+ inverse `belongsTo`).

### 3g. `ref_jawatan` (staff designation)
| Column | Type |
|---|---|
| id | increments |
| nama | string |
| aktif | boolean default true |

---

## 4. Cawangan ↔ CawanganScope reconciliation

- `CawanganScope` (batch 7) filters `model.cawangan = user.cawangan` (string) unless `cawangan.view-all`. **Unchanged.**
- `cawangan_jbg` is a lookup keyed by `kod`; `kod` MUST equal the distinct `cawangan` strings already in `users`/`forms` so the scope keeps resolving.
- `users.cawangan` / `forms.cawangan` stay **string columns** — no FK, no data migration. Branch dropdowns store `cawangan_jbg.kod`.
- `cawangan_jkm`/`cawangan_penjara` are reference lookups, not scope keys.

---

## 5. RBAC additions

Add to `RolePermissionSeeder` MATRIX, each granted `{pengarah, koordinator, ketua_pengarah}` (admin via `Gate::before`):
- `selenggara.cawangan` — gates cawangan_jbg + bilik + cawangan_jkm + cawangan_penjara (one "branch administration" responsibility)
- `selenggara.kategori` — gates the kn_kategori 3-level tree screens
- `selenggara.jawatan` — gates ref_jawatan

**Grouping rationale:** the cawangan family is one admin responsibility (a branch admin manages JBG/JKM/Penjara/rooms together); splitting into 4 perms over-fragments. Kategori + jawatan are distinct → own perms.

**Permission count:** **+3.** Re-read the live `RolePermissionSeeder` first (concurrent work added `selenggara.cuti` → baseline 33) and bump `Batch7SeederTest::test_all_roles_and_permissions_exist` to the new total (33 + 3 = **36**, but verify the live baseline before hardcoding).

Sidebar: links under `@can('menu.selenggara')`, each wrapped in its `@can('selenggara.<x>')`.

---

## 6. Seed strategy

**The .NET backend has ZERO seed rows** (tables were API-populated at runtime — verified). Real sources are **2in1's own existing tables** + the legacy `sistemspk.sql` dump. Idempotent (`updateOrCreate` by natural key). Full extracted data in `scratchpad/batch8-seed.txt`; the plan embeds the literal rows.

### 6a. Kategori tree — derive from existing `ref_kes` (already 3-level!)
`ref_kes` is `jenis_kes` (4) → `kategori_kes` (10) → `deskripsi` (139 leaf). Map 1:1:
- `kn_kategori` ← 4 rows from a fixed jenis-code→nama map: **SIV→Sivil, SYA→Syariah, JEN→Jenayah, PG→Pendamping Guaman**.
- `kn_kategori_kes` ← `SELECT DISTINCT jenis_kes, kategori_kes FROM ref_kes WHERE kategori_kes IS NOT NULL` (10), FK to kn_kategori by jenis.
- `kn_subkategori` ← every `ref_kes` row (139): `kod`(=id_kes), `nama`(=deskripsi), `aktif`(=aktif_kes), FK to kn_kategori_kes by (jenis, kategori_kes). **Add a `kod` column to kn_subkategori** (= ref_kes.id_kes) for advisory-form reference.
- Done in SQL/Eloquent from `ref_kes` — no hardcoded 139 rows. (Advisory taxonomy starts as a copy of the case-type catalog; admin curates independently thereafter.)

### 6b. `cawangan_jbg` — reconcile live + 23 literal rows
1. Seed the **23 `ref_cawangan` branches** (literal, from `scratchpad/batch8-seed.txt`): `kod`(8-digit kodCawangan) | nama | negeri (match `ref_negeri` by nama). MIRI anomaly: negeri = SARAWAK.
2. Reconcile: `SELECT DISTINCT cawangan FROM users ∪ forms` (non-empty); for any value not already a `cawangan_jbg.kod`, insert it (kod=value, nama=value) so `CawanganScope` keeps resolving. Log these.

### 6c. `ref_jawatan` — from existing `pegawai_jbg`
Seed from `SELECT DISTINCT jawatan FROM pegawai_jbg WHERE jawatan <> ''` (current free-text home of designations). If empty, ship a minimal seed.

### 6d. `cawangan_jkm`, `cawangan_penjara`, `bilik` — empty
No data in any source (0 rows). Ship empty; admin populates via CRUD. Tables + CRUD built; `bilik` consumed by batch-10 slot gen.

---

## 7. Tetapan CRUD UI

Each `@extends('layouts.staff')`, mirroring `ref-kes/*` + `pengguna/*` + `peranan/*` conventions (`tap-head`, `tap-card`, `wiz-grid`/`wiz-field`, `btn`/`formerr`, `session('status')`, `@error`, paginated index + form, separate delete form). Thin controllers (RefKesController style: `rules()`, index w/ filters+`paginate(25)`, create/store/edit/update/destroy, `Audit::log($table,$id,$action,$remarks)`).

- **Cawangan JBG** (gated `selenggara.cawangan`): index (kod, nama, negeri, aktif) + form (negeri select from ref_negeri, weekend config) + **nested Bilik management** (list/add/disable rooms for the branch).
- **Cawangan JKM / Penjara** (`selenggara.cawangan`): index + form (kod, nama, negeri, aktif).
- **Kategori tree** (`selenggara.kategori`): kn_kategori index → drill to kn_kategori_kes → drill to kn_subkategori; CRUD at each level.
- **Jawatan** (`selenggara.jawatan`): index + form (nama, aktif).

**Delete semantics:** prefer **soft-disable via `aktif=false`** for masters with downstream references (cawangan, kategori levels). Hard-delete allowed only when no children/references exist; block otherwise with an error (batch-7 role-delete pattern). A `kn_kategori` with `kn_kategori_kes` children → block hard-delete.

---

## 8. Testing (≥80%)

Live-MySQL feature tests (repo convention; seed `RolePermissionSeeder` + relevant seeder in `setUp`, self-clean by tag/prefix):
- **Access gating:** each family's index requires its `selenggara.*` perm (supervisory 200; pegawai/ppuu/pembantu_tadbir redirect; admin 200).
- **CRUD:** create/update/soft-disable per master; FormRequest validation (required nama, valid negeri_id, valid FK parent).
- **Seed reconciliation:** every DISTINCT existing `users.cawangan`/`forms.cawangan` value has a matching `cawangan_jbg.kod` (scope continuity).
- **Kategori tree:** kategori_kes belongs to kategori, subkategori to kategori_kes; delete-guard blocks parents with children.
- **RBAC:** bump `Batch7SeederTest` perm count; assert the 3 new perms exist + granted to exactly the supervisory set.

---

## 9. Risks

| Risk | Mitigation |
|---|---|
| `cawangan_jbg.kod` misses a live scope value → rows mis-scoped | seed from DISTINCT live values FIRST; test asserts full coverage |
| .NET snapshot is **partial** (no subkategori/bilik data, missing subsystems) | build to FE contract; subkategori/bilik ship empty; flag what's unsourced |
| **`forms.tarikh_khidmat_nasihat` already exists** — advisory may be partly modeled in `forms` | INVESTIGATE before batch-9 KN schema; does not block batch-8 masters but informs naming |
| .NET uuid→ref_negeri-by-nama mapping errors | build name→id map; fallback log for unmatched |
| `RolePermissionSeeder` edited concurrently (EPIC G added `selenggara.cuti`) | re-read live seeder; additive only; verify baseline count |
| Master deleted while referenced | soft-disable via `aktif`; guard hard-delete |
| **Concurrent effort building the same subsystem** | coordinate; this batch is foundational masters — confirm ownership before execution |
| Branch timing — touches hot files (seeder/routes/sidebar) under concurrent edit | branch from `main` AFTER batch-7 merges + EPIC F/G settles |

---

## 10. Out of this batch
- `cawangan_mahkamah` (reuse `mahkamah_*`); negeri CRUD; master FK migration of users/forms.
- advisory application / appointment / slot / calendar logic (batches 9–13) — masters consumed there.
- public portal; citizen `awam` user_type.

---

## 11. File-level change map
- migrations: `create_cawangan_jbg_table`, `create_bilik_table`, `create_cawangan_jkm_table`, `create_cawangan_penjara_table`, `create_kn_kategori_table`, `create_kn_kategori_kes_table`, `create_kn_subkategori_table`, `create_ref_jawatan_table`.
- models: `CawanganJbg`, `Bilik`, `CawanganJkm`, `CawanganPenjara`, `KnKategori`, `KnKategoriKes`, `KnSubkategori`, `RefJawatan` (+ relations + `belongsTo RefNegeri`).
- seeders: `Batch8MasterSeeder` (cawangan_jbg reconcile + ported jkm/penjara/kategori/jawatan); wire into `DatabaseSeeder`.
- `RolePermissionSeeder.php` — +3 perms (verify baseline → bump test count).
- controllers: `CawanganJbgController` (+bilik), `CawanganJkmController`, `CawanganPenjaraController`, `KnKategoriController` (+ kategori_kes + subkategori), `RefJawatanController`.
- FormRequests per master.
- routes/web.php — gated `permission:selenggara.*` groups (use `|` for any multi-value — batch-7 lesson).
- views: `cawangan-jbg/`, `bilik/`, `cawangan-jkm/`, `cawangan-penjara/`, `kn-kategori/` (+kes/subkategori), `jawatan/` (index + form); sidebar links.
- tests: per-master CRUD + gating; seed reconciliation; `Batch7SeederTest` count bump.

---

## 12. Batch sequence
**7** RBAC (DONE) → **8** Masters (this) → **9** Khidmat Nasihat wizard → **10** Appointment/slot/calendar (SlotAvailabilityService NET-NEW) → **11** Officer processing → **12** Feedback + reports → **13** Public portal.
