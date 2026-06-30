# Deliverable 2 — Feature Comparison Matrix

> System consolidation audit (Malaysian legal-aid, JBG / BHEUU). READ-ONLY analysis.
> Cross-references all four source systems + chat against the consolidated **2in1** Laravel app.
>
> **Sources**
> - **PP** = legacy `sistem-peguam-panel` (raw PHP, DB `sistemspk`) — map 01
> - **RK** = legacy `sistem-rekod-kes` (raw PHP, DB `sistemspk`) — map 02
> - **ADV** = legacy iGuaman advisory `be_iguaman` (ASP.NET) + `fe-iguaman` (Nuxt 2) — map 03
> - **CHAT** = `cbjbg` AI@JBG chatbot (Python/FastAPI) — map 04
> - **2in1** = consolidated Laravel 13 app (maps 05–09), commit `735dd4f`
>
> **Status values:** Fully available · Partially available · Missing · Duplicated · Obsolete · Conflicting · Unclear
> **Action values:** Retain · Improve · Merge · Replace · Remove
>
> Group key (Area column): Lawyer Panel · Case Records · Mediation · Court · Statistics/Reports · Advisory/Khidmat Nasihat · Appointments/Janji Temu · Citizen Portal · Chat · Platform/Auth/RBAC/Audit.

---

## How to read the status column

- **Fully available** — the feature exists in 2in1 with parity (or better) to the origin; verified in code maps.
- **Partially available** — exists but with a gap (missing sub-flow, broken hand-off, stub columns, no automation).
- **Missing** — origin feature with no 2in1 counterpart.
- **Duplicated** — two implementations of the same thing coexist in 2in1.
- **Obsolete** — origin feature deliberately dropped / superseded; no action beyond confirming removal.
- **Conflicting** — two implementations disagree on encoding/behaviour and clash on the same data.
- **Unclear** — built but with an ambiguity (port discrepancy, suspected mismap) needing a decision.

---

## A. LAWYER PANEL (peguam-panel registration, approval, assignment, withdrawal, lifecycle)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Lawyer Panel | PP (`daftar.php`) | Public lawyer registration — 7-section wizard, ~70 fields, 18 PDF uploads | External lawyers self-apply to join the panel | Prospective panel lawyers (public) | Fully available — `PeguamDaftarController` writes `butiran_peguam_panel_2..6` + `uploaded_files`; throttle 6/1 + honeypot; 18 doc types | Retain |
| Lawyer Panel | PP (`register.php`) | Older/alt registration entry point | Legacy duplicate of daftar | — | Obsolete — not ported (single `peguam/daftar` flow) | Remove (confirm dropped) |
| Lawyer Panel | PP (`semak.php` / `checkstatus.php`) | Public application-status lookup ("Semak Status Permohonan") | Applicant checks approval progress without login | Applicants (public) | Missing — no public status-checker route in 2in1 (`routes/web.php` has no `semak`/`status-permohonan`) | Improve (add public status check) |
| Lawyer Panel | PP (`permohonan-baru.php` + `query/ppHandler*`, `kemaskini.php`) | 3-tier application approval: Admin PT → Pengarah → Ketua Pengarah; `permohonan_status` 0→1→2/3/5 | Vet and approve panel lawyers | PT clerk, Pengarah, KP | Fully available — `PermohonanPeguamController` chain semak→sokong→keputusan; `permohonan_status` 0 Baharu/1 Lulus/2 Tidak Lulus/3 Tarik Diri; `semakan_ppuu` step added | Retain / Improve (status-code map differs from legacy 0–5; reconcile labels) |
| Lawyer Panel | PP (`query/kemaskini.php`, `selenggaraPengguna.php`) | On-approval: provision lawyer login + email credentials | Approved lawyer can log in | KP, system | Partially available — `promote()`/`provisionLogin()` creates `users` row + temp password, but password shown ONCE in flash, **no email delivery**; firma address/phone stubbed `'-'` not copied from `_4` | Improve (email credentials; copy full firma data) |
| Lawyer Panel | PP (`profil.php` / `profilUpdate.php`) | Lawyer self-profile view + edit | Lawyer maintains own record | Panel lawyer | Fully available — `PeguamController` profil + profil/kemaskini, rewrites `_2/_3/_4/_5` + re-uploads 18 docs | Retain |
| Lawyer Panel | PP (`selenggara-peguampanel*.php`) | Staff view/edit lawyer master + activate/deactivate | JBG maintains lawyer records | Admin, koordinator, IT | Partially available — `PeguamPanelController` show/edit/update built, but gated only by `auth`+`system.view` (no `permission:`/`role:`) — any staff with `system.view` can edit a lawyer master | Improve (add permission gate) |
| Lawyer Panel | PP (`query/selenggaraPengguna.php` `janaNewPass*`) | Admin "Jana Kata Laluan Sementara" (regenerate temp password + email) for officer & lawyer | Account recovery by admin | Admin / IT | Partially available — `UserController` user CRUD + `must_change_password`; standard `PasswordResetController` exists, but the legacy admin "jana + email temp pass" one-click flow not explicitly ported | Improve |
| Lawyer Panel | PP (`selenggara-pegjbg*.php`) | Superadmin maintain JBG officer accounts | Officer account admin | Superadmin (IT UTM) | Fully available — `UserController` + `PegawaiController` (`pegawai_jbg`) + RBAC UI | Retain |
| Lawyer Panel | PP (`tarik_diri.php`, `query/tarikdiri.php` 103KB) | Tarik Diri Mewakili OYD — lawyer withdrawal, 4-stage (PP→PPUU→Pengarah→KP), 9 reasons (Seksyen 24), status 12/16/17→6 | Lawyer withdraws from representing an assisted person | Panel lawyer, PPUU, Pengarah, KP | Fully available — `TarikDiriService` + `TarikDiriController`; 9 reasons; status 2→12→16→17→4 (case)/6 (row); `akuanTarikDiri` PDF | Retain |
| Lawyer Panel | PP (`query/tarikdiri.php` Dompdf) | "Surat Batal Penugasan" cancellation letter PDF on withdrawal approval | Formal record of withdrawal | KP / lawyer | Partially available — 2in1 cetakan uses dompdf (Surat Penugasan exists) but the **withdrawal cancellation letter** is not explicitly mapped as a generated artifact | Improve (port cancellation-letter PDF) |
| Lawyer Panel | PP (`senarai-kemaskini-kes.php`, `profil-kemaskinibidangkes.php`) | Bidang Pengkhususan add/drop — 9-state `checkbox_value_status` machine, Pengarah→KP approval, drop-block on active case | Lawyer changes practice areas; controlled by approval | Lawyer, Pengarah, KP | Fully available — `PengkhususanService` + `KemaskiniBidangController`; states 1/2/3/4/7/9; drop guarded by active-case-in-category check | Retain (note: state `0` at daftar is an unhandled dead state) |
| Lawyer Panel | PP (`query/selenggaraPengguna.php` death cascade) | Lawyer deactivate/reactivate + **death-redistribution** (deceased active lawyer → all cases back to pool) | No assisted person left unrepresented | Admin, koordinator, Pengarah, KP | Fully available — `PeguamLifecycleService`; `peguam_panel.statusAktif`+sebab+date; `redistributeActiveCases()` transactional; blocks login | Retain |
| Lawyer Panel | PP (`navbar.php` badge counts) | Per-role notification badges (counts of pending applications/agihan/withdrawal) | At-a-glance pending work | All staff roles | Partially available — agihan/tarik-diri/kemaskini queues exist with counts on their pages, but **no global notification bell/dropdown** across the shell | Improve (add notification bell) |
| Lawyer Panel | PP (`status-peguam-detail.php`, full dossier YBGK/ADR/sijil/eVendor) | Read-only full lawyer dossier (CSO 1–5, YBGK, ADR, sijil, eVendor) | Full vetting view | Pengarah, KP, admin | Partially available — profile/show renders `_2..6` but full read-only dossier print/PDF for lawyer is a P3 item; CSO 1–5 captured | Improve |
| Lawyer Panel | PP (`func/`, `getMalayDate.php`, `check_kp.php`) | IC validation + Malay-date helpers | Data hygiene | System | Fully available — Laravel validation + Carbon; IC normalisation (`normalizeNokp`) | Retain |
| Lawyer Panel | PP (`phpinfo.php`, `test-emel.php`, `debug.log`, `log_masuk_backdoor` n/a here) | Shipped debug/test files | none (liability) | — | Obsolete — not ported | Remove (confirm absent) |

