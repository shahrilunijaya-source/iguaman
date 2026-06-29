# iGuaman 2in1 — Merge Plan

Combine two legacy raw-PHP systems into one full Laravel 13 app.

## Sources

| System | Files | LOC | Role |
|--------|-------|-----|------|
| `sistem-peguam-panel` | 139 | ~64k | Lawyer panel mgmt — case assignment (agihan), lawyer registration, OYD, withdrawal |
| `sistem-rekod-kes` | 188 | ~92k | Case records — applications (permohonan), mediation (pengantaraan), court cases (kes mahkamah), statistics |

Both = procedural PHP (sessions, MySQLi, FPDF), **no framework**. Domain: Malaysian legal aid (JBG/BHEUU).

## Verdict: YES — feasible. Migration/rewrite, not copy-paste.

**Decisive fact: both already run on the SAME database `sistemspk`** and share core tables. The two apps are already two UIs over one schema. "2in1" = unify those UIs + auth into one Laravel app over the existing (cleaned) schema.

## Unified data model (DB `sistemspk`, ~25 tables)

### Shared core (both systems read/write)
- `forms` — MAIN case/application table (78 cols) → decompose into Case + detail tables
- `peguam_panel`, `butiran_peguam_panel`, `butiran_peguam_panel_2` — lawyer master + profiles
- `laporan_kes` — court case reports (child of `forms`)
- `ref_kes` — case types (JENAYAH/SIVIL)
- `mahkamah_sivil`, `mahkamah_syariah` — court reference
- `pegawai_jbg` — officers
- `ref_negeri`, `ref_lokasi_berguam` — reference data
- `uploaded_files` — attachments
- `sejarah_peguam_panel` — lawyer assignment history

### Peguam-panel specific
- `butiran_oyd` — beneficiary (Orang Yang Dibantu) details
- `sejarah_ppuu` — beneficiary service history
- `audit_trail` — system audit log
- `ref_cawangan` — branches
- `mohon_klinik` — legal clinic requests

### Rekod-kes specific
- `sejarah_pegawai`, `sejarah_sidang` — staff + hearing history
- `ref_cuti` — leave records
- `items` — generic list

### Auth tables (THE merge problem) — 3 separate user tables today
- `users` — internal staff (rekod-kes): peranan 0=admin, 1=lawyer, 2=director
- `users_peguam_panel_2` / `_3` — external lawyer logins (peguam-panel)
→ **Consolidate into one `users` table + role column + `user_type` (staff | lawyer)**, link lawyers to `peguam_panel` profile. One Laravel auth, role-gated areas.

## Laravel structure (one app, two domains)

```
Auth (unified)
├── Staff area  (admin / pengarah / pegawai)
│   ├── Permohonan (5-stage intake wizard)
│   ├── Pengantaraan (mediation)
│   ├── Kes Mahkamah (civil + syariah)
│   ├── Statistik / Laporan (exports)
│   └── Pengurusan Peguam Panel (approve lawyers)
└── Peguam area (external lawyers)
    ├── Agihan Kes (baru / semasa / semula — assignment)
    ├── Profil & beban tugas
    ├── Daftar / Tarik diri (register / withdraw)
    └── Kes saya (assigned cases)
```

Models (Eloquent): User, Role, Lawyer (PeguamPanel), Applicant (Oyd), Case (Form), CourtReport (LaporanKes), Assignment, Hearing (Sidang), Mediation, Court (Mahkamah), Branch, plus reference models. Real FKs added (legacy has none).

## Tech: Laravel 13 + Blade (NO Filament)

Override the agents' Filament/Nova suggestion — Shahril rule: plain Laravel auth + Blade only (Filament = every Hostinger deploy headache). Scaffold already built in this `2in1/` folder matches that.

- PDF: `barryvdh/laravel-dompdf` (replaces FPDF 1.82)
- Excel: `maatwebsite/excel` (wraps phpspreadsheet — already a dep)
- Mail: Laravel Mail (replaces raw PHPMailer)
- Uploads: Storage facade + validation

## Critical risks / must-fix on migration

| Risk | Severity | Fix |
|------|----------|-----|
| Plaintext passwords (both systems) | CRITICAL | bcrypt on migrate; force reset all users at launch |
| Hardcoded secrets (`config.php` email pw, DB creds in source) | CRITICAL | move to `.env`, rotate the exposed email password |
| 3 user tables, plaintext, no link | HIGH | unify to one `users` + roles |
| No FK constraints in schema | HIGH | add FKs in migrations; validate ambiguous links first |
| `forms` 78 cols monolith | MEDIUM | decompose into Case + detail tables |
| 100–200KB single PHP files (daftar.php 202KB) | MEDIUM | split into controllers/services + Blade |
| FPDF legacy printing (~35 cetakan files) | MEDIUM | port to dompdf Blade views |

## Phased plan

1. **Schema-first** — dump live `sistemspk` (peguam-panel has no `.sql`; rekod-kes does). Generate Laravel migrations + Eloquent models + real FKs from the dump. Unify the 3 user tables.
2. **Auth + RBAC** — one login, roles (admin/pengarah/pegawai/peguam), bcrypt, middleware-gated areas. (Login shell already scaffolded.)
3. **Rekod-kes domain** — permohonan wizard → pengantaraan → kes mahkamah → statistik/exports.
4. **Peguam-panel domain** — agihan workflows, lawyer profile, daftar/tarik diri, beban tugas.
5. **Printing/exports** — dompdf + laravel-excel port.
6. **Data migration** — ETL legacy rows into cleaned schema; parallel-run + verify; password reset campaign.

## Effort

Manual 2–3 dev team: ~8–12 weeks (per analysis). Schema-first generation + Claude-assisted porting compresses the model/CRUD layers significantly; the long pole is the 5-stage permohonan workflow, statistik, and PDF/Excel parity.

## Open questions for Shahril

1. Migrate existing `sistemspk` production data, or fresh start?
2. Keep external lawyers + internal staff in one login, or two separate portals?
3. Is one branch (cawangan) or multi-branch in scope?
4. Bahasa Melayu UI only, or bilingual?
