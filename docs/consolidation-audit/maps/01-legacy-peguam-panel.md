# Legacy System Map — `sistem-peguam-panel` (Lawyer Panel / JBG)

> Read-only audit. Source: `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/sistem-peguam-panel`
> Stack: procedural PHP 8.x, native sessions, MySQLi (`butiran_peguam_panel_*` + `forms`), some PDO, FPDF 1.82 + Dompdf 3.1 for print, PHPMailer 6 SMTP (Gmail). No framework, no router — every `.php` is a directly-reachable entrypoint. DB name: **`sistemspk`**.
> Owner agency: Jabatan Bantuan Guaman (JBG), BHEUU. "BPPPG" = Bahagian Peguam Panel & Pendamping Guaman.

This system manages the **lawyer panel lifecycle**: lawyer self-registration (`daftar`) → multi-tier approval → case assignment to lawyers (`agihan`) → withdrawal (`tarik diri`) → beneficiary (OYD) tracking. It is the counterpart to the case-record system (`sistem-rekod-kes`), sharing the `forms` table (a case = one OYD application).

---

## 1. Roles (`users_peguam_panel_2.peranan`)

Single users table for BOTH lawyers and JBG officers. `peranan` (int) drives navbar, dashboard include, and access:

| peranan | Label (navbar `$namaPeranan`) | Who | Login table check |
|--------|-------------------------------|-----|-------------------|
| 0 | Pembantu Tadbir (P/O) — Admin PT | JBG clerk; first reviewer of new lawyer applications | `users_peguam_panel_2` |
| 1 | Peguam Panel | The panel lawyer (citizen-facing) | same |
| 2 | Penolong Pegawai Undang-Undang (PPUU) | Assigns/redistributes cases to lawyers | same |
| 3 | Pengarah | Director — recommends (sokongan) approvals | same |
| 4 | Ketua Pengarah (KP) | Director-General — final approval | same |
| 5 | IT UTM JBG (superadmin) | Maintains officer + lawyer accounts | same |

Auth is split-brain: lawyers and officers all sit in `users_peguam_panel_2`, differentiated only by `peranan`. There is a special account `nama = 'PPUU SA'` explicitly excluded from assignment dropdowns/queries.

---

## 2. Entry points (real `.php` screens) and what they do

### Public / unauthenticated
| File | Purpose |
|------|---------|
| `index.php` | Login page (split card UI). Includes `query/checklogin.php`. Captcha (6-digit) via `$_SESSION['captcha_login']`. Links to daftar / set-semula / semak. |
| `daftar.php` | Public lawyer **registration form** (multi-tab: peribadi, sijil CLP/CSO, firma, insurans, bank, bidang kes, doc upload). POSTs to `query/daftarNew.php`. Own captcha `captcha_daftar`. |
| `register.php` | Older/alt registration entry (legacy). |
| `set-semula.php` | "Lupa Kata Laluan" — user enters ID + email; POSTs `query/set-semulapassword.php`. |
| `semak.php` | "Semak Status Permohonan" — public application-status lookup; POSTs `query/checkstatus.php`. |
| `verify.php` | AJAX captcha verify + refresh (login & daftar). Returns JSON `{valid:...}` / `{captcha:...}`. |
| `logout.php` | Destroys session, redirects to `index.php`. |

### Authenticated shell
| File | Purpose |
|------|---------|
| `utama.php` | Dashboard. Loads `navbar.php`, then `switch($peranan)` includes `utama/{adminpt,peguampanel,ppuu,pengarah,ketuapengarah,superadmin}.php`. |
| `navbar.php` | Role-based top nav + **notification badge counts** (computed via raw `forms`/`butiran_*` COUNT queries). Also enforces 30-min idle timeout. |
| `katalaluan.php` | Change own password; role variants in `katalaluan/{adminpt,peguampanel,pengarah,ppuu,ketuapengarah,superadmin}.php`. |
| `footer.php`, `page.php`, `info.php`, `phpinfo.php`, `test-emel.php` | Boilerplate / debug (note: `phpinfo.php`, `test-emel.php`, `debug.log` shipped — see weaknesses). |