---

## B. CASE RECORDS (permohonan intake, keputusan, no_fail, file closure, OYD, attachments, printing)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Case Records | RK (`peringkat1.php`, `process_decision_peringkat1_tabs.php`) | Peringkat 1 — Permohonan Bantuan Guaman intake (nama, nokp→umur, jantina, OKU, bangsa, etnik, kaedah penerimaan) | Citizen legal-aid application intake | Officer (pegawai) | Fully available — `KesController::store`; auto-derive umur; cawangan from session; AJAX dup-IC guard | Retain |
| Case Records | RK (`check_nokp.php`) | Duplicate-IC detection (AJAX) + modal | Prevent duplicate applicants | Officer | Fully available — `kes.semak-nokp` (`checkNokp`) | Retain |
| Case Records | RK (`process_decision_peringkat2.php` 408 lines) | Peringkat 2 — Keputusan (Diterima/Ditolak), `keputusan_menteri` override when `kelulusan='Perlu'`, 30-day rule | Director's decision to accept/reject aid | Pengarah / KP | Partially available — `KeputusanController::lulus/tolak` sets `keputusan`+`status`+`diterima`+dates, gated `kes.keputusan`; but **30-day rule, `keputusan_menteri` override, `batal` action not confirmed** in the lean controller | Improve (verify 30-day + menteri-override + batal) |
| Case Records | RK (`process_decision_peringkat2.php` `jana`) | No. Fail generation — `JBG.<state>(<jenis>)<seq>/<MMYY>`, 23-branch `$jbgMap`, per-branch+jenis sequence | Official file number on approval | Officer / system | Fully available — `NoFailGenerator` (23 branch codes); auto on store/buka-kes if blank | Improve (legacy had COUNT race; verify transactional/unique to avoid duplicate file numbers) |
| Case Records | RK (`process_decision_peringkat2.php` `batal`) | Pembatalan Borang 1 (`pembatalan_borang_1='Ya'`, `Dibatalkan`/`Tamat`, alasan) | Cancel an application | Officer | Partially available — column `pembatalan_borang_1` imported; **cancel action/route not explicitly mapped** | Improve |
| Case Records | RK (`process_decision_peringkat6.php` = Peringkat 7) | Tutup Fail — `Fail Tutup`, `sebab_tutup_fail` (incl. Pemindahan / Kesilapan Menjana Nombor Fail), kos completeness | Official file closure | Pengarah / KP | Fully available — `KeputusanController::tutupFail` gated `kes.keputusan`; closed list `fail-tutup` | Retain |
| Case Records | RK (`senarai_pemohon.php`, `Senarai_Orang_Yang_Dibantu.php`, `senarai_fail_tutup.php`) | Case browse/list screens (applicants in process / OYD / closed files) with cawangan scope | Day-to-day case navigation | All staff | Fully available — `KesController` index/tutup with filters (cawangan/status/kategori/q, paginate 20) + `CawanganScope` | Retain |
| Case Records | RK (`butiran_oyd`, OYD screens) | OYD (beneficiary) registry CRUD, unique IC | Track assisted persons | Officer | Fully available — `OydController` (`butiran_oyd`), unique `kp_oyd`, audited | Retain |
| Case Records | RK (`e-poster.php`, `poster_*.php`) | e-Poster board (upload/update/delete; `status_poster` aktif/tidak; non-admin sees aktif only) | Internal/public notice board | Admin + viewers | Partially available — `PosterController` admin CRUD exists (`permission:selenggara.poster`); but **public/awam poster gallery view not exposed** (only admin side) | Improve (add public gallery if needed) |
| Case Records | RK (`uploaded_files`, `move_uploaded_file`) | Case attachments (lampiran) | Store case documents | Officer | Fully available — `LampiranController` store/download/destroy on private `local` disk, auth-streamed, mimes pdf/jpg/png/doc/xls ≤10MB, audited | Retain (better than legacy: private disk + auth) |
| Case Records | RK (`ref_kes` CRUD `senarai_kes.php`) | Jenis Kes (litigation case-type) master maintenance | Maintain case taxonomy | Admin | Fully available — `RefKesController` (`selenggara.ref_kes`) | Retain |
| Case Records | RK (`cetakanMaklumatPermohonan.php` FPDF) | Per-case dossier / penugasan / laporan PDFs | Printable case file | Officer / lawyer | Fully available — `CetakanController` via dompdf (ringkasan/penugasan/laporan); penugasan blocks if no lawyer assigned | Retain (FPDF→dompdf upgrade) |
| Case Records | RK (`index.php`, `semaksesi.php`, `jFail.php`, `cuba.php`, `test.php`) | Orphan/foreign appointment-booking files + scratch files | none (dead code) | — | Obsolete — not ported | Remove (confirm dropped) |
| Case Records | RK (`log_masuk_backdoor.php`) | Backdoor login | none (security liability) | — | Obsolete — not ported; auth rebuilt | Remove (confirm absent) |
| Case Records | RK / PP (`forms` 78→98 col monolith) | Single wide case table holding entire lifecycle | The case spine | All domains | Conflicting/Unclear — imported verbatim (98 cols) but documented intent to "decompose into Case + detail tables" is **NOT done**; mixed casing + duplicate-intent columns; `$timestamps=false` | Improve (planned decompose; reconcile column drift) |

