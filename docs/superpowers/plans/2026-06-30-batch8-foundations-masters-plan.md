# Batch 8 — Foundations / Masters (iGuaman Janji Temu port)

> Reference data backbone the Khidmat Nasihat / Janji Temu batches (9-13) depend on.
> Plan date 2026-06-30. Builds on [parity map](2026-06-30-iguaman-janjitemu-parity-map.md). Branch: continue off `batch-7-rbac` (or new `batch-8-masters`).

---

## Goal

Stand up the master/reference tables + CRUD admin UI that batches 9-13 consume:
`cawangan` (JBG/JKM/Penjara, optionally Mahkamah) + `bilik`, the 3-level KN category tree, and `ref_jawatan`. No citizen flow, no appointments yet — pure foundations.

**Done when:** each master has migration + model + seeder + gated CRUD (list/create/edit/delete) + FormRequest validation + `Audit::log` + feature tests, and `CawanganScope` still works against the reconciled branch identity.

---

## Scope

In:
- `cawangan` master (type JBG/JKM/PENJARA) + `bilik` (rooms, JBG only)
- KN category tree: `ref_kategori_kn` → `ref_kategori_kes_kn` → `ref_subkategori_kn` (3 levels, matches Nuxt FE)
- `ref_jawatan` (staff job titles)
- 3 new Spatie permissions + role grants
- Seeders from live data + source lists

Out (later batches):
- Branch session/weekend config + operating hours → **batch 10** (calendar/slot)
- Cuti umum/negeri → batch 10 (reuse `ref_cuti`)
- Any citizen/appointment/wizard surface → batch 9+

---

## Decisions (RESOLVED 2026-06-30)

| # | Decision | Outcome |
|---|---|---|
| D1 | **Mahkamah** | ✅ **Reuse existing** `mahkamah_sivil`/`mahkamah_syariah` (used by rekod-kes 1-6). `cawangan` enum = **JBG/JKM/PENJARA only**. KN court refs point at existing mahkamah tables. |
| D2 | **CawanganScope identity** | ✅ `cawangan.nama` = exact legacy string (`"JBG PUTRAJAYA"`). Keep scope string-based; new KN tables carry `cawangan_id` FK too. No legacy string→FK backfill this batch. |
| D3 | Category tree levels | ✅ Build 3 tables (top/kes/sub) to match FE. |
| D4 | Reference seed data source | ✅ "Pull from .NET source" chosen — **source dig done; partial result (see Seeders).** |

---

## Schema (MySQL, bigint PK, snake_case, real FKs, InnoDB utf8mb4)

### `cawangan`
| Col | Type | Notes |
|---|---|---|
| id | bigint PK | |
| jenis | enum(`JBG`,`JKM`,`PENJARA`) | branch type |
| kod | string(20) nullable | short code |
| nama | string | **= legacy `cawangan` string** for scope match (e.g. `JBG PUTRAJAYA`) — unique |
| negeri_id | FK→`ref_negeri.id` nullable | |
| alamat1/2/3 | string nullable | |
| poskod | string(10) nullable | |
| no_tel | string(30) nullable | |
| status_aktif | bool default 1 | |
| timestamps | | |

### `bilik` (rooms — JBG branches; consumed by slot-gen batch 10)
| Col | Type | Notes |
|---|---|---|
| id | bigint PK | |
| cawangan_id | FK→`cawangan.id` cascade | |
| nama_bilik | string | |
| status_aktif | bool default 1 | |
| timestamps | | |

### `ref_kategori_kn` (level 1 — advisory category)
`id, jenis_kategori (string), aktif (bool), timestamps`

### `ref_kategori_kes_kn` (level 2 — case type under category; .NET `JenisKes`)
`id, kategori_id FK→ref_kategori_kn cascade, nama (string), aktif (bool), timestamps`

### `ref_subkategori_kn` (level 3 — sub-type; FE-only level)
`id, kategori_kes_id FK→ref_kategori_kes_kn cascade, nama (string), aktif (bool), timestamps`

### `ref_jawatan`
`id, nama (string) unique, aktif (bool), timestamps`

> All `aktif`/`status_aktif` default true. FKs `onDelete('cascade')` for tree children, `restrict`/`nullSet` for negeri.

---

## Models