### Lawyer registration & approval (Admin PT / Pengarah / KP)
| File | Purpose |
|------|---------|
| `senarai-permohonan-baru.php` | List of new lawyer applications (`butiran_peguam_panel_2.permohonan_status`). |
| `permohonan-baru.php` + `permohonanbaru/{adminpt,ketuapengarah,pengarah}.php` | Application detail/review by role. Uses `query/ppnew.php` (load) and `query/ptHandler.php` / `query/ppHandler*` (actions). |
| `senarai-peguam-panel.php`, `senarai-permohonan-peguam-panel.php` | Master list of registered lawyers. |
| `selenggara-peguampanel.php` / `-detail.php` | Maintain lawyer record (activate/deactivate, reset password). Actions → `query/selenggaraPengguna.php`. |
| `selenggara-pegjbg.php` / `-detail.php` | Superadmin: maintain JBG officer accounts. Actions → `query/selenggaraPengguna.php`. |
| `status-peguam-detail.php` | Lawyer status detail view. |
| `senarai-kemaskini-kes.php`, `maklumat-kemaskini-kes.php`, `profil-kemaskinibidangkes.php`, `profilkemaskinipilihan.php` | Lawyer **bidang kes (specialisation) update** request + approval flow. Actions → `query/kemaskini.php`, `query/profil-kemaskinibidangkes.php`. |
| `profil.php`, `profilUpdate.php` | Lawyer self-profile view/edit. |

### Case assignment — "Agihan" (PPUU / Pengarah / KP)
| File | Purpose |
|------|---------|
| `senarai-pengagihan-baru.php` | New cases awaiting distribution. |
| `senarai-pengagihan-semasa.php` | Currently-assigned cases. |
| `senarai-pengagihan-semula.php` | Cases needing **re-distribution** (rejected/timed-out/withdrawn). |
| `sejarah-pengagihan.php`, `maklumat-sejarah-agihan.php`, `sejarah-penugasan-keseluruhan.php` | Assignment history. |
| `maklumat-agihan-baru.php`, `agihanbaru/{ppuu,pengarah,ketuapengarah}.php` | Agihan-baru detail screens per role. Load via `query/agihanbaru_ppuu.php`, `query/agihanbaru_pengarah.php`, `query/maklumatagihanbaru.php`. |
| `maklumat-agihan-semasa.php`, `agihansemasa/{ppuu,pengarah,ketuapengarah,oydinfo}.php` | Current assignment screens. Load via `query/maklumatagihansemasa.php`. |
| `maklumat-agihan-semula.php`, `agihansemula/{ppuu,pengarah,ketuapengarah}.php` | Re-assignment screens. Load via `query/agihansemula.php`, `query/maklumatagihansemula.php`. |
| `formAgihanBaru.php`, `formAgihanBaruPengarah.php`, `formAgihanSemasa.php`, `formAgihanSemula.php` | Action forms (the big workflow controllers). Mirror handlers in `query/formAgihanBaru.php`, `query/formAgihanBaruKP.php`, `query/formAgihanSemula.php`. |
| `maklumat-pemilihan-peguam.php`, `maklumat-penugasan*.php`, `kendaliPeguam.php`, `otherpeguampanel.php`, `filter_peguam.php` | Lawyer-matching / selection (rules-based: cawangan + jenis kes + valid sijil + workload < 5). |
| `senarai-penugasan.php` (lawyer view), `serahan_semula.php` | Lawyer's offered assignments; "serah semula ke JBG". Uses `query/tabsp.php`. |
| `senarai-beban-tugas.php`, `maklumat-beban-tugas.php`, `query/checkbebantugas.php` | Workload report (count of `forms.status_agihan='2'` per lawyer). |

