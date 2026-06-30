# Legacy System Map — `sistem-rekod-kes` (Case Records / Litigation)

> **Source:** `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/sistem-rekod-kes`
> Procedural raw-PHP (~188 PHP files, no framework). MySQL via `mysqli`. DB name `sistemspk` (`db_connection.php`).
> Owner: **JBG / BHEUU** (Jabatan Bantuan Guaman — Malaysian Legal Aid Dept). UI language: Malay.
> This is the **case-records / litigation** half of a 2-system split (the other half = `sistem-peguam-panel`, the panel-lawyer billing system).
> Audit date: 2026-06-30. **READ-ONLY audit.** No source files modified.

---

## 1. Purpose & Domain

End-to-end lifecycle for legal-aid case records inside JBG state branches (cawangan):

1. **Permohonan Bantuan Guaman** — citizen application intake + director's decision (approve/reject).
2. **Penjanaan Nombor Fail** — file-number generation on approval (`JBG.<state>(<jenis_kes>)<seq>/<MMYY>`).
3. **Pengantaraan** (mediation) — assign mediator, schedule sidang (hearings), reschedule history.
4. **Pengendalian Kes** — assign case to JBG officer **or** Peguam Panel (external lawyer).
5. **Kes Mahkamah** (court cases, civil/syariah) + **Laporan Kes** (case progress reports per mention/sebutan).
6. **Status Fail / Penyelesaian** — court orders, costs, completion.
7. **Tutup Fail** — file closure.
8. **Statistik / Laporan / KPI** — dashboards, CSV exports, print views, SLA tracking.

The whole lifecycle revolves around **one giant table: `forms`**.

---

## 2. Authentication, Roles & Sessions

### Login — `log_masuk.php`
- POST email + password + **numeric 4-digit captcha** (`$_SESSION['captcha_code'] = rand(1000,9999)`; AJAX refresh via `refresh_captcha.php`).
- Query: `SELECT * FROM users WHERE emel = ? AND status_aktif = '1'` (prepared — the only safe part).
- **Password check is PLAINTEXT**: `if ($user['kata_laluan'] === $kata_laluan)` (line 79). `password_verify` is present but **commented out**.
- On success sets `$_SESSION['user']`, `$_SESSION['peranan']`, `$_SESSION['cawangan']` → redirect `dashboard.php` (single dashboard for all roles since the 6/2026 merge).
- `log_masuk_backdoor.php` — a **backdoor login** variant exists (security liability).
- `logout.php` — `session_destroy()` → `log_masuk.php`.
- `lupa_kata_laluan.php` — resets password to a **plaintext temp pass** then **passes it in the URL**: `header("Location: phpmailer/emelpwd.php?aa=$id&ww=$tempPass")`. Email sent via PHPMailer (`phpmailer/emelpwd.php`). Old variant: `lupapwd.php`.

### Roles — `users.peranan` (int)
| `peranan` | Role | Capabilities |
|-----------|------|--------------|
| `0` | **Pegawai** (officer) | Default. Data scoped to own `cawangan`. No Selenggara/admin menus. |
| `1` | **Admin** | Sees ALL cawangan. Selenggara menus (users, pegawai JBG, jenis kes, mahkamah, e-poster). Notification badge for rejected PP cases. Kesilapan-nombor-fail reports. |
| `2` | **Pengarah / Ketua Cawangan** | Sees own cawangan; **if `cawangan == 'JBG WP PUTRAJAYA'` → HQ access to ALL cawangan** (`$is_hq` rule). Can close files (tutup fail). |

- **Data scoping rule** (dashboard.php:146): `$is_hq = ($peranan == '1') || ($peranan == '2' && $cawangan_user == 'JBG WP PUTRAJAYA')`. HQ → no cawangan filter; otherwise `AND cawangan = '<user cawangan>'`.
- **Pre-merge legacy** (still on disk): role-prefixed file duplicates — `admin_*.php`, `pengarah_*.php` vs plain. Navbar variants `navbar.php` / `admin_navbar.php` / `pengarah_navbar.php`. The 6/2026 merge unified `navbar.php` to switch on `$peranan` via a `$prefix` var; many `admin_`/`pengarah_` files remain as dead duplicates.

### Session handling
- `navbar.php` guards every page: `if (!isset($_SESSION['user'])) header("Location: log_masuk.php")`.
- 30-min idle timeout is **client-side only** (JS `setTimeout` + SweetAlert) — server-side timeout block is **commented out** (navbar.php:18-24). No real server enforcement.