---

## C. MEDIATION (Pengantaraan, sidang scheduling, cuti/leave calendar)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Mediation | RK (`process_decision_peringkat3.php`) | Peringkat 3 — assign mediator, `kaedah_sidang`, party locations, agreement dates, `status_sidang` | Run mediation before litigation | Officer / mediator | Partially available — `PengantaraanController::update` writes mediation fields free-text; `status_pengantaraan='Ya'/'Tidak'` by convention (no enum); several reason columns not populated | Improve (port full pengantaraan write-path; the missing-column stubs in exports trace to this) |
| Mediation | RK (`process2.php`, `sejarah_sidang`) | Tangguh Sidang — hearing postponement log (multi-row) | Audit of rescheduled hearings | Officer | Fully available — `PengantaraanController::tangguhSidang` inserts `sejarah_sidang`; `status_sidang='Tangguh'` | Retain |
| Mediation | RK (`formTambahCuti.php`, `list_cuti.php`, `ref_cuti`) | Cuti Umum / leave-calendar management (mediator availability + SLA day calc) | Public-holiday calendar for SLA & slot logic | Admin | Fully available — `CutiController` + `/cuti`; `CutiNegeri` 16-state bitmask via `CutiNegeriController`; reused by slot engine | Retain |
| Mediation | RK (`cal.php`, `calc_days.php`) | Working-day calculator (exclude cuti/weekends) | SLA day counts | System | Fully available — folded into `SlotAvailabilityService` working-day logic + `CutiNegeri`; SLA via `DATEDIFF` | Retain |
| Mediation | RK (`detail_elaun.php`) | Mediator allowance detail | Pay mediators | Admin | Missing/Unclear — no `elaun` controller in 2in1 | Improve (confirm in/out of scope) |
| Mediation | RK (export columns: `alasan_tidak_setuju_pengantara`, `alasan_gagal_pengantara`, `alasan_tangguh_sidang`, `alasan_tidak_rujuk_pengantaraan`, `tarikh_perjanjian`) | Mediation reason/agreement detail capture | Reporting completeness | Reporting | Partially available — columns degrade to `-Tiada Maklumat-` in wide exports because the write-path isn't fully ported | Improve (port write-path, then exports light up) |

---

## D. COURT (Kes Mahkamah, Laporan Kes, court reference data)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Court | RK (`process_decision_peringkat5.php`) | Peringkat 5 — Kes Mahkamah section (nama_pihak/responden, mahkamah, pemfailan, perintah, kos, completion dates) | Track litigation in court | Officer | Fully available — `MahkamahController::update` (`MahkamahRequest`) writes court + closure date fields | Retain |
| Court | RK (`laporan_kes_mahkamah.php`, `process_laporan_kes_mahkamah.php`, `laporan_kes`) | Laporan Kes — multi-row court progress reports (one per sebutan/mention) | Record each court mention | Officer / lawyer | Fully available — `MahkamahController::storeLaporan/destroyLaporan` (`laporan_kes` child) | Retain (note: `laporan_kes.id_kes` is `varchar(20)` vs `forms.id` int — type mismatch, no FK) → Improve |
| Court | RK (`senarai_mahkamah.php`, `mahkamah_sivil`/`mahkamah_syariah` CRUD) | Court reference data maintenance (civil + syariah) | Court registry master | Admin | Fully available — `MahkamahRefController` serves both via `{jenis: sivil\|syariah}` (`selenggara.mahkamah_ref`) | Retain (consider merging the two identical-structure tables into one + `jenis` discriminator) → Improve |
| Court | RK (`pengarah_laporan_kes_mahkamah_sivil/syariah.php`, `sivil_syariah_HQ.php`) | Civil/Syariah split court reports + HQ aggregate | Director-level court reporting | Pengarah, HQ | Fully available — folded into Laporan + the case detail; HQ via `cawangan.view-all` | Retain |
| Court | PP (`cetakanLaporanKesMahkamah.php` FPDF) | Court-case report PDF | Printable court report | Lawyer / officer | Fully available — `CetakanController` laporan (dompdf) | Retain |
| Court | ADV (`CawanganMahkamah`, `pages/cawangan/mahkamah/`) | Court branches reference (advisory side, used in wakil flow + reports) | Geo/venue reference for advisory | Staff | Partially available — `khidmat_nasihat.id_mahkamah` link exists (no FK) + `MahkamahSivil/Syariah` masters; advisory-specific `CawanganMahkamah` master not separately built | Merge (use the rekod-kes court masters) |