### Withdrawal — "Tarik Diri" (lawyer → PPUU → Pengarah → KP)
| File | Purpose |
|------|---------|
| `senarai-permohonan-tarikdiri.php` | List of withdrawal requests. |
| `tarik_diri.php`, `tarikdiri/{peguampanel,ppuu,pengarah,ketuapengarah}.php` | Withdrawal screens per role. |
| `query/tarikdiri.php` (103 KB — core controller) | All withdrawal state transitions + emails + **Dompdf "surat batal penugasan"** generation. |
| `query/semaktarikdiri.php` | Loads a withdrawal request for review. |
| `tarikdiri/deletefile.php`, `uploads/perintahMahkamah/deletefile.php` | Delete attached PDFs. |

### OYD (Orang Yang Dibantu / beneficiaries)
| File | Purpose |
|------|---------|
| `Senarai_Orang_Yang_Dibantu.php` | Lawyer's list of assigned beneficiaries (their cases). |
| `query/senaraioyd.php`, `query/oydinfo.php`, `agihansemasa/oydinfo.php` | OYD detail load (joins `forms` + `butiran_oyd` + `sejarah_*`). |

### Reports / Print (FPDF + Dompdf)
| File | Output |
|------|--------|
| `cetak.php` (a.k.a. `cetakanRingkasanPemohon.php`) | FPDF — applicant summary (`PDF extends FPDF`, custom `Row()` multicell). |
| `cetakanKelulusanPemohon.php` | FPDF — applicant approval letter. |
| `cetakanMaklumatPermohonan.php` | FPDF — full application details. |
| `cetakanLaporanKesMahkamah.php` | FPDF — court-case report. |
| `query/tarikdiri.php` | Dompdf — "Surat Batal Penugasan" (cancellation letter), saved to `uploads/surat/surat_batal_penugasan_{kp}_{ts}.pdf` and emailed. |

### Cron / batch
| File | Purpose |
|------|---------|
| `cron_lebih_masa.php` | Auto-reassign: cases with `forms.status_agihan='1'` and `tarikh_penugasan_peguam_panel` > 7 days old and **no** `sejarah_ppuu` row → insert `sejarah_ppuu` with random active PPUU and `statusAgihan='7'`. (Random PPUU via `array_rand`.) |
| `phpmailer/cron.php` | Standalone PHPMailer cron (reminder mailer). |

### Misc / utilities
`func/string_control_function.php`, `getMalayDate.php`, `getMalayDate.php`, `check_kp.php` (IC validation), `delete.php`, `deletefiles.php`, `fetch_sejarah_pp.php`, `fetch_sejarah_pp_2.php`, `tabs*.php`, `contoh.html`, `Readme.txt`.

---

## 3. Auth, session & access control

- **Login** (`query/checklogin.php`): `SELECT id, id_peguam_panel, kata_laluan, statusAktif FROM users_peguam_panel_2 WHERE id_peguam_panel = ?`. Password compared with **`$password === $kata_laluan`** (plaintext, no hashing). On success sets `$_SESSION['user_id']`, updates `last_login`, `session_regenerate_id(true)`.
- **Rate limit**: session counter `login_attempts`; after `maxAttempts=5` adds `sleep(3)`. Captcha regenerated on each failure.
- **statusAktif gate**: `statusAktif='0'` blocks login ("Akaun tidak aktif").
- **Role enforcement**: `query/auth.php::allowRole($roles)` — redirects to `index.php` if no session, `unauthorized.php` if `$_SESSION['peranan']` not allowed. **NOTE: it reads `$_SESSION['peranan']` but `checklogin.php` never sets it** — peranan is re-fetched per page from DB (`utama.php`, `navbar.php`), so `allowRole` is effectively broken/inconsistent and most pages rely on ad-hoc `WHERE ... peranan = N` query filters instead of central middleware.
- **Idle timeout**: `navbar.php` — 30 min (`$timeout_duration=1800`), redirects `index.php?expired=1`.
- **Session state used**: `user_id`, `peranan` (intermittently), `captcha_login`, `captcha_daftar`, `login_attempts`, `last_activity`, `swal` (flash).