---

## 3. Core Table: `forms`

The master case record. **Two schema snapshots disagree (drift):**
- `sistem-rekod-kes.sql` dump (Dec 2024): **78 columns**.
- Canonical current schema (`sistem-rekod-kes-laravel` migration, 2026-06-29): **98 columns** (~20 added since the dump).

### Column groups (forms)
**Identity / intake (Peringkat 1):** `id`, `cawangan`, `tarikh_khidmat_nasihat`, `tarikh_permohonan`, `nama`, `nokp`, `umur`, `jantina`, `agama`, `agamaLain`*, `oku`, `bangsa`, `etnik`, `kaedah_penerimaan`, `created_at`, `didaftarkan_oleh`*.

**Decision / keputusan (Peringkat 2):** `keputusan` (Diterima/Ditolak), `kelulusan` (Perlu/Tidak — needs Minister), `keputusan_menteri` (Diluluskan/Ditolak), `taraf` (Pemohon/Orang Yang Dibantu/Tamat), `diterima`, `reason`, `alasan`, `sumbangan` (Ada/Tiada), `jenis_sumbangan`*, `nilai_sumbangan`, `kaedah_penerimaan`, `tarikh_perakuan`, `tarikh_pemakluman`, `tarikh_pemakluman_ditolak`, `kaedah_pemakluman` (JSON array), `tarikh_pemberitahuan_perakuan`, `no_fail`, `no_sistem`, `nama_pegawai`, `nama_pegawai_pengesahan`*, `tarikh_pengesahan`*, `pembatalan_borang_1`* (Ya/Tidak), `alasan_pembatalan`*, `jenis_oyd`, `kategori_kes` (Sivil/Syariah/Jenayah), `kategori_kes2`, `jenis_kategori`, `jenis_jenayah`, `jenis_kes` (ref_kes id_kes), `jenis_kes_lain`*, `nyatakanLain`*, `kategori_kes_borang`*, `nama_pegawai_penyiasat`.

**Pengantaraan / mediation (Peringkat 3):** `status_pengantaraan` (Ya/Tidak/Tidak Dirujuk), `tarikh_penugasan`, `pilihan`, `kaedah_sidang` (Fizikal/Atas Talian), `lokasi_pihak_pertama`, `lokasi_pihak_kedua`, `lokasi_pegawai_pengantara`, `tarikh_persetujuan`, `tarikh_persetujuan_pengantaraan`, `tarikh_sidang`, `status_sidang` (Selesai/Tangguh/Gagal), `cara_selesai`, `setuju_pengantara`*, `alasan_tidak_rujuk_pengantaraan`*, `alasan_gagal_pengantara`*, `alasan_tidak_setuju_pengantara`*, `pengantaraan_kategori_kes`*, `kpi`.

**Pengendalian / agihan (Peringkat 4):** `agih_kepada` (JBG / Peguam Panel), `status_agihan` (see §5), `nama_pegawai_yang_dapat_kes`, `nama_pegawai_penyiasat`, `keputusan_kendali_kes`, `tarikh_penugasan_peguam_panel`, `tarikh_mohon_khidmat_pp`*, `justifikasi_rujuk_pp`* (JSON), `justifikasi_lain_rujuk_pp`*, `sebab_Tidak_Diluluskan`, `sebab_menolak`, `tarikh_pengarahKemaskini`*, `tarikh_KPKemaskini`*, `tarikh_penugasan_pegawai_pengendali_kes`*.

**Kes Mahkamah (Peringkat 5):** `nama_pihak`, `nama_responden`, `nama_mahkamah`, `tarikh_pemfailan_kes`, `no_mahkamah`, `keputusan_kendali_kes`, `tarikh_pemfailan`. (Detailed case reports → `laporan_kes` table.)

**Status fail / penyelesaian (Peringkat 6):** `tarikh_perintah`, `tarikh_perintah_bersih`, `tarikh_serahan_perintah`, `tarikh_selesai`, `sebab_selesai`, `alasan_selesai`, `catatan_penyelesaian`*, `tarikh_pemberitahuan_oyd`, `tarikh_pemberitahuan_mahkamah`.

**Kos & Tutup fail (Peringkat 7):** `kos` (Ya/Tidak), `kos_oyd`, `kos_pihak_lawan`, `tarikh_kos_selesai`, `tarikh_tutup_fail`, `sebab_tutup_fail`*, `alasan_pemindahan_fail`*, `alasan_kesilapan_no_fail`*.

**Audit:** `modifiedBy`*, `modifiedDate`*, `created_at`.