---

## E. STATISTICS / REPORTS (dashboards, KPI, SLA, exports, prints)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Statistics/Reports | RK (`dashboard.php` 116KB) | Aggregate dashboard (status/keputusan/sumbangan/bangsa/kategori/sidang breakdowns) | At-a-glance operational stats | All staff | Fully available — `StatistikController` 8 KPI tiles + 7 breakdowns + byBulan; Excel + PDF; `CawanganScope` | Retain |
| Statistics/Reports | RK (`kpi.php` 76KB) | KPI SLA dashboard (40/60/120/7-day thresholds via DATEDIFF) | SLA compliance tracking | Management | Fully available — `KpiController` 5 KPI defs per kategori×month | Retain |
| Statistics/Reports | RK (SLA senarai/statistik screens) | Per-branch SLA matrices (5×, fixed 23-branch × 4 kategori, CAPAI/TIDAK/%) + breach drill-down lists | Branch SLA scorecards + breach lists | Management, HQ | Fully available — `StatistikSlaController` + `SlaMatrix` (5 defs) + `SlaListExport` breach CSVs; 2 legacy bugs fixed (`*7.0` typo, Putrajaya) | Retain |
| Statistics/Reports | RK (`statistik_*_pengantaraan.php`) | Mediation assignment matrices (kategori, bulanan, pencapaian funnel) | Mediation performance | Management | Fully available — `StatistikPengantaraanController` + `PengantaraanMatrix` (3 views); 2 legacy bugs fixed | Retain (note F2 numerator port-deviation documented) |
| Statistics/Reports | RK (`senarai_kesilapan_penjanaan_nombor_fail.php`) | Kesilapan Penjanaan Nombor Fail report (duplicate file-number catcher) | Catch file-number generation errors | Admin | Fully available — `KesilapanController` + `KesilapanMatrix` (branch×month) + 36-col wide CSV | Retain |
| Statistics/Reports | RK (13 `export_*.php`, all CSV) | Wide-column CSV exports (permohonan 49 / pendaftaran 29 / status-fail 56 / pengantaraan / tidak-dirujuk) with envelope + NoKP-as-text | Excel-ready data extracts | Officer / management | Fully available — `LaporanPenuhController` + `WideExport` (UTF-8 BOM, envelope, BIL, ref_kes join, reason decode); 5 types | Retain (some pengantaraan cols degrade to NO_DATA — tied to mediation write-path gap) |
| Statistics/Reports | RK (narrow on-screen `laporan_*.php`) | 6 narrow reports (permohonan/pendaftaran/status-fail/penugasan-/pencapaian-/tidak-dirujuk-pengantaraan) | Operational report tables | Staff | Fully available — `LaporanController` (table + CSV + PDF, 6 keys) | Retain |
| Statistics/Reports | RK (`statistik_*_syariah.php`) | Syariah-specific stats (borang 1 syariah, kes selesai, pendaftaran) | Syariah reporting cut | Management | Partially available — covered by kategori filters in the matrices; dedicated Syariah-only screens not separately reproduced | Improve (confirm coverage via filters) |
| Statistics/Reports | RK (15 `cetakan*.php` FPDF prints) | Printable statistic/report PDFs | Hardcopy reports | Management | Fully available — dompdf PDF on SLA/pengantaraan/laporan; per-statistik PDF buttons shipped | Retain |
| Statistics/Reports | RK (`composer.json` phpspreadsheet, unused) | Excel exports | Excel output | — | Fully available — `maatwebsite/excel` used (statistik + KN reports); legacy never actually used phpspreadsheet | Retain (2in1 exceeds legacy) |
| Statistics/Reports | ADV (`pages/laporan/**`, 8 KN reports) | Advisory statistical reports (by cawangan/kategori/subkategori/registration/legal-opinion/how-heard/satisfaction/race-gender) | KN performance + satisfaction reporting | Management | Fully available — `LaporanKhidmatNasihatController` + `LaporanKnService` 8 reports; 2 Excel exports | Retain |
| Statistics/Reports | RK / KPI | SLA `khidmat` 60-day end-date | Mediation-service SLA | Management | Conflicting — `SlaMatrix` uses `tarikh_persetujuan` as end col; `KpiController` uses `tarikh_selesai` for the same 60-day rule | Improve (pick one end column) |

---