---

## 4. Status fields and transitions

### A. Lawyer registration — `butiran_peguam_panel_2.permohonan_status`
(see `query/ppinfo.php` lines 416-433, `query/ppnew.php` 396-413, `query/checkstatus.php`)

| code | meaning |
|------|---------|
| 0 | BARU (in Admin PT review) |
| 1 | DALAM PROSES SEMAKAN PENGARAH BPPPG |
| 2 | DILULUSKAN (approved — lawyer now usable for assignment) |
| 3 | TIDAK DILULUSKAN |
| 4 | DIBATALKAN |
| 5 | DALAM PROSES SEMAKAN & KELULUSAN KETUA PENGARAH JBG |

**Flow:** daftar inserts `0` → PT "submit" sets `1` (`query/ptHandler.php`) → Pengarah recommends → KP sets `5` then final `2`/`3` (`query/kemaskini.php`, `query/ppnew.php`). On `2`, the lawyer row is **inserted into `users_peguam_panel_2`** (login enabled) and joins begin working. Related: `sokonganPengarah` (0=DISYORKAN, 1=TIDAK DISYORKAN), `ulasan_sokonganPengarah`, `ulasan_keputusanKP`.

### B. Lawyer specialisation/cert update — `butiran_peguam_panel_6.checkbox_value_status`
(see `query/kemaskini.php`, `query/profil-kemaskinibidangkes.php`)

| code | meaning |
|------|---------|
| 1 | Baru dimohon (pending) |
| 2 | Diluluskan (active specialisation — used in assignment matching) |
| 3 / 4 | In Pengarah / kemaskini review states |
| 6 | Diganti/lama (superseded on update) |
| 7 / 9 | In KP review states |

`maklumat-kemaskini-kes` sets `permohonan_status='5'` (to KP) when a lawyer requests bidang-kes change; on approval old rows → `6`, new → `2`.

### C. Case assignment — `forms.status_agihan` / `sejarah_ppuu.statusAgihan`
(definitive map from `query/agihanbaru_ppuu.php` 150-179, `formAgihanBaru.php`, `formAgihanBaruKP.php`, `formAgihanSemula.php`, `navbar.php`)

| code | meaning |
|------|---------|
| 0 | DALAM PROSES KELULUSAN PENGARAH (new case, awaiting director) |
| 1 | DITAWARKAN (offered to lawyer) |
| 2 | DITERIMA OLEH PP (lawyer accepted — active workload) |
| 3 | DITOLAK OLEH PP (lawyer rejected) |
| 4 | PPUU AGIH SEMULA (to re-distribute) |
| 5 | SELESAI (case closed) |
| 6 | (tarik-diri intermediate — "DALAM PROSES TARIK DIRI") |
| 7 | (auto-assigned / agih semula trigger; used by cron + redistribution) |
| 8 | DITERIMA OLEH PENGARAH (director accepted, awaiting PPUU pick lawyer) |
| 9 | DITOLAK OLEH PENGARAH |
| 10 | DALAM PROSES SOKONGAN PENGARAH |
| 11 | (director-support state in agihan semula) |
| 12 | TARIK DIRI MEWAKILI OYD (withdrawal submitted by lawyer) |
| 13 | DALAM PROSES KELULUSAN KETUA PENGARAH |
| 14 | (KP-stage in agihan baru/semula) |
| 15 | (KP-approval state in agihan semula) |
| 16 | Tarik diri — PPUU reviewed, sent to Pengarah |
| 17 | Tarik diri — Pengarah reviewed, sent to KP |
| 20 | PP TELAH MENINGGAL DUNIA (lawyer deceased → forced reassignment) |

