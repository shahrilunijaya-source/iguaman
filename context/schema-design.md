# iGuaman 2in1 — Schema Design (Phase 1)

Source: live `sistemspk` (MariaDB/Laragon), 23 tables. Authoritative dump: `database/schema/` + scratchpad `sistemspk-schema.sql`.

## Strategy: preserve legacy, add Laravel-native auth

Brownfield + migrate existing data → **keep legacy table & column names verbatim** for all 20 domain tables. Reasons:
- ETL becomes a straight copy (no column remapping).
- Later porting of ~156k LOC of legacy SQL maps 1:1 to Eloquent models.
- Renaming 350+ columns to English/snake-convention = high risk, low payoff now. Defer cosmetic renames.

Only the **auth layer is rebuilt Laravel-native** (security-critical): the 3 legacy user tables collapse into one `users` table with bcrypt + roles.

## Table inventory (rows = ETL sizing)

| Table | Rows | Cols | Maps to model | Domain |
|-------|-----:|-----:|---------------|--------|
| `forms` | 59 | 94 | `Form` (Case spine) | shared |
| `laporan_kes` | 29 | 11 | `LaporanKes` | court report (child of forms via id_kes) |
| `butiran_oyd` | 9 | 22 | `Oyd` | applicant/beneficiary |
| `peguam_panel` | 5 | 12 | `PeguamPanel` | lawyer master (**no PK → add id**) |
| `butiran_peguam_panel` | 14 | 60 | `ButiranPeguamPanel` | lawyer profile v1 |
| `butiran_peguam_panel_2` | ~50 | 25 | `ButiranPeguamPanel2` | lawyer application v2 (approval workflow) |
| `sejarah_pegawai` | 18 | 5 | `SejarahPegawai` | officer reassign history (**has FK→forms**) |
| `sejarah_peguam_panel` | 9 | 10 | `SejarahPeguamPanel` | lawyer assign history (id_kes→forms) |
| `sejarah_sidang` | 13 | 5 | `SejarahSidang` | hearing history (id_kes→forms) |
| `pegawai_jbg` | 221 | 7 | `PegawaiJbg` | officer registry |
| `mahkamah_sivil` | 229 | 5 | `MahkamahSivil` | civil court ref |
| `mahkamah_syariah` | 172 | 5 | `MahkamahSyariah` | syariah court ref |
| `ref_kes` | 214 | 7 | `RefKes` | case-type ref |
| `ref_negeri` | 16 | 4 | `RefNegeri` | state ref |
| `ref_lokasi_berguam` | 23 | 2 | `RefLokasiBerguam` | practice-location ref |
| `ref_cuti` | 198 | 6 | `RefCuti` | holiday/leave ref (**latin1**) |
| `uploaded_files` | 26 | 6 | `UploadedFile` | attachments |
| `audit_trail` | 403 | 10 | `AuditTrail` | change log |
| `posters` | 0 | 9 | `Poster` | announcements |
| `items` | 13 | 3 | `Item` | generic list (likely deprecated) |

## Unified auth (replaces 3 legacy tables)

Legacy: `users` (264, staff), `users_peguam_panel_2` (586, lawyers), `users_peguam_panel_3` (116). All plaintext `kata_laluan`, integer `peranan`.

New `users` (Laravel-native):

| Column | Type | Note |
|--------|------|------|
| id | bigint PK | |
| name | string | from legacy `nama` |
| email | string unique | from `emel` |
| username | string null | staff login id |
| password | string | **bcrypt** (legacy plaintext re-hashed at ETL) |
| user_type | enum(staff, lawyer) | which legacy table it came from |
| role | string | admin / pengarah / koordinator / pegawai / peguam |
| cawangan | string null | branch (staff) |
| nokp | string null | IC |
| id_peguam_panel | string null | links lawyer login → peguam_panel |
| is_active | bool | from `status_aktif` |
| last_login_at | datetime null | |
| remember_token, timestamps | | Laravel standard |

**Role map (ETL)** — CONFIRMED from `log_masuk.php` redirect logic (peranan 1→admin_dashboard, 2→pengarah_dashboard, else→dashboard):
- `users.peranan`: 1→admin, 2→pengarah, 0→pegawai
- `users_peguam_panel_2/_3.*`: → peguam (all external lawyers)
- Live result: admin=1, pengarah=26, pegawai=237, peguam=702 (966 total).

Auth: one `web` guard, single login. Gate areas by `role` (middleware/policies). Staff → rekod-kes + panel admin; lawyer → peguam area only.

## Relationships / FKs to add (legacy has only 1)

- `forms` 1—N `laporan_kes` (laporan_kes.id_kes = forms.id — note: legacy id_kes is varchar, forms.id int → cast/clean at ETL)
- `forms` 1—N `sejarah_pegawai` (FK already exists), `sejarah_peguam_panel`, `sejarah_sidang` (add FK on int id_kes)
- `peguam_panel` (add `id` PK) — lawyer logins link via `id_peguam_panel`
- Reference tables: no FKs (lookup by string value in legacy) — leave as-is, optionally add later.

## Migration order

1. `0001_..._create_users_table` (default, **edited** to unified shape) + sessions/cache/jobs as shipped.
2. `import_legacy_domain_tables` — `DB::unprepared()` the 20-table baseline (`database/schema/legacy-domain.sql`), names preserved.
3. `add_keys_and_foreign_keys` — add `peguam_panel.id` PK; add FKs for sejarah_* (int id_kes); indexes on hot lookups (no_fail, nokp, cawangan).

## ETL (Phase 1 last step)

Artisan command `legacy:import` reads live `sistemspk`, writes `iguaman_2in1`:
- Domain tables → straight `INSERT … SELECT` (same column names).
- 3 user tables → unified `users` with bcrypt(password) + role/user_type mapping.
- Clean `laporan_kes.id_kes` (varchar→int), dedupe `forms.is_duplicate=1` rows on review.

## Deferred (not Phase 1)

- Decompose `forms` 94-col monolith into Case + detail tables.
- English/convention column renames.
- Drop `items` if confirmed dead.