## F. ADVISORY / KHIDMAT NASIHAT (legal-advisory request + officer processing + category tree + feedback)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Advisory/Khidmat Nasihat | ADV (`permohonan-baru.vue`, 4-step) | KN application wizard (Maklumat → Bayaran → Slot → Perakuan); `DIRI SENDIRI` / `SEBAGAI WAKIL` | Digitise legal-advisory intake | Citizen, PT clerk, prison/JKM officer | Fully available — staff `KhidmatNasihatController` + citizen `Awam\PermohonanController`, shared `KhidmatNasihatService`; both DIRI_SENDIRI/SEBAGAI_WAKIL (staff) | Retain (rebuilt from frontend contract per D7) |
| Advisory/Khidmat Nasihat | ADV (saringan / income test) | Eligibility screening — RM 50,000 income threshold, sumbangan path, criminal-companion bypass | Means-test eligibility | Citizen, clerk | Fully available — `saringan`/`saringanSemak`; server re-asserts `session(...lulus)` on submit (403 otherwise); wakil/draft bypass | Retain |
| Advisory/Khidmat Nasihat | ADV (`bayaran.vue`, fee tiers) | Fee computation — RM 0 (free/prison/JKM) / RM 10 (default) / RM 260 (sumbangan) | Charge correct advisory fee | Citizen, clerk | Partially available — `KhidmatBayaran::kira()` computes fee + stores `jumlah_bayaran`/`status_bayaran`, but **no payment-confirmation UI/route** flips `status_bayaran` (informational only) | Improve (add receipt/payment confirmation) |
| Advisory/Khidmat Nasihat | ADV (officer flow: assign PKN, accept/reject, attendance, complete) | Officer processing chain — assign PKN → terima/tolak → kehadiran → selesai; `status_kn` BAHARU→DALAM_PROSES→SELESAI | Staff process advisory sessions | PT clerk, PKN officer | Fully available — `KhidmatProsesController` + `KhidmatProsesService` with hard-coded transition guards; fixes legacy `IdPegawaiKN` drop bug | Retain |
| Advisory/Khidmat Nasihat | ADV (TIDAK HADIR path) | No-show handling | Close/retry no-show sessions | PKN officer | Partially available — `kehadiran(false)`→temu `TIDAK_HADIR` but `status_kn` stays `DALAM_PROSES` forever; no reschedule-after-no-show, no auto-close | Improve (HANGING STATE — add no-show terminal/rebook) |
| Advisory/Khidmat Nasihat | ADV (`tolak` rejection) | PKN reject leaves KN appointment-less | Reassign rejected case | PKN officer | Partially available — `tolak` sets temu BATAL but leaves `status_kn` unchanged; no staff-side rebook path | Improve |
| Advisory/Khidmat Nasihat | ADV (3-level category tree: Kategori → KategoriKes → SubKategori) | Advisory case taxonomy (SIVIL/SYARIAH/PENDAMPING JENAYAH/PENDAMPING GUAMAN) | Tag advisory by case type | Citizen, officer, reports | Fully available — `ref_kategori_kn`/`ref_kategori_kes_kn`/`ref_subkategori_kn` (3-level), `KategoriKnController` CRUD; distinct from `ref_kes` (per D3) | Retain (do NOT merge with litigation `ref_kes`) |
| Advisory/Khidmat Nasihat | ADV (KN→litigation handoff) | "Buka Kes" — completed advisory spawns a litigation `forms` row | Bridge advisory → case records | PKN officer | Fully available — `bukaKes` (SELESAI + `id_forms===null`) creates `forms`, back-links, generates `no_fail`; `normalizeNokp` for legacy column | Retain (this is the integration seam ADV→RK — new value not in any legacy system) |
| Advisory/Khidmat Nasihat | ADV (`maklumbalas`, batch-1 feedback) | Maklum Balas feedback (how-heard checkboxes, satisfaction rating, suggestions); one per KN | Customer satisfaction + how-heard reporting | Citizen (public) | Fully available — `MaklumBalasController` PUBLIC (no auth, throttle 6/1); `maklum_balas` unique per KN; feeds reports 2 & 7 | Retain (per D5 public, one per advisory) |
| Advisory/Khidmat Nasihat | ADV (`print/maklumat-permohonan.vue`) | Printable KN application summary | Hardcopy advisory record | Citizen, officer | Partially available — KN detail views built; dedicated KN print/PDF not explicitly mapped (reports have print CSS) | Improve (confirm KN dossier print) |
| Advisory/Khidmat Nasihat | ADV (`DIKECUALIKAN` badge) | Exempted-status surfacing | Filter exempted advisories | Officer | Unclear — `status_kn` enum is DRAF/BAHARU/DALAM_PROSES/SELESAI/BATAL; `DIKECUALIKAN` not in the 2in1 enum | Improve (confirm if needed) |
| Advisory/Khidmat Nasihat | ADV backend (`KhidmatNasihat` thin entity, `[Authorize]` commented out) | Server-side authorization | Security | — | Fully available — server-enforced (`KhidmatNasihatPolicy` owner-gate + permission middleware); fixes legacy client-only gating | Retain (security upgrade) |

---

## G. APPOINTMENTS / JANJI TEMU (slot generation, availability, booking, calendar, holidays/closures)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Appointments/Janji Temu | ADV (`slotJanjiTemu/_id.vue`, `AutoCreate`) | Slot auto-generation (per cawangan/bilik, working/lunch hours, slot minutes, weekend inclusion) | Supply bookable appointment slots | Admin / PT clerk | Fully available — `SlotGenerator` (`slot.manage`) over `slot_temu_janji`; skips weekends/holidays/closures; idempotent; MAX_RANGE 180d. **Note: ADV backend never implemented this (frontend-only) — 2in1 builds it real** | Retain (new real implementation) |
| Appointments/Janji Temu | ADV (`bilik/_id.vue`, rooms) | Bilik (rooms) per branch | Room-level slot capacity | Admin | Fully available — `cawangan`+`bilik` tables, `CawanganController` bilik CRUD. **ADV had NO Bilik table (frontend-only)** | Retain |
| Appointments/Janji Temu | ADV (`GetAvailableDate/Time`, 4-working-day lead time) | Slot availability (≥4 working days ahead, skip weekend/holiday/closure, ≥1 open slot) | Valid bookable dates/times | Citizen, clerk | Fully available — `SlotAvailabilityService` (`MIN_WORKING_DAYS=4`); JSON pickers `slot.tarikh`/`slot.masa` | Retain |
| Appointments/Janji Temu | ADV (`SetKhidmatNasihatSlotTemujanji`) | Booking — lock slot, create temu_janji (MENUNGGU), flag slot taken | Book a consultation | Citizen, clerk | Fully available — `KhidmatNasihatService::bookSlot` (`FOR UPDATE` race-safe), `releaseSlot`, `reschedule` | Retain |
| Appointments/Janji Temu | ADV (`temu_janji` lifecycle) | Appointment status machine MENUNGGU→DISAHKAN→HADIR/TIDAK_HADIR→SELESAI (+BATAL) | Appointment state | Officer, citizen | Fully available — `TemuJanji::STATUS` + `KhidmatProsesService::TEMU_TRANSITIONS` enforced | Retain (note TIDAK_HADIR hanging state in F) |
| Appointments/Janji Temu | ADV (`kalendar/_id.vue`) | Branch calendar overlay (holidays/closures/slots) read-only | Visualise availability | Staff | Fully available — `JadualJanjiTemuController` (`slot.view`) month grid, Malay labels | Retain |
| Appointments/Janji Temu | ADV (`penutupan-hari-operasi.vue`, `HariCutiOff`) | Operation closures + holiday calendars (umum/negeri) | Block booking on closed days | Admin / PT clerk | Fully available — `penutupan_operasi` + `PenutupanOperasiController`; `ref_cuti`+`CutiNegeri`. **ADV backend never implemented `HariCutiOff` (frontend-only)** | Retain |
| Appointments/Janji Temu | ADV (`penetapan-sesi-janji-temu.vue`, session template) | Per-branch session config (active-days mask, hours, slot length) | Configure branch slot template | Admin | Fully available — session-config cols on `cawangan` (`hari_minggu`/`masa_buka`/`masa_tutup`/`tempoh_slot_minit`) + `slot.sesi` updateSession | Retain |
| Appointments/Janji Temu | ADV (`kaedahTemuJanji` virtual option) | Virtual / online appointment mode | Remote consultations | Citizen | Missing/Obsolete — legacy fixed `'SECARA FIZIKAL'` (no virtual wired); 2in1 also physical-only | Retain (parity; add virtual only if scoped) |