**Assignment happy path:** case enters `0` → Pengarah accepts (`statusMohonPP='0'`) → `8` + inserts `sejarah_ppuu` + emails PPUU → PPUU picks lawyer → `1` (DITAWARKAN) + emails lawyer → lawyer accepts → `2`. Director/KP support path runs `10`→`11`→`13`/`15`/`14`. Rejections/timeouts → `3`/`4` → back to PPUU re-distribution (`senarai-pengagihan-semula`).

### D. Withdrawal — `sejarah_peguam_panel.status_agihan` (+ `forms`)
(from `query/tarikdiri.php`)

| action | sets |
|--------|------|
| Lawyer "hantar" | `sejarah_peguam_panel.status_agihan='12'`, captures `pilihanTarikDiri`, `alasan`, `tarikhNextBicaraKes`, attaches `akuanTarikDiri` PDF |
| PPUU "hantarPPUU" | `status='16'` (to Pengarah) |
| Pengarah "hantarPengarah" | `status='17'` (to KP) |
| KP "hantarKP" (approve) | `sejarah_peguam_panel.status='6'` (tarik diri mewakili OYD), `forms.status_agihan='4'` (re-distribute), generates Dompdf cancellation letter, emails lawyer |
| KP reject | `forms.status_agihan='2'` (lawyer keeps case) |

`pilihanTarikDiri` distinguishes withdrawal types (e.g. withdraw representing OYD vs. case completed). `permohonan_kali` counts withdrawal attempts.

### E. Account active status — `users_peguam_panel_2.statusAktif` (`0`/`1`)
Deactivation reasons (`sebabTidakAktif`): "Dalam Tindakan JK Disiplin", "Telah Meninggal Dunia", "Lain-lain". Deactivating a deceased active lawyer triggers a **cascade** in `query/selenggaraPengguna.php`: all their `forms.status_agihan='2'` cases → `'4'`, history rows logged with `status_agihan='20'`, new `sejarah_ppuu` aktif row inserted, notification email to Pengarah/PPUU/KP.

---

## 5. Database tables (DB `sistemspk`) and key columns

