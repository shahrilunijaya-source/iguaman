# iGuaman 2in1 — Parity Backlog (Build Order)

> Derived from [[parity-matrix]] (`context/parity-matrix.md`). Goal: reach **full parity** with both legacy systems, then exceed.
> **Process guardrail:** never build/rewrite a module without reading its legacy source first (`../sistem-peguam-panel`, `../sistem-rekod-kes`). Build to a matrix row; check it off here. Do not build from memory or screenshots.

Legend — size: **S** ≤½day · **M** 1–2 days · **L** 3–5 days · **XL** >1 week.

---

## Build progress (resume point)

**DONE (committed, verified):**
- ✅ EPIC C — lawyer registration (70 fields + 18 uploads) · editable profile · login provisioning · Tarik Diri withdrawal
- ✅ EPIC A — agihan 3-tier state machine (PPUU→Pengarah→KP) · Semula re-pick · list buckets (`StatusAgihan` + `sejarah_ppuu`)
- ✅ EPIC B — lawyer deactivation + death-redistribution
- ✅ EPIC D — Bidang Pengkhususan add/drop 3-tier approval (`butiran_peguam_panel_6`)
- ✅ EPIC E — exact `no_fail` generation (23 branch codes) + `check_nokp` duplicate guard

→ Whole **sistem-peguam-panel** domain at parity. Services in `app/Support/` (AgihanService, TarikDiriService, PeguamLifecycleService, PengkhususanService, NoFailGenerator, StatusAgihan). All state machines unit-tested.

- ✅ EPIC F — per-branch SLA matrix dashboards (5×, fixed 23-branch) + wide-column exports. `SlaMatrix` service (pure pivot+peratus, 5 defs perakuan40/fail-tiada60/fail-terlibat120/serahan7/khidmat60) + `StatistikSlaController` (`/statistik-sla`, index/show/pdf, all-branch aggregate bypassing CawanganScope, gated `statistik.view`). `WideExport` (verbatim 49/27/53-col lists, ref_kes JENIS KES join, derived BULAN/TAHUN, reason decode, computed STATUS PEMFAILAN, NoKP as Excel text formula `="…"`, title+filter envelope) + `LaporanPenuhController` (`/laporan/{type}/eksport-penuh`, CawanganScope = legacy HQ/branch gating). 19 tests (13 unit pure + 6 mysql smoke). Legacy bugs fixed: `*7.0` peratus typo + Putrajaya typo/missing-branch normalised to one canonical 23-list.

**REMAINING:**
- ⬜ EPIC G — Cuti Umum module (`RefCuti` model exists, no CRUD) · Lebih Masa 7-day auto-reassign cron · agihan transition emails.

---

## P0 — Critical (45 features). Build first.

### EPIC A — pp-agihan: 3-tier case-assignment spine `[XL]`
The single biggest debt. Legacy = PPUU → Pengarah → Ketua Pengarah endorsement chain over a numeric `forms.status_agihan` machine (0/8/9/10/11/13/14/15) with `sejarah_ppuu` history + role-routed forms + transition emails. Current build collapses it to one flat staff→offer step.
- [ ] Schema: `sejarah_ppuu` table (PPUU pick + sokong + KP keputusan, aktif/tutup rotation) — spec: `formAgihanBaru.php`, `agihanbaru/ppuu.php`
- [ ] Schema: `forms.status_agihan` numeric state machine + sub-status + `permohonan_kali` counter
- [ ] Agihan Baru: PPUU form (Pilihan A own-cawangan / B other-negeri + syor date + ulasan) — `agihanbaru/ppuu.php`
- [ ] Agihan Baru: Pengarah endorse (sokong→13 / tidak→4 + counter++ + rotation) — `agihanbaru/pengarah.php`
- [ ] Agihan Baru: Ketua Pengarah final approval (→1 offer / →14 reject-to-branch) — `agihanbaru/ketuapengarah.php`
- [ ] Agihan Semula: 3-tier re-pick → endorse (→15) → KP (→1/→14) — `agihansemula/*`
- [ ] Lebih Masa auto re-assign (7→4 + 16-col `sejarah_peguam_panel`) — `formAgihanSemasa.php`
- [ ] 4 list buckets (baru/semasa/semula/sejarah) with role + numeric-status scoping
- [ ] Role-routed detail hosts (maklumat-agihan-baru/semasa/semula → ppuu/pengarah/kp partials)
- [ ] Transition emails (PPUU / Ketua Cawangan / Pengarah Negeri / Pengarah / PP)
- [ ] Serah Semula hand-back action (status_agihan='5')

### EPIC B — pp-selenggara: lawyer lifecycle + death-redistribution `[L]`
- [ ] Schema: `peguam_panel.statusAktif` + `sebabTidakAktif` + date
- [ ] Deactivate lawyer w/ justifikasi (JK Disiplin / Meninggal / Lain) — `selenggara-peguampanel-detail.php`
- [ ] **DEATH-REDISTRIBUTION**: deactivate-deceased → per active case status_agihan=4, null assignee, sejarah inserts, fan-out emails — `query/selenggaraPengguna.php`
- [ ] Admin Jana Kata Laluan Sementara (officer + lawyer) + email — `janaNewPass` / `janaNewPassPP`