---

## H. CITIZEN PORTAL (Awam — public register/login, self-service journey)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Citizen Portal | ADV (`Public/CreatePenggunaAwam`, `daftar-pengguna.vue`) | Citizen self-register (IC-based) + login | Public access to legal aid | Citizens | Fully available — `Awam\PublicAuthController` daftar/login by `nokp` (IC); session captcha + honeypot; throttle 6/1, 10/1. **ADV backend had NO `PublicController` (frontend-only)** | Retain (per D2 full public portal) |
| Citizen Portal | ADV (`ByUser` own KN list) | Citizen dashboard — own advisory list + status | Track own applications | Citizen | Fully available — `Awam\PortalController@index` (own KN, paginate 10) | Retain |
| Citizen Portal | ADV (citizen create DIRI SENDIRI) | Self-service advisory application (saringan → wizard → slot → hantar) | Citizen lodges own request | Citizen | Fully available — `Awam\PermohonanController` (DIRI_SENDIRI only); live slot picker; saringan re-asserted server-side | Retain |
| Citizen Portal | ADV (cancel / reschedule) | Self-service cancel + reschedule appointment | Citizen manages own booking | Citizen | Fully available — `@cancel` (assertCancellable + releaseSlot), `@reschedule` (release+rebook); owner-gated `KhidmatNasihatPolicy` | Retain |
| Citizen Portal | ADV (`DokumenSokongan`) | Citizen document upload + owner-gated download | Attach supporting docs | Citizen | Fully available — `@upload` (`AwamLampiranRequest` mimes pdf/jpg/png ≤5MB, MIME-derived type, throttle 20/1), `@download` owner-gated, private disk | Retain (recent commit; matches spk-laravel DocumentController intent) |
| Citizen Portal | ADV (forgot password `RequestResetPassword`) | Citizen password reset | Account recovery | Citizen | Partially available — staff `password/forgot` exists; awam-specific reset path not separately confirmed (awam logs in by IC) | Improve (confirm awam reset) |
| Citizen Portal | RK/PP (public status checker) | Public application-status lookup without login | Check status anonymously | Public | Missing — no public status-check route (mirrors PP `semak.php` gap) | Improve |
| Citizen Portal | ADV (prison/JKM `sebagaiWakil` paths, payment=0) | Lodge on behalf of inmate/ward (forced wakil, free) | Vulnerable-group access | Prison/JKM officer | Partially available — staff wizard supports SEBAGAI_WAKIL (PENJARA/JKM/MAHKAMAH) with RM0 fee; dedicated prison/JKM officer roles not in the 8-role set (handled via staff wakil) | Improve (confirm prison/JKM officer onboarding) |

---

## I. CHAT (cbjbg AI@JBG assistant)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Chat | CHAT (`main-with-cors.py` FastAPI) | AI Q&A assistant (LangChain + GPT-4o + FAISS RAG over JBG legal-aid docs + web/portal scrape) | Public legal-aid information Q&A | Public (landing page) | Fully available — kept as microservice; `ChatbotController@ask` proxy (`/chatbot/ask`, throttle 20/1) + Blade widget (`partials/chatbot.blade.php`) | Retain (per D9 microservice + proxy) |
| Chat | CHAT (`/generate_token` + `/forward_message`) | Basic→JWT auth, server-side proxy (creds never reach browser) | Secure bot access | System | Fully available — proxy mints JWT server-side; per-session `chatbot_sid` | Retain |
| Chat | CHAT (widget include scope) | Chatbot widget surfacing | Reach users where they are | Public | Partially available — widget included **only on `welcome.blade.php`** (public landing); not surfaced to authenticated staff/citizen areas | Improve (decide wider surfacing) |
| Chat | CHAT (`.env` plaintext secrets, shared Basic creds, open `/docs`, header reflection) | Operational security posture | Protect keys/cost | Ops | Partially available / Conflicting — works, but **5 plaintext secrets must be ROTATED**, shared Basic creds duplicated across repos, `/docs` open, request headers reflected | Improve (rotate secrets; lock `/docs`; secret store; CORS to real origin) |
| Chat | CHAT (`news_today` MySQL tool on `10.19.206.132`) | Today's-news tool over JBG internal DB | News answers | Public | Obsolete (in cloud) — DB unreachable from HF Spaces; tool dead in deploy | Remove (or re-point if internal-hosted) |
| Chat | CHAT (in-RAM `user_conversations`) | Conversation memory | Multi-turn context | Public | Partially available — ephemeral in-process dict; lost on restart, not shared across replicas | Improve (persist memory if multi-replica/restart-resilient wanted) |
| Chat | CHAT (record/permission access) | Bot access to 2in1 records | "ask about my case" | — | Missing (by design) — bot has zero access to 2in1 data/roles | Retain (net-new integration only if scoped later) |