| Table | Role | Key columns observed |
|-------|------|----------------------|
| `users_peguam_panel_2` | **Accounts** (lawyers + officers) | `id`(PK,int), `id_peguam_panel`(login id = IC/kpBaru), `nama`, `emel`, `kata_laluan`(**plaintext**), `peranan`(0-5), `statusAktif`(0/1), `sebabTidakAktif`, `sebabTidakAktif_text`, `sebabTidakAktif_date`, `last_login`, `createdBy/Date`, `modifiedBy/Date` |
| `butiran_peguam_panel_2` | Lawyer application — personal | `kpBaru`(IC, join key), `kpLama`, `namaPeguam`, `jantina`, `noTelBimbit`, `emelPeguam`, `kelulusanAkademik`, `tahunPengalaman`, `tahunPengalamanSyarie`, `tarikhDiterimaMasuk(Syarie)`, `bilanganKes`, `keteranganKes`, `permohonan_status`(0-5), `tarikhMohon`, `sokonganPengarah`, `ulasan_sokonganPengarah`, `tarikh_sokonganPengarah`, `ulasan_keputusanKP`, `tarikh_keputusanKP`, `sebabBatal`, `statusAktif`, `sebabTidakAktif` |
| `butiran_peguam_panel_3` | Lawyer certs / locations | `kpBaru`, `clpNumber/Mula/Akhir` (Sijil Amalan Guaman), `csoNumber1..5`+`csoNMula/Akhir/Tauliah` (Syarie certs), `lokasiBerguam1..5`+`_status`, `ybgk_kelulusan/tarikhLulus_A/B/daftar`, `adr_penimbangtara`, `adr_pengantara`, `sijilAhli_*`, `sijilAkreditasi_*`, `eVendor_daftar/ID` |
| `butiran_peguam_panel_4` | Lawyer firm + insurance | `kpBaru`, `namaFirma`, `namaInsurans`, `noPolisi`, `amaunPerlindungan`, `polisiMula/Akhir`, `alamatFirma1-3`, `poskodFirma`, `bandarFirma`, `negeriFirma`, `noTelFirma`, `noFaksFirma` |
| `butiran_peguam_panel_5` | Lawyer bank | `kpBaru`, `namaBank`, `noAkaunBank`, `alamatBank1-3`, `poskodBank`, `bandarBank`, `negeriBank` |
| `butiran_peguam_panel_6` | Lawyer specialisations (bidang kes) | `kpBaru`, `category`(JEN/SIV/SYA/PG), `checkbox_value`(deskripsi from `ref_kes`), `checkbox_value_status`(1/2/3/4/6/7/8/9) |
| `forms` | **Shared case record** (OYD application) | `id`(PK), `nama`(OYD), `nokp`, `no_fail`, `jenis_kes`(→`ref_kes.id_kes`), `kategori_kes`, `jenis_kategori`, `cawangan`, `keputusan_menteri`, `nama_pegawai_yang_dapat_kes`(lawyer name), `tarikh_penugasan_peguam_panel`, `status_agihan`(0-20), `keputusan`, `status`, `justifikasi_rujuk_pp`, `justifikasi_lain_rujuk_pp`, `tarikh_mohon_khidmat_pp`, court fields (`no_mahkamah`,`nama_mahkamah`,`nama_pihak`,`nama_responden`,`cara_selesai`,`tarikh_perintah`,`tarikh_pemfailan_kes`,...), `kaedah_pemakluman`, `modifiedBy/Date` |
| `sejarah_ppuu` | Assignment history (PPUU layer) | `id`, `id_kes`(→forms.id), `idPPUU`, `nama_peguampanel`, `kpPP`, `cawangan_peguampanel`, `pilihan_Agihan`, `statusAgihan`, `statusMohonPP`(0=diterima/1=ditolak), `status_sokonganPengarah`, `ulasanPengarah`, `status_KP`, `ulasanKP`, `tarikh_diberiAgihan`, `tarikh_syorPPUU`, `tarikh_PengarahKemaskini`, `tarikh_KPKemaskini`, `sebabTolakTugasan`, `ulasanPPUU`, `status_rekod`(aktif/tutup), `createdBy/Date`, `modifiedBy/Date` |
| `sejarah_peguam_panel` | Lawyer-side case history / withdrawals | `id`, `id_kes`, `nama_pp_lama`, `kp_pp_lama`, `tarikh_penugasan`, `status_agihan`, `pilihanTarikDiri`, `alasan`, `tarikhNextBicaraKes`, `permohonan_kali`, `status_rekod`, `ulasanPPUU`, `ulasanPengarah`, `ulasanKetuaPengarah`, `createdBy/Date` |
| `sejarah_pegawai` | Case officer-change history (shared) | `id_kes`, `nama_pegawai_lama`, `tarikh_kemaskini`, `dikemaskini_oleh` |
| `uploaded_files` | Doc registry | `kpBaru`, `nama`, `file_name`(`{kp}_{type}.pdf`), `file_path`, `file_type`, `uploaded_at` |
| `audit_trail` | Audit log | `table_name`, `record_id`, `action_type`(INSERT/UPDATE), `old_value`, `new_value`, `remarks`, `modified_by` |
| `butiran_oyd` | Beneficiary contact | `kp_oyd`, `notelefon_oyd`, `email_oyd` |
| `laporan_kes` | Court-report reference list | (lookup) |
| `pegawai_jbg` | Officer reference (legacy) | referenced in queries |
| `ref_kes` | Case-type taxonomy | `id`/`id_kes`, `jenis_kes`(JEN/SIV/SYA/PG), `deskripsi` |
| `ref_negeri` | States | `idNegeri`, `nama` |
| `ref_cawangan` | JBG branches | `cawangan`, `idNegeri` |
| `mahkamah_sivil`, `mahkamah_syariah` | Court lists | `negeri_mahkamah`, `nama_mahkamah` |