`*` = added after the Dec 2024 SQL dump (present in INSERT/UPDATE code + Laravel migration, absent from the dump).

### `forms.status` — lifecycle state machine (string)
`Baru` → `Dalam Proses Permohonan` → `Dalam Proses JBG` / `Dalam Proses Peguam Panel` → `Dalam Proses Tutup Fail` → `Fail Tutup`.
Branch terminals: `Ditolak`, `Dibatalkan` (`taraf='Tamat'`), `Diserah Semula`.
(Some dashboard filters also reference legacy codes `'1'`,`'2'`,`'5'`,`'TOLAK'`,`'TARIK DIRI'`,`'LEBIH MASA'`.)

---

## 4. The Permohonan Intake → 5-Stage Workflow (screens + processors)

UI is a **tabbed single-page case file**: `tabs.php` (pegawai), `tabs_admin.php`, `tabs_pengarah.php` (200KB+ each, role variants). Also `tabs_mohon*.php` for new applications. Tabs labelled **Peringkat 1–7** (the "5-stage" intake is Peringkat 1–2; 3–7 are post-approval handling).

| Peringkat | Screen(s) | Processor | What it does |
|-----------|-----------|-----------|--------------|
| **1 — Permohonan** | `peringkat1.php`, `permohonan_bantuan_guaman.php`, `tabs_mohon*.php` | `process_decision_peringkat1_tabs.php` | Intake form: tarikh khidmat nasihat, tarikh permohonan, nama, nokp (auto-derives umur from IC), jantina, OKU, bangsa, etnik, kaedah penerimaan. **INSERT INTO forms** (raw concat — SQLi). `status='Baru'`. cawangan from session. |
| **2 — Keputusan + Jana No. Fail** | `kemaskini_peringkat2.php`, decision tab in `tabs*.php` | **`process_decision_peringkat2.php`** (the core, 408 lines) | 3 actions: **`simpan`** (save: Diterima→`Dalam Proses Permohonan`/`Pemohon`, or Ditolak→`Ditolak`/`Tamat` requiring `tarikh_pemakluman_ditolak`+`reason`); **`jana`** (generate `no_fail` if all fields complete → `Dalam Proses JBG`/`Orang Yang Dibantu`); **`batal`** (cancel: `Dibatalkan`/`Tamat`, requires `alasan_batal`, `pembatalan_borang_1='Ya'`). Enforces **30-day rule**: `tarikh_perakuan` must be within 30 days of `tarikh_pemakluman`; pemakluman cannot be future. `keputusan_menteri` overrides `keputusan` when `kelulusan='Perlu'`. |
| **3 — Pengantaraan** | `kemaskini_peringkat3.php`, pengantaraan tab | `process_decision_peringkat3.php` | Assign mediator (`nama_pegawai`, `tarikh_penugasan`), `kaedah_sidang`, party locations, `tarikh_sidang`, `status_sidang` (Selesai/Tangguh/Gagal), `cara_selesai`, agreement dates. Conditional NULL-ing logic by status_pengantaraan/setuju_pengantara/status_sidang. |
| **4 — Pengendalian / Agihan** | `kemaskini_peringkat4.php`, agihan tab | **`process_decision_peringkat4.php`** (295 lines) | Assign case to **JBG** (→`nama_pegawai_yang_dapat_kes`, `status='Dalam Proses JBG'`) or **Peguam Panel** (→`status='Dalam Proses Peguam Panel'`, `status_agihan='0'`). Logs reassignment history to `sejarah_pegawai` (JBG officer changes) and `sejarah_peguam_panel` (PP changes), including switch JBG↔PP. `justifikasi_rujuk_pp` (JSON). |
| **5 — Kes Mahkamah + Laporan** | `kemaskini_peringkat5.php`, mahkamah tab; `laporan_kes_mahkamah.php`, `kemaskini_laporan_kes_mahkamah.php` | `process_decision_peringkat5.php`, **`process_laporan_kes_mahkamah.php`** | P5 processor saves court info + completion dates (`tarikh_perintah*`, `tarikh_selesai`, `sebab_selesai`) → `Dalam Proses Tutup Fail` when complete. Court reports → multi-row INSERT into **`laporan_kes`** (one per sebutan/mention: pihak, no_kes, tarikh_sebutan, fakta_ringkas, isu, ringkasan, status_kes). |
| **6 — Status Fail / Penyelesaian** | status fail tab | (handled by P5/P6 processors) | Order/cost dates, notifications to OYD + court. |
| **7 — Tutup Fail** | tutup fail tab | **`process_decision_peringkat6.php`** (note: file "6" produces the **Peringkat 7** screen) | 2 actions: `simpan` (stay `Dalam Proses Tutup Fail`) or **`tutup_fail`** (`status='Fail Tutup'`). Validates `tarikh_tutup_fail`, `sebab_tutup_fail`, kos completeness. `sebab_tutup_fail` options incl. *Pemindahan Fail Ke Cawangan Lain*, *Kesilapan Menjana Nombor Fail* (each needs an alasan). **Uses prepared statements** (one of the few). |