### EPIC C — pp-profil-daftar: full registration + profile + withdrawal `[XL]`
The original complaint (the JBG profile screenshot). Legacy `daftar.php` = 7-step wizard, ~70 fields, 18 PDF uploads.
- [ ] Schema: qualification/firma/bank/pengkhususan tables (butiran_peguam_panel_2..6) + `uploaded_files.kpBaru` + 18 doc types
- [ ] Registration: full 7-section wizard, all ~70 fields — `daftar.php`
- [ ] Registration: 18 PDF document uploads → `uploaded_files`
- [ ] Profile self-service EDIT (lawyer updates own record) — `profil.php` + `profilUpdate.php`
- [ ] Profile loader full JOIN (_2/3/4/5/6, CSO 1–5 not 1–3, doc flags) — `ppinfo.php`
- [ ] **Tarik Diri Mewakili OYD** — PP withdrawal form (9 reasons, Section 24) + PPUU/Pengarah/KP approval chain + status 12/16/17 + queue — `tarik_diri.php`, `tarikdiri/*`
- [ ] Lawyer login provisioning on KP approval (create `users` login row) — currently only creates peguam_panel master

### EPIC D — pp-kes-oyd: Bidang Pengkhususan add/drop lifecycle `[L]`
- [ ] Schema + model: `butiran_peguam_panel_6` + 9-state `checkbox_value_status` machine
- [ ] Lawyer request DROP (block on active matching case) / ADD — `profil-kemaskinibidangkes.php`
- [ ] Pengarah recommend → KP approve (3→7 DELETE, 4→9→2) — `maklumat-kemaskini-kes.php`
- [ ] Review queue + navbar pending badge — `senarai-kemaskini-kes.php`

### EPIC E — rk-permohonan: file-number + duplicate guard `[M]`
- [ ] `no_fail` generation: JBG.STATE3(jenis)seq/MMYY, 24 branch codes, per-branch+jenis sequencing — `jFail.php`
- [ ] `check_nokp` AJAX duplicate-IC detection + modal — `check_nokp.php`

### EPIC F — rk-statistik / rk-export: per-branch SLA matrices + wide exports `[L]`
- [ ] Per-branch SLA matrix render (BIL+CAWANGAN+4 kategori × CAPAI/TIDAK/PERATUS%) over fixed 23-branch list — 5 dashboards (40/60/120/7/60 day) — math already exists in KpiController
- [ ] Wide-column exports parity: permohonan (49 col), pendaftaran (29), status_fail (56) + envelope + NoKP-as-text — `export_*.php`

### EPIC G — rk-cuti: Cuti Umum module `[M]`
- [ ] CutiController + routes + views (Tambah/Kemaskini/Senarai) + `ref_cuti.idnegeri` 16-slot decode — `formTambahCuti.php` / `formUpdateCuti.php`

---

## P1 — High (73 features)
Grouped; see matrix for per-row evidence.
- pp-agihan: re-assignment list/history, role detail views, multi-row Laporan Kes Mahkamah + closure reasons, draft endorsement
- pp-selenggara: combined officer list (last_login+status), temp-password flows, full read-only dossier (YBGK/ADR/sijil/eVendor), public registration full form
- pp-profil-daftar: pengkhususan add/drop director review, status-code maps (4=DIBATALKAN, 5=SEMAKAN KP)
- pp-kes-oyd: OYD-specific columns, >40-day overdue red-row highlight
- rk-permohonan: guardian auto-unlock <18, kaedah_pemakluman JSON, pembatalan kelulusan + alasan, Pendamping Guaman handling, PDF column parity (~30 cols)
- rk-pengantaraan: completed-mediation list, monthly race/gender matrix, per-branch assignment matrices, SLA breach LISTS (60/120-day), per-branch compliance stats
- rk-statistik: month filter, drill-down cells, kesilapan-nombor-fail report, count matrices, per-statistik PDF buttons
- rk-export: 11 SLA-breach / reference / inverse-filter CSVs, universal Kesilapan exclusion, HQ-vs-branch gating

## P2 — Medium (57 features)
Status-code completeness, captcha, AJAX IC pre-check, conditional field toggles (etnik/agama/guardian/jantina-from-KP), public status checker, public lawyer directory, notification bell dropdown, dashboard cawangan/month/year filters + missing chart cuts, derived BULAN/TAHUN export columns, grand-total footers.

## P3 — Low / enhancements (71 features)
Print/PDF for lawyer dossier, 30-min idle auto-logout, poster public gallery, menu-tree completeness, consolidation of duplicate legacy handlers, misc cosmetic. Several already ✅ (Eloquent SQLi fixes, dompdf/xlsx supersets) — no action, kept for audit trail.

---

## Already exceeds legacy (no action — keep)
Server-side dompdf Surat Penugasan, Eloquent SQLi remediation across all modules, removed hardcoded remote PDO creds, FormRequest validation, xlsx exports (legacy had none), pagination on report tables, offer accept/reject mail flow.