**Document types uploaded** (18, all PDF, naming `{kpBaru}_{type}.pdf`): kadPengenalan, insuransTR, penyataBank, profilFirma, sijilAkademik1-3, clp, cso1-5, senaraiKesKendali, certpenimbangtara, certpengantara, certkelulusanYBGK, sijilEvendor. Plus runtime: `akuanTarikDiri_{id}.pdf`, `perintahMahkamah_{id}.pdf`, `surat/surat_batal_penugasan_*.pdf`.

---

## 6. Notifications / Email (PHPMailer 6 via Gmail SMTP)

All transactional emails go through `smtp.gmail.com:587` STARTTLS using `EMAIL_USERNAME=aplikasi.jbg@bheuu.gov.my` (creds in `config.php`). Senders:

| Trigger | Recipients | File |
|---------|-----------|------|
| New lawyer registration submitted | (applicant on-screen only; "diproses dalam 21 hari") | `query/daftarNew.php` |
| Forgot-password / reset | applicant | `query/set-semulapassword.php` |
| New JBG officer created (with temp password + login URL) | officer | `query/selenggaraPengguna.php` (`tambahPegJBG`) |
| Officer/lawyer password regenerated | user | `query/selenggaraPengguna.php` (`janaNewPass`, `janaNewPassPP`) |
| Lawyer deceased → reassignment notice | Pengarah (CC PPUU, KP) | `query/selenggaraPengguna.php` |
| New case assignment ("Agihan Baru") | PPUU | `query/formAgihanBaru.php`, `formAgihanBaru.php` |
| Registration decision (lulus/tolak) | applicant | `query/kemaskini.php` |
| Withdrawal at each tier + cancellation letter PDF | lawyer / officers | `query/tarikdiri.php` |
| Reminder cron | (varies) | `phpmailer/cron.php` |

Email bodies embed the **internal app URL `http://10.19.211.207/sistem-peguam-panel/...`** and helpdesk `utm@jbg.gov.my`. On-screen feedback uses `alert()` + SweetAlert flash (`$_SESSION['swal']`).

---

## 7. Reports / Print inventory
- FPDF 1.82 (`fpdf182/`) custom `PDF extends FPDF` with `Row()/NbLines()` multicell: `cetak.php`, `cetakanKelulusanPemohon.php`, `cetakanMaklumatPermohonan.php`, `cetakanLaporanKesMahkamah.php`.
- Dompdf 3.1 (composer): cancellation letter ("surat batal penugasan") in `query/tarikdiri.php`.
- Charting libs bundled (ApexCharts, Chart.js, ECharts) for dashboard but mostly template scaffolding.

---

## 8. Hardcoded secrets & exposed config (CRITICAL)

| Location | Secret |
|----------|--------|
| `config.php` | Gmail app password **`uhzlhiduqemtszdj`** for `aplikasi.jbg@bheuu.gov.my` (committed in repo) |
| `query/daftarNew.php:317`, `query/kemaskini.php:104`, `query/tabsp.php:212` | `new PDO('mysql:host=10.19.202.135;dbname=sistemspk','penggunaspkjbg','spkjbg24Abcd1234')` |
| `query/ptHandler.php:112`, `query/tarikdiri.php:197` | `new PDO('mysql:host=10.19.206.132;...','penggunaspkjbg','spkjbg24Abcd1234')` |
| `cetak.php`, `cetakanKelulusanPemohon.php`, `cetakanRingkasanPemohon.php` (commented) | `mysqli_connect("10.19.202.133"/"10.19.211.165",...,"spkjbg24Abcd1234","sistemspk")` |
| `db_connection.php` | local `root`/no-password (dev) — prod backed up as `db_connection.remote.php.bak` |
| Emails | internal IP `10.19.211.207` leaked in mail bodies |
| Shipped files | `phpinfo.php`, `test-emel.php`, `debug.log`, `info.php` present in webroot |