---

## J. PLATFORM / AUTH / RBAC / AUDIT (cross-cutting)

| Area | Original System | Feature | Business Purpose | Used By | Current Status in New System | Action Required |
|---|---|---|---|---|---|---|
| Platform/Auth/RBAC/Audit | PP+RK+ADV (3 separate plaintext user tables) | User authentication | Log in | All | Fully available — unified single `users` table + bcrypt (collapses `users`+`users_peguam_panel_2`+`_3`); `Auth::attempt`; replaces plaintext (CRITICAL legacy vuln) | Retain (major security upgrade) |
| Platform/Auth/RBAC/Audit | PP+RK (plaintext `===` password compare, bcrypt commented out) | Password security | Protect accounts | All | Fully available — bcrypt + `must_change_password` + `ForcePasswordChange` middleware + active-only login | Retain |
| Platform/Auth/RBAC/Audit | PP+RK (4-/6-digit numeric captcha) | Login captcha | Anti-brute-force | All | Partially available — trivial 2-number sum captcha (legacy-parity weak); throttle:10,1 | Improve (stronger captcha) |
| Platform/Auth/RBAC/Audit | PP (`allowRole()` broken; RK `$is_hq`) | Role-based access control | Authorize actions | All | Partially available / Conflicting — `spatie/laravel-permission` (9 roles, 40 perms) BUT gating split across `permission:` route / `role:` route / in-controller `->can()`; several seeded perms NOT enforced as route middleware | Improve (consolidate gating; audit each controller) |
| Platform/Auth/RBAC/Audit | (new) | RBAC management UI (roles + per-role permission matrix) | Admin-editable RBAC | Admin | Fully available — `RoleController` + `RolePermissionController` (`urus.peranan`, admin-only); system roles protected | Retain |
| Platform/Auth/RBAC/Audit | ADV (`Peranan` CRUD, `akses-pengguna`) | Per-role access management | Configure roles | Superadmin | Fully available — matches the RBAC UI above | Retain |
| Platform/Auth/RBAC/Audit | (new, batch 13) | `awam` role seeding | Citizen portal gate | Citizens | Conflicting — `awam` role seeded by **migration 130002**, NOT in `RolePermissionSeeder::ROLES` nor `RoleController::SYSTEM_ROLES` → admin could rename/delete it and break the citizen gate | Improve (HIGH — add awam to protected system roles) |
| Platform/Auth/RBAC/Audit | RK (`$is_hq` cawangan scoping) | Branch (cawangan) data isolation | Branch sees only own data | All staff | Partially available — `CawanganScope` global scope on **`Form` only**; KN/temu_janji/slot/OYD/lawyer tables manually scoped in 3 places (inconsistent) | Improve (extend/standardise branch isolation) |
| Platform/Auth/RBAC/Audit | RK (free-string `cawangan`) | Branch master | Branch reference | All | Fully available — real `cawangan` master (+`jenis` JBG/JKM/PENJARA), `nama` matches legacy string so scope keeps working (per D4) | Retain |
| Platform/Auth/RBAC/Audit | PP (`audit_trail` table) | Audit logging | Compliance trail | Admin | Fully available — `Audit::log()` writer + `AuditController` viewer (`audit.view`); 25+ call sites; INSERT/UPDATE/DELETE/APPROVE/REJECT | Retain (note: record-level only, field-level old/new always NULL; denormalised, no FK) → Improve (field-level diffs) |
| Platform/Auth/RBAC/Audit | PP+RK (PHPMailer SMTP, hardcoded Gmail app-password) | Transactional email | Notify users | All | Partially available — Laravel Mail (dev driver `log`); `NotifikasiAgihan`/`AgihanTransisiMail`/`KesDitawarkanMail`/`KesLebihMasaMail` wired; **prod mail driver + rotate leaked `aplikasi.jbg@bheuu.gov.my` password are pre-prod TODO** | Improve (prod mail + rotate secret) |
| Platform/Auth/RBAC/Audit | PP (hardcoded DB creds ×4 hosts, remote PDO `10.19.202.135`) | DB connectivity | Data access | System | Fully available — single `.env` config; remote PDO removed; grep-clean of hardcoded secrets | Retain |
| Platform/Auth/RBAC/Audit | PP (SQLi via string-interpolation) | Query safety | Prevent injection | System | Fully available — Eloquent/prepared throughout; raw `$where` removed | Retain |
| Platform/Auth/RBAC/Audit | (new) | Security headers | Browser hardening | All | Partially available — `SecurityHeaders` middleware (X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy) but **no CSP, no HSTS** | Improve (add CSP + HSTS at edge) |
| Platform/Auth/RBAC/Audit | PP (`forms.status_agihan` numeric 0–20) + 2in1 dual encoding | Assignment status encoding | Drive assignment workflow | Agihan actors | **Conflicting (HIGH)** — `AgihanController` (single-step) + lawyer accept/reject write STRING labels (`'Ditawarkan'`/`'Diterima'`/`'Ditolak'`); the 3-tier spine + `AgihanService` write NUMERIC codes into the SAME `forms.status_agihan`. Reconciled only at read-time via `StatusAgihan::LEGACY_STRING_MAP`; no write normalisation, no migration | Merge (converge to ONE encoding; pick one assignment front-end) |
| Platform/Auth/RBAC/Audit | (2in1 two agihan front-ends) | Case→lawyer assignment | Assign cases | Staff | **Duplicated/Conflicting (HIGH)** — `AgihanController` single-step AND `AgihanSpineController` 3-tier both mutate the same case, no guard preventing clobber; spine→lawyer offer hand-off **broken** (`PeguamController::tawaran` filters literal `'Ditawarkan'`, never sees numeric `1` offers) | Merge (retire single-step; fix tawaran to use `bucketValues`) |
| Platform/Auth/RBAC/Audit | PP (7-day auto-reassign `cron_lebih_masa.php`, random PPUU) | Lebih-Masa auto re-assignment | No case stuck on a non-responsive lawyer | System | Partially available — `LebihMasaService` + `agihan:lebih-masa` command + daily scheduler EXISTS (status `7`); but map 05 flags status `7` "defined but never produced" and `9` (Ditolak Pengarah) is a dead-end with no UI exit | Improve (verify scheduler runs; add `9` recovery screen) |
| Platform/Auth/RBAC/Audit | PP (name-string case↔lawyer linkage) | Case-to-lawyer ownership | Link cases to lawyers | System | Conflicting/Unclear — ownership, workload, redistribution, drop-guard all match on `nama_peguam` STRING not `kp_peguam`/id; fragile on duplicate/renamed names | Improve (key on id) |
| Platform/Auth/RBAC/Audit | (new) | Missing FK constraints across links | Referential integrity | System | Partially available — some FKs added (`sejarah_*`→forms, KN tree, cawangan/bilik) but many links are plain indexed cols (`khidmat_nasihat.id_forms/id_temu_janji`, `temu_janji.id_khidmat_nasihat/id_pegawai_kn`, `uploaded_files.*`, `sejarah_ppuu.id_kes`, `laporan_kes.id_kes` varchar) | Improve (add FKs where types allow) |
| Platform/Auth/RBAC/Audit | (charset/collation) | DB consistency | Reliable joins/LIKE | System | Conflicting (LOW) — `ref_cuti` is latin1, `posters` is utf8mb4_0900_ai_ci, rest utf8mb4_general_ci → collation-mismatch risk | Improve (normalise collation) |
| Platform/Auth/RBAC/Audit | RK (`items` stub, `ref_lokasi_berguam`, `butiran_peguam_panel` v1) | Dead/near-dead tables | none | — | Obsolete — `items` (no controller), `butiran_peguam_panel` v1 (superseded by `_2`), `ref_lokasi_berguam` (lookup only); `jobs`/`failed_jobs` present but no queue used | Remove (drop `items` + v1 after verify) |
| Platform/Auth/RBAC/Audit | (`_3..6` / `sejarah_ppuu` reconstructed) | Lawyer detail + spine tables | Lawyer profile + assignment history | System | Unclear — 2in1 migrations claim these were "reconstructed from source code (never dumped)", but `sistemspk.sql` DOES contain `butiran_peguam_panel_3..6` + `sejarah_ppuu` → possible column drift | Improve (reconcile Blueprint shapes against the real dump) |
| Platform/Auth/RBAC/Audit | (stale planning docs) | Project documentation | Onboarding/scope clarity | Team | Conflicting — `project.md`/`domain.md` describe a bare scaffold while `parity-backlog.md`/overview describe a near-complete app; merge-plan open questions (data migration, single/multi-branch, bilingual) unresolved | Improve (refresh stale docs; close open questions) |
| Platform/Auth/RBAC/Audit | PP (30-min idle timeout) | Session idle logout | Security | All | Partially available — legacy was server-side (PP) / client-only (RK); 2in1 idle auto-logout is a P3 item, not confirmed built | Improve |

