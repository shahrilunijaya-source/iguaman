# 09 — 2in1 INTENDED SCOPE (Planning-Doc Baseline)

> **Purpose.** This map is the "intended truth" baseline for the consolidation gap analysis. It is built ONLY from the 2in1 **planning docs** (`context/*.md`), `CLAUDE.md`, and the two System-Overview HTML files — NOT from reading the actual Laravel code. The gap analysis compares the real code against this. Where the docs claim something is "done", that claim is recorded here verbatim as a claim to be VERIFIED, not as fact.
>
> **Sources read (all under `…/iGuaman/2in1/` unless noted):**
> - `context/project.md` · `context/2in1-merge-plan.md` · `context/domain.md` · `context/schema-design.md` · `context/security.md` · `context/parity-matrix.md` · `context/parity-backlog.md` · `context/port-plan-iguaman-janjitemu.md`
> - `CLAUDE.md` · `docs/system-overview.html` (intended 2in1 target, generated 2026-06-30)
> - `../iGuaman/iGuaman-System-Overview.html` (LEGACY .NET source overview — the Janji Temu/KN origin system being ported)
>
> **Audit posture:** READ-ONLY. Only this map file was written.

---

## 0. One-line objective

Fuse **three** origin systems into ONE Laravel 13 + Blade + MySQL monolith with one `users` table, one `spatie/laravel-permission` RBAC layer, three login-landing portals (staff / lawyer / awam):
1. `sistem-rekod-kes` (case records / mediation / court / statistics) — legacy raw PHP
2. `sistem-peguam-panel` (panel-lawyer registration + 3-tier case assignment) — legacy raw PHP
3. `iGuaman Janji Temu / Khidmat Nasihat` (advisory + appointment + public portal + chatbot) — ported from ASP.NET Core 8 / Nuxt 2 / PostgreSQL

Both legacy PHP systems already shared ONE MariaDB database `sistemspk` (~23–25 tables) — two UIs over one schema. The KN system is greenfield/adjacent (new tables, no schema reuse).

---

## 1. Intended stack (LOCKED)

| Layer | Choice |
|-------|--------|
| Language | PHP 8.3 |
| Framework | Laravel 13 |
| DB | MySQL 8.4 — local `iguaman_2in1` (Laragon, user `root`); legacy source `sistemspk` (MariaDB) |
| Views | Blade + vanilla JS |
| Assets | Vite 8 + Tailwind v4 |
| Auth | **Plain** `SystemAuthController` + `Auth::attempt` — **NEVER Filament / Breeze / Jetstream** (hard rule, Hostinger deploy headaches) |
| RBAC | `spatie/laravel-permission` (per overview HTML) |
| PDF | `barryvdh/laravel-dompdf` (replaces legacy FPDF 1.82) |
| Excel | `maatwebsite/excel` (flagged new dependency) |
| Mail | Laravel Mail (replaces raw PHPMailer); dev driver = `log` |
| Chatbot | Python microservice kept as-is + Laravel proxy/widget (NOT rebuilt — per memory `integrate-cbjbg-chatbot`) |
| Deploy | Hostinger Laravel-in-public_html; GitHub `shahrilunijaya-source/sys-iguaman-2in1`, branch `main`; committed `public/build` (no node on host); migrate via SSH port 65002 |

---

## 2. Intended data model (~48 tables target)

Strategy (`schema-design.md`): **brownfield — keep legacy table & column names verbatim** for all 20 domain tables (1:1 ETL, no remapping). ONLY the auth layer is rebuilt Laravel-native. FK strategy: bigint (new) tables get real FKs; legacy int tables (`forms.id`, `ref_negeri`) use plain indexes only.

### 2a. Imported legacy domain tables (~20, names preserved)
`forms` (94 cols, case spine) · `laporan_kes` · `butiran_oyd` · `peguam_panel` (no PK → **add `id`**) · `butiran_peguam_panel` · `butiran_peguam_panel_2` (+ planned `_3`…`_6`) · `sejarah_pegawai` (has legacy FK) · `sejarah_peguam_panel` · `sejarah_sidang` · `pegawai_jbg` · `mahkamah_sivil` · `mahkamah_syariah` · `ref_kes` (litigation taxonomy) · `ref_negeri` · `ref_lokasi_berguam` · `ref_cuti` (latin1) · `uploaded_files` · `audit_trail` · `posters` · `items` (likely dead → defer drop).