> **Naming offset bug/convention:** processor files are numbered 1–6 but the UI tabs run 1–7 (`process_decision_peringkat6.php` saves "Peringkat 7"). The mapping is consistent internally but a migration trap.

### No. Fail generation (process_decision_peringkat2.php, `jana`)
- Format: `JBG.<statecode>(<jenis_kes>)<seq>/<MMYY>` e.g. `JBG.PJY(1)17/0424`.
- State code from a **hardcoded 23-entry `$jbgMap`** (JBG JOHOR→JHR … JBG WP PUTRAJAYA→PJY).
- `seq` = `COUNT(*) FROM forms WHERE cawangan=? AND jenis_kes=? AND no_fail LIKE '%<YY>'` + 1. **Concurrency race** (no locking → duplicate file numbers possible → drives the "Kesilapan Penjanaan Nombor Fail" report that exists to catch this).
- Requires all of: jenis_kes, valid tarikh_perakuan + pemberitahuan_perakuan (not `0000-00-00`), tarikh_pemakluman, sumbangan (+jenis if Ada), kategori_kes2, nama_pegawai_penyiasat, jenis_oyd. Otherwise alert + abort.

---

## 5. `status_agihan` — Peguam Panel approval sub-workflow

Set on the **peguam-panel side** (the sibling `sistem-peguam-panel` system) — NOT set anywhere in `sistem-rekod-kes` itself; this system only **reads/displays** it. String codes (`tabs*.php`):

| `status_agihan` | Meaning (display) |
|-----------------|-------------------|
| `''`/NULL | `--TIADA PERMOHONAN DIHANTAR--` |
| `0` | `PERMOHONAN BAHARU` (PP request created) |
| `1` / `2` | Approved → `PERMOHONAN KHIDMAT PEGUAM PANEL TELAH DILULUSKAN` / `DALAM PENUGASAN PEGUAM PANEL` (`2` shows full PP firm details from `butiran_peguam_panel_2/4`) |
| `3` / `6` | `DALAM PROSES TARIK DIRI` (PP withdrawal) |
| `9` | **Tidak Diluluskan oleh Pengarah** (rejected by director) |
| `14` | **Tidak Diluluskan oleh KP** (rejected by Ketua Pengarah) |

- Display also reads `statusMohonPP` + `status_KP` from **`sejarah_ppuu`** table (PP approval workflow history — added post-dump).
- **Admin notification badge** (navbar.php + dashboard.php): counts `forms WHERE status_agihan IN ('9','14') AND (agih_kepada IS NULL OR agih_kepada != 'JBG')` = PP-rejected cases needing reassignment. Links to **`maklumat-agihan-semula.php`** — **THIS FILE DOES NOT EXIST** → dead link / broken admin workflow.

---

## 6. Court Cases (Mahkamah) & Sidang

- **Court reference data:** `mahkamah_sivil` + `mahkamah_syariah` (each: nama_mahkamah, negeri_mahkamah, lokaliti_mahkamah, +`jenis_mahkamah` post-dump). CRUD in `senarai_mahkamah.php` (admin Selenggara) — raw-concat INSERT/UPDATE (SQLi); `updateMahkamah.php`, `delete_mahkamah.php`.
- **Case progress reports:** `laporan_kes` (linked `id_kes` → `forms.id`). One row per court mention (sebutan). Screens: `laporan_kes_mahkamah.php` (JOIN laporan_kes ↔ forms), `kemaskini_laporan_kes_mahkamah.php`, `update_laporan_kes_mahkamah.php`, `delete_laporan_kes_mahkamah.php`. Civil/Syariah split reports: `pengarah_laporan_kes_mahkamah_sivil.php`, `pengarah_laporan_kes_mahkamah_syariah.php`, `sivil_syariah_HQ.php`.
- **Sidang rescheduling (mediation hearings):** `sejarah_sidang` (id_kes, tarikh_sidang, alasan_tangguh, dikemaskini_oleh). Inserted by **`process2.php`** (multi-row, prepared) when a sidang is postponed (Tangguh). History shown in Peringkat 3 tab.