---

## Headline conflicts / duplications / gaps (summary)

1. **CONFLICTING (HIGH) — `forms.status_agihan` dual encoding.** Two assignment front-ends (`AgihanController` string vs `AgihanSpineController` numeric) write incompatible encodings into one column; reconciled only at read-time. → Merge.
2. **DUPLICATED + broken hand-off (HIGH) — spine→lawyer offer.** `PeguamController::tawaran` filters the literal string `'Ditawarkan'`, so cases offered through the numeric spine (`1`) never reach the lawyer's offer list. → Merge/fix.
3. **CONFLICTING (HIGH) — `awam` role unprotected.** Seeded by migration, absent from `RolePermissionSeeder::ROLES` + `RoleController::SYSTEM_ROLES`; renamable/deletable → would break the citizen portal gate. → Improve.
4. **MISSING — public application-status checker** (PP `semak.php` / RK) on both lawyer and citizen sides. → Improve.
5. **PARTIAL (HIGH) — KN TIDAK_HADIR hanging state** + no payment confirmation + PKN-reject leaves KN appointment-less. → Improve.
6. **PARTIAL — mediation (pengantaraan) write-path not fully ported** → several wide-export columns degrade to `-Tiada Maklumat-`. → Improve.
7. **PARTIAL — credential delivery gap**: approved lawyer's temp password shown once in a flash, never emailed; no email on registration. → Improve.
8. **INCONSISTENT — branch isolation**: `CawanganScope` only on `forms`; KN/janji-temu/OYD/lawyer tables scoped manually in 3 places. → Improve.
9. **CONFLICTING — SLA `khidmat` end-date** (`tarikh_persetujuan` in `SlaMatrix` vs `tarikh_selesai` in `KpiController`). → Improve.
10. **CHAT security debt** — 5 plaintext secrets to rotate, shared Basic creds across repos, open `/docs`, reflected headers, dead `news_today` tool in cloud. → Improve/Remove.
11. **OBSOLETE/DEAD** — `items`, `butiran_peguam_panel` v1, `ref_lokasi_berguam`, unused jobs/queue tables; legacy backdoor/debug files not ported. → Remove (confirm).
12. **UNCLEAR — `_3..6`/`sejarah_ppuu` reconstructed** despite existing in `sistemspk.sql` dump → reconcile shapes for column drift. → Improve.
13. **NEW VALUE (keep)** — KN "Buka Kes" advisory→litigation bridge and unified bcrypt auth/RBAC are net-new improvements over every legacy system. → Retain.