### 2b. New tables added by epics/batches (~15)
**Peguam-panel epics:** `sejarah_ppuu` (PPUU assignment history spine) · `butiran_peguam_panel_6` (Bidang Pengkhususan).
**KN/Janji-Temu batches 7–12:** `khidmat_nasihat` · `temu_janji` · `slot_temu_janji` · `sesi_janji_temu` · `penutupan_operasi`(=`penutupan_hari_operasi`) · `bilik` · `cawangan` (real master, +type JBG/JKM/Mahkamah/Penjara) · `jkm` · `penjara` · `jenis_kes` · `ref_kategori_kn` (L1) · `ref_kategori_kes_kn` (L2) · `ref_subkategori_kn` (L3) · `maklum_balas` · `ref_jawatan`.

### 2c. Unified auth `users` (replaces 3 legacy tables)
Legacy 3 tables collapse: `users` (264 staff, peranan 0/1/2) + `users_peguam_panel_2` (586) + `users_peguam_panel_3` (116). New columns: `user_type` (staff/lawyer/awam), `role`, `cawangan`, `nokp`, `id_peguam_panel`, `is_active`, `must_change_password`, `last_login_at`, bcrypt `password`. Live ETL target counts: admin=1, pengarah=26, pegawai=237, peguam=702 (966 total).

### 2d. KN category tree (LOCKED DECISION — `ref_kes` is NOT the KN tree)
3-level taxonomy used ONLY for advisory: `ref_kategori_kn` (L1) → `ref_kategori_kes_kn` (L2) → `ref_subkategori_kn` (L3). L1 values: **SIVIL · SYARIAH · PENDAMPING JENAYAH · PENDAMPING GUAMAN**. Deliberately separate from litigation `ref_kes`. Seed L1 only (per memory `ref-kes-not-kn-tree`).

---

## 3. Intended roles & permissions (8 roles, 3 user_types)

**user_types:** `staff` · `lawyer` · `peguam` portal, `awam` (citizen, IC-login).
**Roles (8):** `admin` (super-admin, `Gate::before` bypass, only role with `urus.peranan`) · `ketua_pengarah` (KP, top of every 3-tier chain) · `pengarah` (Director — approve/reject cases, sokong, close) · `koordinator` (cross-branch ops, can distribute) · `pegawai` (officer, KN processing) · `ppuu` (case distributor — `ppuuPilih`) · `pembantu_tadbir` (clerk, excluded from KN processing/approvals) · `peguam` (external lawyer — own portal only).

Branch isolation: most staff see only own `cawangan`; koordinator + ketua_pengarah see all (`cawangan.view-all`). RBAC matrix editable in-app (Peranan & Akses). `RolePermissionSeeder` is the source of grants (per overview HTML).

---

## 4. Documented status/state machines (intended)

- **`forms.status_agihan`** — numeric assignment state machine 0–17 (`StatusAgihan`): 0 BARU_PENGARAH · 8 DIAGIH_PPUU · 9 DITOLAK_PENGARAH · 10 SOKONGAN_PENGARAH · 13 KELULUSAN_KP · 1 DITAWARKAN · 2 DITERIMA · 4 PPUU_AGIH_SEMULA · 15 KELULUSAN_KP_SEMULA · 7 LEBIH_MASA · 5 Diserah Semula · 6 TARIK_DIRI_LULUS · 11 (semula sokongan) · 12 DALAM_PROSES_TD · 16 SEMAKAN_PENGARAH(TD) · 17 SEMAKAN_KP(TD) · 14 TOLAK_KE_CAWANGAN. Buckets: Baru{0,8,10,13,9,15,14} · Semasa{1,2,7} · Semula{4,15}.
- **`status_kn`** — DRAF → BAHARU → DALAM_PROSES → SELESAI (+ BATAL); SELESAI → Buka Kes spawns a `forms` row.
- **`temu_janji.status`** — MENUNGGU → DISAHKAN → HADIR → SELESAI (+ BATAL / TIDAK_HADIR).
- **`butiran_peguam_panel_6.checkbox_value_status`** — 9-state add/drop: 4 ADD_MOHON / 3 DROP_MOHON → 9/7 disokong → 2 AKTIF or row deleted.
- **`butiran_peguam_panel_2.permohonan_status`** — panel application 0 Baharu → vetted → endorsed → 1 Lulus / 2 Tidak Lulus / 3 Tarik Diri.
- **Withdrawal (Tarik Diri Mewakili OYD)** — status 12 → 16 → 17 → 6.