**Same DB credentials (`penggunaspkjbg`/`spkjbg24Abcd1234`) appear across 4 different host IPs** — multiple environments, all hardcoded, all committed.

---

## 9. Weaknesses / risks (for the rewrite)

1. **Plaintext passwords** — `kata_laluan` stored and compared raw (`$password === $kata_laluan`); reset/temp passwords generated as 9-char random and emailed in clear. No hashing anywhere.
2. **Hardcoded credentials** (DB ×4 hosts, Gmail app password) committed to source. Must rotate on migration.
3. **Broken central authz** — `allowRole()` checks `$_SESSION['peranan']` which is never set by login; security relies on scattered `WHERE peranan=N` query filters. No route middleware.
4. **No CSRF protection** on any state-changing POST (registration, approvals, assignment, withdrawal, account maintenance).
5. **SQL injection surface** — heavy use of string-interpolated `$conn->query("... '$userid' ...")` (e.g. `navbar.php` count queries, `ptHandler.php` `permohonan_status='1' WHERE kpBaru='$kpBaru'`, many `selenggaraPengguna`/`tarikdiri` spots) alongside prepared statements elsewhere. Inconsistent.
6. **No router / direct file access** — ~139 reachable `.php` files; debug files (`phpinfo.php`, `test-emel.php`) publicly served.
7. **Logic in views** — business state transitions embedded in page files and 40-100 KB `query/*.php` controllers (`tarikdiri.php` 103 KB, `formAgihanSemula.php` 85 KB). Duplicated load-logic (`ppinfo.php` vs `ppnew.php` near-identical 400-line blocks).
8. **Magic-number statuses** — `status_agihan` 0-20 and `permohonan_status` 0-5 are bare ints re-translated to labels independently in many files; mappings drift (e.g. `checkstatus.php` lacks code 5; `'6'` overloaded). No single source of truth.
9. **Fragile lawyer matching** — joins lawyer↔case on `forms.nama_pegawai_yang_dapat_kes = butiran_peguam_panel_2.namaPeguam` (name string, not ID); `UPPER(TRIM())` location matching against `ref_negeri.nama`. Brittle.
10. **Random reassignment** — `cron_lebih_masa.php` uses `array_rand` to pick PPUU; non-deterministic, no fairness/audit.
11. **Mixed DB drivers** — MySQLi (`$con/$conn`) + ad-hoc PDO connections to *different remote hosts* within the same request (e.g. `daftarNew.php` uploads to `10.19.202.135` while inserts go to the MySQLi connection). Split-write inconsistency / partial-failure risk.
12. **Transaction gaps** — file move + cross-host PDO insert happen inside a MySQLi `begin_transaction`/`commit` that cannot cover the PDO host; rollback won't undo uploaded files or remote invalid writes.
13. **File handling** — uploads keyed by IC (`{kpBaru}_type.pdf`) so re-registration overwrites; `mkdir(...,0777)`; `deletefiles.php` does `basename()` only (some path-traversal mitigation) but is GET-driven without CSRF.
14. **Single users table for lawyers + officers** complicates RBAC and uniqueness (login id = IC).

---

## 10. Consolidation notes (vs `sistem-rekod-kes` / 2in1 target)
- **`forms`** is the integration seam — shared OYD/case record between this lawyer-panel system and the case-record system. Any 2in1 model must unify `forms` + `butiran_peguam_panel_*` + `sejarah_*`.
- The 6-table `butiran_peguam_panel_2..6` split (personal/cert/firm/bank/specialisation) should collapse into a normalized lawyer profile + child tables in the rewrite.
- Status enums (sections 4A-4E) must be promoted to named constants/state machines.
- Pre-existing rewrite design docs already in legacy repo: `docs/superpowers/specs/2026-06-29-laravel-rewrite-design.md` and slice plans under `docs/superpowers/plans/` — cross-reference, do not duplicate.