---

## 7. Statistics, Reports, Exports, Prints

### Dashboard — `dashboard.php` (116KB, single merged dashboard)
Aggregates over `forms` with cawangan scoping. Cards: total permohonan, status breakdown, keputusan (Diterima/Ditolak), reason for rejection, pembatalan, sumbangan (count + SUM `nilai_sumbangan`), bangsa, kategori_kes, status_sidang, fail tutup counts, dalam-proses-tutup-fail. Charts via Chart.js/ApexCharts. (`admin_dashboard.php`, `dashboard_backup.php` are variants.)

### KPI — `kpi.php` (76KB) — SLA thresholds (DATEDIFF)
| KPI | Rule |
|-----|------|
| Perakuan Bantuan Guaman | `tarikh_pemakluman − tarikh_permohonan ≤ 40 days` |
| Pemfailan Kes (terlibat pengantaraan) | `tarikh_pemfailan_kes − tarikh_perakuan ≤ 60 days` |
| Pemfailan Kes (tiada pengantaraan) | `≤ 120 days` |
| Serahan Perintah Kes | `tarikh_serahan_perintah − tarikh_perintah_bersih ≤ 7 days` |

Drives: `senarai_perakuan_melebihi_40_hari.php`, `statistik_perakuan_bantuan_guaman.php`, `senarai/statistik_pemfailan_kes_*_pengantaraan.php`, `senarai/statistik_serahan_perintah_kes.php`, `senarai/statistik_khidmat_pengantaraan.php`.

### Laporan (reports — on-screen tables, role + cawangan scoped)
`laporan_permohonan_bantuan_guaman.php`, `laporan_pendaftaran_fail_kes.php`, `laporan_status_fail_kes.php`, `laporan_kes_mahkamah.php`, `laporan_penugasan_pengantaraan.php`, `laporan_pengantaraan_tidak_dirujuk.php`, `laporan_pencapaian_penugasan_pengantaraan.php`. Pengantaraan stats: `statistik_penugasan_pengantaraan.php`, `statistik_penugasan_bulanan_pengantaraan.php`, `statistik_bulanan_kes_pengantaraan.php`. Syariah-specific: `statistik_borang_1_syariah.php`, `statistik_kes_selesai_syariah.php`, `statistik_pendaftaran_kes_syariah.php`. Kesilapan: `statistik/senarai_kesilapan_penjanaan_nombor_fail.php`.

### Exports — **13 `export_*.php`, ALL CSV** (`fputcsv`/`text/csv`)
`export_permohonan_bantuan_guaman.php`, `export_pendaftaran_fail_kes.php`, `export_status_fail.php`, `export_laporan_kes_mahkamah.php`, `export_laporan_penugasan_pengantaraan.php`, `export_laporan_pengantaraan_tidak_dirujuk.php`, `export_senarai_kes.php`, `export_senarai_khidmat_pengantaraan.php`, `export_senarai_pemfailan_kes_terlibat_pengantaraan.php`, `export_senarai_pemfailan_kes_tiada_pengantaraan.php`, `export_senarai_perakuan_melebihi_40_hari.php`, `export_senarai_serahan_perintah_kes.php`, `export_kesilapan_nombor_fail.php`.
> **Note:** `composer.json` requires `phpoffice/phpspreadsheet ^4.0` but exports are hand-rolled CSV (Excel-friendly via `="..."` nokp wrapping). PhpSpreadsheet appears unused in the case-records exports.

### Prints — **15 `cetakan*.php`** (HTML print-to-PDF views; FPDF lib bundled in `fpdf182/`)
`cetakanMaklumatPermohonan.php` (per-case dossier), `cetakan_laporan_kes_mahkamah[_sivil/_syariah].php`, `cetakan_laporan_pencapaian_penugasan_pengantaraan.php`, `cetakan_laporan_pengantaraan_tidak_dirujuk.php`, `cetakan-statistik-bulanan-pengantaraan.php`, `cetakan_statistik_{khidmat_pengantaraan, pemfailan_kes_terlibat/tiada_pengantaraan, pendaftaran_pengantaraan, perakuan_bantuan_guaman, serahan_perintah_kes, kesilapan_penjanaan_nombor_fail}.php`, `cetakan_status_fail_kes.php`.

---

## 8. List / Browse Screens (Senarai)