---

## 5. Intended workflows (7 core)

1. Case lifecycle (`forms.status`): Permohonan → Diterima → in-progress (pengantaraan/mahkamah) → Fail Tutup (or Ditolak).
2. 3-tier lawyer assignment (Agihan): PPUU → Pengarah → Ketua Pengarah, with re-pick/Lebih-Masa loops + death-redistribution.
3. Khidmat Nasihat advisory + appointment (wizard → screening → officer processing → SELESAI → Buka Kes).
4. Citizen self-service journey (awam): Daftar/Login by IC → Saringan → Wizard → Tempah slot → Hantar → dashboard (upload/reschedule/cancel) → Maklum Balas.
5. Lawyer-side flows: accept/reject offer, report, Tarik Diri, Kemaskini Bidang (add/drop practice area).
6. Panel-lawyer application (public apply → 3-tier vet → peguam_panel + login).
7. Statistik/Laporan: SLA matrices, mediation stats, file-number error log, KPI, 8 KN reports; CSV/Excel/PDF.

---

## 6. Documented security must-fixes & posture

**Claimed DONE in-app (`security.md`) — VERIFY against code:** passwords bcrypt; forced reset (`must_change_password` + `ForcePasswordChange` middleware → `/password/change`); active-only login (`Auth::attempt(['is_active'=>true…])`); RBAC `EnsureRole` middleware (endorse=pengarah, decide=admin/koordinator); `SecurityHeaders` middleware (X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy); no hardcoded secrets in app (grep-clean, `.env`); CSRF default. Throttling (per overview): login 10/min, public forms/uploads 6–20/min, chatbot 20/min, feedback 6/min; honeypot + captcha on public forms. Private-disk attachments streamed via auth + ownership; citizens download only own KN docs.

**Legacy vulns being remediated by the merge:** plaintext passwords (both systems, CRITICAL) · hardcoded secrets in `sistem-peguam-panel/config.php` (email pw `aplikasi.jbg@bheuu.gov.my` + DB creds, CRITICAL) · 3 unlinked plaintext user tables (HIGH) · no FK constraints (HIGH) · SQLi raw-interpolation throughout (claimed resolved via Eloquent) · hardcoded remote PDO `10.19.202.135` (claimed removed). KN source had auth configured but `[Authorize]` UNENFORCED + client-side-only gating → must enforce server-side.

**Pre-prod ops checklist (NOT done — by design):** rotate leaked email password (treat as compromised); prod `.env` (`APP_ENV=production`, `APP_DEBUG=false`, fresh `APP_KEY`, real `DB_*`/`MAIL_*`, `SESSION_SECURE_COOKIE=true`); confirm all migrated users flagged; HTTPS + HSTS at edge; consider login rate-limit + real mail driver; review file-upload handling before enabling `uploaded_files` writes.

---

## 7. LOCKED decisions (do not re-litigate)