`App\Models\Cawangan` (hasMany `bilik`, belongsTo `RefNegeri`; scope by `jenis`), `App\Models\Bilik`, `App\Models\RefKategoriKn` (hasMany kes), `App\Models\RefKategoriKesKn` (hasMany sub), `App\Models\RefSubkategoriKn`, `App\Models\RefJawatan`. `#[Fillable(...)]`, `aktif`/`status_aktif` bool cast. No `CawanganScope` on these masters (they're global reference).

---

## Permissions (Spatie — extend batch-7 set)

New: `selenggara.cawangan`, `selenggara.kategori_kn`, `selenggara.jawatan`.
Grant to: `admin` (super, auto), `koordinator`, `pengarah`, `ketua_pengarah` (match existing `selenggara.*` grantees). Add to `RolePermissionSeeder` + a migration that syncs perms onto roles.

---

## Routes (staff area, inside `['auth','permission:system.view']` group)

Follow existing `selenggara.*` nested pattern (cf. `ref-kes`, `cuti`):

```
Route::middleware('permission:selenggara.cawangan')->group(function () {
    Route::resource-style: cawangan.index/create/store/edit/update/destroy
    nested: cawangan/{cawangan}/bilik (index/store/update/destroy)
});
Route::middleware('permission:selenggara.kategori_kn')->group(...);   // 3-level tree CRUD
Route::middleware('permission:selenggara.jawatan')->group(...);
```

Naming: `cawangan.*`, `kategori-kn.*` (+ `kategori-kes-kn.*`, `subkategori-kn.*`), `jawatan.*`.

---

## Controllers + views

- `CawanganController`, `BilikController`, `RefKategoriKnController` (+ kes/sub or one nested controller), `RefJawatanController`.
- Pattern (per agent's convention scan): private `rules()` OR dedicated `FormRequest` for cawangan; inline `$request->validate()` for simple refs; `Audit::log(table,id,action,remark)` on every write; route-model binding; `redirect()->route('x.index')->with('status',...)`.
- Views under `resources/views/cawangan/`, `kategori-kn/`, `jawatan/` using `@extends('layouts.staff')`. Add sidebar links gated by `@can('selenggara.cawangan')` etc.
- Category tree UI: index lists level-1; drill into `{kategori}/kes` then `{kes}/subkategori` (mirror FE `senarai-kategori/_id` nesting).

---

## Seeders — source-dig outcome (2026-06-30)

The .NET migrations carry **zero seed inserts**; reference tables were filled by direct DB insert against the real Postgres (unavailable). FE is API-driven. So only part is reconstructable from source:

**Seedable NOW (real data):**
- `CawanganSeeder` (JBG) — ~30 branches from live 2in1 strings (`forms`/`pegawai_jbg`/`users.cawangan`): JBG PUTRAJAYA, WP KUALA LUMPUR, SELANGOR, NEGERI SEMBILAN, MELAKA, JOHOR, MUAR, PAHANG, RAUB, TERENGGANU, KELANTAN, GUA MUSANG, PERLIS, KEDAH, LANGKAWI, PULAU PINANG, PERAK, TAIPING, SARAWAK, MIRI, SIBU, SABAH, WP LABUAN. Map → `negeri_id`; strip trailing-comma ETL artifacts.
- `RefJawatanSeeder` — 10 real titles from `pegawai_jbg.jawatan`: PENGARAH LITIGASI DAN NASIHAT SIVIL, PEGAWAI UNDANG-UNDANG, PENOLONG PEGAWAI UNDANG-UNDANG, PENGARAH LITIGASI DAN NASIHAT SYARIAH, PEGAWAI SYARIAH, PENOLONG PEGAWAI SYARIAH, PENGARAH PENGANTARAAN SYARIAH, PENGARAH PEGUAM PANEL DAN PENDAMPING GUAMAN, PENGARAH NEGERI, KETUA CAWANGAN.
- `KategoriKnSeeder` **level-1 only** — from FE eligibility screening: **SIVIL, SYARIAH, PENDAMPING JENAYAH, PENDAMPING GUAMAN** (the 4 "Jenis Khidmat"). PENDAMPING GUAMAN↔JKM context, PENDAMPING JENAYAH↔Penjara context.

**NOT in any source — defer / stakeholder-supply:**
- `cawangan` JKM + PENJARA branch lists → ship empty, supply later (CRUD UI lets staff add).
- KN category **levels 2 & 3** (`ref_kategori_kes_kn`, `ref_subkategori_kn`) → empty; real tree from JBG stakeholders or the live Postgres dump.

Register all in `DatabaseSeeder`, idempotent `updateOrCreate`.

**Carry to batch 9 (KN wizard) — eligibility/payment logic recovered from FE screening:** income > RM50,000 (Sivil/Syariah) → "Laluan Sumbangan" RM260; default RM10; Penjara + JKM → RM0 (dikecualikan); `isPercuma` full exemption toggle (Pembantu Tadbir).

---

## Tests (Pest/PHPUnit feature)

- Each master: index renders, store validates + persists + audits, update, destroy, **permission gate** (403 without perm, 200 with), unique constraints.
- `CawanganScope` regression: a staff user with `cawangan='JBG KEDAH'` and without `cawangan.view-all` still sees only their branch on a scoped model (confirm master add didn't break scope).
- Tree cascade: deleting a `ref_kategori_kn` cascades kes + sub.
- Target: keep suite green (batch-7 was 91/91).

---

## Task checklist (build order)

1. Migrations: `cawangan`, `bilik`, `ref_kategori_kn`, `ref_kategori_kes_kn`, `ref_subkategori_kn`, `ref_jawatan`.
2. Permission migration + `RolePermissionSeeder` update (3 new perms → roles).
3. Models + relations + casts + fillable.
4. Seeders (cawangan from live data, jawatan, kategori tree) + DatabaseSeeder wiring.
5. Controllers + FormRequests + routes (gated groups).
6. Blade views + sidebar `@can` links.
7. Feature tests (CRUD + gate + scope regression + cascade).
8. `php artisan migrate:fresh --seed` clean run; full suite green.
9. Commit atomically per master; update parity map §1 statuses 🟥→🟩.

---

## Risks / notes

- **Legacy `cawangan` string hygiene**: `users.cawangan` has trailing commas / multi-values (ETL artifact). Seeder must normalize; consider a data-cleanup migration so `CawanganScope` matches cleanly.
- **JKM / Penjara branch lists** not in current data — need source (flag to user; can ship JBG-only first).
- **Mahkamah (D1)** — if user insists on unified master, add type=MAHKAMAH + migrate `mahkamah_sivil`/`syariah` (extra task, regression-test rekod-kes).
- **Category tree source data** — confirm the real kategori/kes/sub values (from JBG, not invented). The .NET `Kategoris` table was empty-seeded; pull the canonical list from FE option arrays or stakeholders.
- Keep `users.role` mirror in sync per locked RBAC decision when granting new perms.

---

*Next: confirm D1 (mahkamah) + source JKM/Penjara/kategori lists, then execute checklist step 1.*