| Screen | Purpose / filter |
|--------|------------------|
| `senarai_pemohon.php` | Applicants in process (excludes Peguam Panel/Diserah Semula/Fail Tutup + Ditolak). cawangan scoped. |
| `Senarai_Orang_Yang_Dibantu.php` (+`admin_`/`pengarah_`) | Approved OYD (has no_fail; excludes tutup-fail states). |
| `senarai_fail_tutup.php` (+role variants) | Closed files (`status='Fail Tutup'`). |
| `senarai_kes.php` | Selenggara jenis kes (ref_kes CRUD). |
| `senarai_mahkamah.php` | Selenggara mahkamah (sivil+syariah CRUD). |
| `senarai_pengguna.php` (86KB) | Selenggara pengguna (user CRUD) — **uses `password_hash()` bcrypt** for new/changed passwords (inconsistent with login's plaintext compare). Manages peranan, status_aktif, nokp, created_by/modified_by. |
| `senarai-pegawai-jbg.php` (62KB) | Selenggara pegawai JBG (`pegawai_jbg` CRUD). |
| `senarai_khidmat_pengantaraan.php`, `senarai_serahan_perintah_kes.php`, `senarai_pemfailan_kes_*.php`, `senarai_perakuan_melebihi_40_hari.php`, `senarai_kesilapan_penjanaan_nombor_fail.php` | KPI/stat list drill-downs. |

---

## 9. Other Modules

- **e-Poster** (`e-poster.php`, `poster_upload.php`, `poster_update.php`, `poster_delete.php`): public/internal poster board. Table `posters` (`status_poster` aktif/tidak aktif; non-admins see only aktif). The **only real file-upload feature** (`move_uploaded_file` → `uploads/`).
- **Cuti (leave) management** (mediator availability): `formTambahCuti.php`, `formUpdateCuti.php`, `formKemaskiniCuti.php`, `list_cuti.php`, `detail_elaun.php`. Tables `ref_cuti`. `cal.php`/`calc_days.php` = working-day calculators (excludes cuti/weekends — used for SLA day counts).
- **Email to panel lawyer:** `emel_ke_peguam_panel.php` (PHPMailer).
- **AJAX endpoints:** `get_jenis_kes.php` (cascade kategori→jenis_kes from `ref_kes WHERE aktif_kes=1`), `check_nokp.php`/`check_nokp_users.php` (duplicate-IC lookup), `check_kes_pegawai.php`, `fetch_sejarah_*.php` (officer/PP/sidang history), `delete_*.php`.
- **`under_maintenance.php`** — maintenance landing (self-referenced only; manual toggle).

---

## 10. Database Tables (full inventory)

From `sistem-rekod-kes.sql` (Dec 2024) + drift from 2026 Laravel migrations.

| Table | Key columns | Role |
|-------|-------------|------|
| **`forms`** | 78→98 cols (see §3) | **MASTER case record** |
| `users` | id, nama, username, **emel**, cawangan, **kata_laluan**, peranan, +`nokp`,`status_aktif`,`created_by`,`modified_by` (drift) | App users (officers/admins/directors) |
| `laporan_kes` | id, id_kes→forms.id, pihak_pihak, no_fail, no_kes, nama_pegawai, tarikh_sebutan, fakta_ringkas, isu, ringkasan, status_kes | Court case progress reports (per mention) |
| `mahkamah_sivil` / `mahkamah_syariah` | id, nama_mahkamah, negeri_mahkamah, lokaliti_mahkamah, +jenis_mahkamah | Court reference data |
| `pegawai_jbg` | id, nama, cawangan, jawatan, bahagian | JBG officer directory |
| `peguam_panel` | nama_peguam, kp_peguam, tel, emel, firma + address | Panel-lawyer directory |
| `butiran_peguam_panel`, `_2` (+`_3..._6`, drift) | firm/lawyer detail | PP firm details (joined in tabs P4) |
| `sejarah_pegawai` | id_kes, nama_pegawai_lama, tarikh_kemaskini, dikemaskini_oleh | JBG officer reassignment history |
| `sejarah_peguam_panel` | id_kes, nama_pp_lama, tarikh_penugasan, status, alasan | PP reassignment history |
| `sejarah_ppuu` (drift) | id_kes, statusMohonPP, status_KP | PP approval-workflow history |
| `sejarah_sidang` | id_kes, tarikh_sidang, alasan_tangguh, dikemaskini_oleh | Mediation hearing reschedule log |
| `ref_kes` | id, id_kes, jenis_kes (SIV/SYA/JEN/PG), kategori_kes, deskripsi, +`aktif_kes` (drift) | Case-type taxonomy (litigation) |
| `ref_negeri` | id, nama, aktif, kategori | State reference |
| `ref_lokasi_berguam` | id, nama | Mediation location reference |
| `ref_cuti` | id_cuti, nama_cuti, tarikh_mula/tamat, idnegeri | Public-holiday/leave calendar (SLA calc) |
| `ref_cawangan` (drift) | branch reference | Branch master |
| `uploaded_files` | id, nama, file_name, file_path, file_type, uploaded_at | Generic file metadata (largely poster-only in practice) |
| `posters` (drift) | poster + status_poster | e-Poster board |
| `audit_trail` (drift) | audit log | (Laravel rewrite only — not in raw PHP) |
| `users_peguam_panel_2` / `_3` | PP-side user accounts | (PP system; present in dump) |
| `items` | id, name, description | **Unused stub** (empty) |

---

## 11. Earlier Laravel Rewrites (intermediate artifacts — NOT a 4th source)

Two partial rewrites on disk. Use to confirm intended behavior + spot dropped features.

### `…/iGuaman/sistem-rekod-kes-laravel` (2026-06-29, most recent)
- Covers the **case-records side only**. Laravel 11/12 skeleton.
- **Auth hardened:** `AuthController` uses `Auth::attempt` (bcrypt) + `throttle:10,1` rate-limit; role middleware `role:0,1` / `role:2`.
- Models: Form, LaporanKes, MahkamahSivil/Syariah, PegawaiJbg, PeguamPanel, Sejarah{Pegawai,PeguamPanel,Sidang}, RefKes/Negeri/Cuti/LokasiBerguam, UploadedFile, Poster, ButiranPeguamPanel. Global Scopes/ dir (likely cawangan scope).
- Controllers: Auth, Dashboard, Pemohon, **Peringkat**, **Keputusan**, Permohonan, Laporan.
- **Full 29-table migration set** (2026_06_29_154458_*) = the canonical 98-col `forms` schema + butiran_peguam_panel_2-6 + sejarah_ppuu + ref_cawangan + audit_trail. **This is the best schema reference** (resolves the dump drift).
- **DROPPED / NOT PORTED (gap flags):**
  - **`KeputusanController` (25 lines) and `PeringkatController` (23 lines) are stubs** — the 5-stage decision engine, no_fail generation, 30-day rule, batal/jana actions, status_agihan handling are **NOT implemented**.
  - KPI module, statistik screens stubbed (`Route::get("/{$slug}", fn()=>view('stub'))` for kpi/pengantaraan/statistik/selenggara).
  - Exports: only permohonan/pendaftaran/status-fail/kes-mahkamah have export routes; the other ~9 CSV exports not ported.
  - e-Poster, Cuti, PP-email, AJAX duplicate-check, court CRUD, sidang reschedule — not present.

### `…/iGuaman/spk-laravel`
- More **ambitious merge of BOTH systems**: models include `SejarahPpuu`, `ButiranPeguamPanel2-6`, `AuditTrail`, `RefCawangan`, `ButiranOyd`, `UsersPeguamPanel3`, `LegacyUser`.
- Controllers: Auth/, Dashboard, **Document** (real doc-mgmt — beyond legacy poster-only), PeguamPanel, Permohonan, **PermohonanReview**, Profile, Selenggara.
- **No migrations dir populated** (schema relies on legacy import). README is Laravel default (no scope doc).
- **Feature possibly intended but at risk of being dropped in 2in1:** a proper **DocumentController** (general document upload/management) — legacy only had poster uploads + a barely-used `uploaded_files`. The 2in1 rebuild already added "citizen document upload" (recent commits) — confirm parity with spk-laravel's DocumentController.

---

## 12. Weaknesses / Migration Risks (concrete)

**CRITICAL — Security**
- **Plaintext password login** (`log_masuk.php:79` `===`). Seed users all `pass123`. `password_verify` commented out. But `senarai_pengguna.php` writes **bcrypt** hashes → **mixed/broken auth state**: any user created/edited via admin can no longer log in (hash ≠ plaintext `===`).
- **SQL injection pervasive**: raw `$_POST`/`$_GET` string-concatenated into queries across nearly all intake/list/CRUD files (`peringkat1.php`, `senarai_mahkamah.php`, `senarai_pengguna.php` filters, dashboard `$where_clause`, etc.). Only a minority use prepared statements (process_decision_peringkat6, process2, process_laporan_kes_mahkamah, check_nokp).
- **Password reset leaks temp password in URL** (`lupa_kata_laluan.php` → `emelpwd.php?ww=$tempPass`); temp pass stored plaintext.
- **`log_masuk_backdoor.php`** present — backdoor auth path. Must be removed.
- **Captcha is trivial** (4-digit numeric in session, predictable).
- **No server-side session timeout** (commented out); idle logout is JS-only (bypassable).
- `error_reporting(E_ALL); ini_set('display_errors',1)` left on in processors → info leakage.

**HIGH — Data integrity / logic**
- **No. Fail generation race**: COUNT-based sequence with no locking → duplicate file numbers (the entire "Kesilapan Penjanaan Nombor Fail" report exists to mop this up). Migrate to a proper sequence/unique constraint.
- **Status is free-text strings** (`forms.status`, `status_sidang`, `taraf`) with no enum/constraint and historical drift (`'1'`,`'TOLAK'`,`'TARIK DIRI'`,`'LEBIH MASA'` vs `'Dalam Proses …'`). Needs normalization to an enum + state-machine on migration.
- **`status_agihan` cross-system coupling**: this system only *reads* `status_agihan`/`sejarah_ppuu`/`butiran_peguam_panel_*` which are *written* by the separate peguam-panel system. Consolidation must unify ownership of this workflow.
- **Broken admin link**: `maklumat-agihan-semula.php` is referenced by the admin notification dropdown but **does not exist** → rejected-PP reassignment workflow dead-ends.
- **Processor↔tab numbering offset** (`process_decision_peringkat6.php` = UI "Peringkat 7"). Easy to mis-map during rewrite.
- **Schema drift**: Dec 2024 SQL dump (78 cols) ≠ live code (98 cols). ~20 columns (`modifiedBy/Date`, `pembatalan_borang_1`, `agamaLain`, `justifikasi_rujuk_pp`, `tarikh_*Kemaskini`, etc.) only exist in code + the 2026 Laravel migration. **Use the `sistem-rekod-kes-laravel` migrations as the schema source of truth, not the .sql dump.**

**MEDIUM — Maintainability**
- **Massive code duplication**: role-prefixed file triplets (`admin_*`, `pengarah_*`, plain) + `*_backup.php`, `test.php`, `cuba.php`, `process_decision_peringkat4 backup.php` (note the space in filename). Mega-files: `tabs_admin.php` 255KB, `tabs.php` 205KB, `tabs_pengarah.php` 250KB, `dashboard.php` 116KB, `senarai_pengguna.php` 86KB.
- **Orphan/foreign files** from a different appointment-booking system bundled in: `index.php` (refs `category`/`ref_bahagian`/`pic` tables, undefined `$con`), `semaksesi.php`/`jFail.php`/`page.php`/`cuba.php` (ref `include/conn.php` which doesn't exist). Dead code — exclude from rebuild.
- **DB name mismatch**: `db_connection.php` → `sistemspk`; the 2in1 project uses `iguaman_2in1`. Plus three identical connection handles (`$connection`/`$con`/`$conn`) used interchangeably across files.
- **No tests, no migrations, no .env** in the raw-PHP source (creds hardcoded in `db_connection.php`).

**Notifications**: only two — (1) PHPMailer emails (password reset, email-to-panel-lawyer); (2) in-app admin notification badge/dropdown for PP-rejected cases (status_agihan 9/14). No queue, no broadcast, no SMS.

---

## 13. Consolidation Notes for 2in1

- **Source of truth for schema** = `sistem-rekod-kes-laravel/database/migrations/2026_06_29_154458_create_forms_table.php` (98 cols) + sibling migrations. The `.sql` dump is stale.
- **The decision engine must be rewritten from the raw-PHP `process_decision_peringkat2/3/4/5.php` + `process_decision_peringkat6.php`** — the prior Laravel rewrite stubbed it out (do not port from the stub).
- **Unify peranan model** (0/1/2 → roles/permissions) and the cawangan-scoping `$is_hq` rule.
- **Resolve PP cross-system coupling** (`status_agihan`, `sejarah_ppuu`, `butiran_peguam_panel_*`) with the peguam-panel half during merge.
- Replace plaintext auth with bcrypt (already the direction in `senarai_pengguna.php` + the Laravel AuthController). Remove backdoor + URL-password-reset.
- Migrate No. Fail generation to a transactional/unique-constrained sequence.
- Normalize free-text statuses → enum + explicit state machine (the 7-peringkat lifecycle).