| # | Decision |
|---|----------|
| D1 | **One login** for staff+lawyer (email) + awam (separate IC login). One `users` table, one RBAC layer. |
| D2 | **Public portal IN SCOPE = option B (FULL).** `awam` user_type, public register/login, captcha, self-service booking. Batch 12 not optional. New attack surface → server-side auth + rate-limit + captcha on all public routes. |
| D3 | **`ref_kes` is NOT the KN tree.** Separate normalized `ref_kategori_kn`/`ref_subkategori_kn`; seed L1 only; optionally seed from ref_kes where values align. |
| D4 | **Cawangan → REAL master table** (+ branch type); migrate free-string usage; reconcile `CawanganScope`. |
| D5 | **Maklum balas public** (no login, one per advisory). |
| D6 | **NEVER Filament/Breeze/Jetstream** — plain custom auth + Blade only. |
| D7 | KN port = 100% rewrite (no C#/Vue reuse); integrate into monolith, not side-by-side. |
| D8 | New PKs = auto-increment `id` (not source uuid); loose source string statuses → PHP enums/consts. |
| D9 | Chatbot kept as Python microservice + Laravel proxy (not rebuilt). |

---

## 8. Parity-build progress (CLAIMED by docs — to verify)

`parity-backlog.md` declares: 🎉 **FULL LEGACY PARITY REACHED** — both legacy PHP domains ported, P0 epics A–G done; P1–P3 = enhancements beyond parity. Parity matrix baseline (pre-build) was 246 features: ✅44 full / 🟡86 partial / ❌116 none; severity 🔴45 🟠73 🟡57 ⚪71.

**Services claimed in `app/Support/`:** AgihanService · StatusAgihan · TarikDiriService · PeguamLifecycleService · PengkhususanService · NoFailGenerator · LebihMasaService · SlaMatrix · WideExport · KesilapanMatrix · SlaListExport · PengantaraanMatrix · KhidmatNasihatService · KhidmatProsesService · SlotAvailabilityService · LaporanKnService. Plus `KhidmatNasihatPolicy` + `Gate::before` super-admin bypass.

**Open questions still unresolved in docs:** migrate prod `sistemspk` data vs fresh start (merge-plan Q1) · single vs multi-branch scope (Q3) · BM-only vs bilingual UI (Q4) · chatbot host A/B/C + deploy (memory) · `project.md` goals still checkbox-unchecked (define what "2in1" combines, replace dashboard stub, rebrand theme.css) — note these are stale vs the much-further-along parity/overview docs.

---

## 9. Documented divergences / known deviations (the team already flagged)

- SLA "senarai" period filter keys off SLA end-date (not legacy `tarikh_perakuan`) — intentional, reconciles with matrix.
- Legacy file-1 header/value misalignment + one-off column NOT reproduced (4 court lists share one clean layout).
- Pengantaraan wide-CSV: 5 legacy cols absent from current spine (alasan_tidak_setuju_pengantara, alasan_gagal_pengantara, alasan_tangguh_sidang, alasan_tidak_rujuk_pengantaraan, tarikh_perjanjian) → degrade to NO_DATA until pengantaraan workflow ported.
- Pencapaian F2 numerator = setuju_pengantara='Ya' (legacy cetakan's extra status_sidang='Selesai' predicate NOT ported).
- Legacy bugs intentionally fixed (do NOT copy): `*7.0` peratus typo, Putrajaya/missing-branch normalisation, bulanan kategori filter column, JUMLAH summation; KN port bugs: KhidmatNasihat dropping FK fields, UTC+8 double-offset, CreateTemuJanji dropping IdPegawaiKN, no slot logic, unenforced auth.
- Legacy zero-dates `0000-00-00` imported as-is (relaxed sql_mode during ETL only; app runs strict).

---

## 10. How to use this map in the gap analysis

For each `features[]` entry below: `status` = what the **docs claim** (done / partial / missing / planned), NOT verified code. The gap analysis must:
1. Confirm "done/✅" claims actually exist in code (controllers, routes, migrations, services, tests).
2. Flag any P0 epic A–G claimed done but absent/thin in code (highest risk — docs assert full parity).
3. Verify every security must-fix in §6 is truly implemented (middleware registered, `.env` clean, ownership checks present).
4. Check the KN/Janji-Temu batches 7–12 (D2 public portal, slot engine, feedback, 8 reports) against the parity discipline.
5. Note stale planning artifacts (project.md/domain.md describe a bare scaffold; overview + backlog describe a near-complete app).
