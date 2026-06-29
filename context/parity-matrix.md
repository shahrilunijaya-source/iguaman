# iGuaman 2in1 — Authoritative Parity Matrix

> Merge of two legacy raw-PHP systems (`sistem-peguam-panel` + `sistem-rekod-kes`) into one Laravel 13 + Blade app.
> This matrix is the source of truth for closing the parity gap. The current build was scaffolded from summaries, not source, so it is **lower-spec** than legacy. Build to this matrix.

## Summary

| Metric | Count |
|--------|-------|
| **Total features audited** | **246** (+1 placeholder `test/x` row, excluded) |
| ✅ Full (parity met or exceeded) | 44 |
| 🟡 Partial (thin/divergent coverage) | 86 |
| ❌ None (entirely missing) | 116 |

### By severity

| Severity | Count |
|----------|-------|
| 🔴 Critical | 45 |
| 🟠 High | 73 |
| 🟡 Medium | 57 |
| ⚪ Low | 71 |

### By module

| System | Module | Features | ❌ none | 🟡 partial | ✅ full |
|--------|--------|----------|---------|-----------|---------|
| sistem-peguam-panel | pp-agihan — Case Assignment to Lawyers | 46 | 23 | 19 | 4 |
| sistem-peguam-panel | pp-profil-daftar — Lawyer Profile/Registration/Withdrawal | 29 | 14 | 8 | 7 |
| sistem-peguam-panel | pp-selenggara-status — Officer/Lawyer Maintenance | 19 | 8 | 10 | 1 |
| sistem-peguam-panel | pp-kes-oyd — Specialization Update + OYD Listing | 20 | 12 | 4 | 4 |
| sistem-rekod-kes | rk-permohonan — Permohonan Bantuan Guaman (Borang 1) | 31 | 10 | 13 | 8 |
| sistem-rekod-kes | rk-pengantaraan — Mediation Reporting | 31 | 11 | 13 | 7 |
| sistem-rekod-kes | rk-statistik — Statistics Dashboards + PDF | 26 | 16 | 7 | 3 |
| sistem-rekod-kes | rk-export — Excel/CSV Exports | 22 | 14 | 5 | 3 |
| sistem-rekod-kes | rk-cuti-users-poster — Leave/Users/Poster/Password/Dashboards | 22 | 6 | 7 | 9 |

**The two highest-debt areas:** the entire `pp-agihan` 3-tier case-assignment approval chain (PPUU → Pengarah → Ketua Pengarah) and the `rk-statistik` per-branch SLA matrix/PDF reporting surface. The single most dangerous compliance gap is the **death-redistribution workflow** (`pp-selenggara-status`).

---

# System: sistem-peguam-panel

## Module: pp-agihan — Case Assignment to Lawyers (Agihan Baru / Semasa / Semula + Sejarah)

> The Laravel build COLLAPSES the legacy 3-tier assignment state machine into a single flat staff→offer→accept/reject step. None of the PPUU/Pengarah/Ketua Pengarah endorsement spine, `sejarah_ppuu` history, numeric status machine (0/8/10/11/13/14/15), or 4-bucket list views exist. Eloquent correctly eliminates the legacy SQLi pattern (security improvement, not a gap).

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| formAgihanBaru.php — Agihan Baru processor (submit1/kemaskini/hantar) | Drives status 0→8/0→9, PPUU PP-pick, hantar→10 + sejarah_ppuu + audit + emails | 🟡 partial | 🔴 critical | `AgihanController@store` does a SINGLE step (staff picks PP → 'Ditawarkan'); no PPUU sub-step, no status 8/9/10, no sejarah_ppuu |
| formAgihanBaruPengarah.php — Pengarah endorsement (sokong→13 / tidak-sokong→4) | Disokong→13 (to KP); Tidak→4 (PPUU re-assign) + permohonan_kali MAX+1 + history rotation | ❌ none | 🔴 critical | No route/controller endorses a CASE ASSIGNMENT; sokong logic in PermohonanPeguamController is for lawyer applications |
| formAgihanSemasa.php — Semasa processor (kemaskiniOYD upsert; 7→4 re-assign) | Upserts butiran_oyd enriched by nokp + re-assigns on Lebih Masa (7→4) | 🟡 partial | 🟠 high | OydController is standalone CRUD; PeguamController has 7-day const but NO automated Lebih-Masa re-assign |
| formAgihanSemula.php — Semula lifecycle (→11, Pengarah→15, KP→1/14) | 3-tier re-pick → endorse → approve/reject with emails + sejarah inserts | 🟡 partial | 🔴 critical | `AgihanController@store` treats reassign as ONE step (status 'S', re-offers); no 11/15/14 chain |
| senarai-pengagihan-baru.php — List (status IN 0,8,10,13; PPUU own-only) | Role-gated new-assignment bucket + static NOT-NULL guards | 🟡 partial | 🟠 high | kes/index lists ALL cases; no baru-agihan bucket, no PPUU-own scoping, no numeric-status bucket |
| senarai-pengagihan-semasa.php — List (status IN 1,2,5) | In-progress bucket Ditawarkan/Dikendalikan/Selesai | 🟡 partial | 🟡 medium | peguam/kes + kes/index cover parts; no staff-side Semasa bucket with role gating |
| senarai-pengagihan-semula.php — List (status IN 3,4,6,7,11,15) | Re-assignment work queue | ❌ none | 🟠 high | No list surfaces re-assignment states; rejected offers just set 'Ditolak' |
| sejarah-pengagihan.php — History list (status IN 5,6,9,14 + search) | Read-only assignment-outcome history | ❌ none | 🟡 medium | No dedicated assignment-history list; audit/index is generic change log |
| maklumat-agihan-baru.php — Detail host, switch(peranan)→ppuu/pengarah/kp | Role-routes ONE page into 3 role-specific editable forms | ❌ none | 🟠 high | Single kes/agihan.blade for all staff roles; no per-role assignment views |
| maklumat-agihan-semasa.php — Detail host (semasa, OYD, role-routed) | In-progress detail + OYD info + role partials | 🟡 partial | 🟡 medium | peguam/kes-show + kes/mahkamah cover some data; no role-routed semasa detail w/ embedded OYD |
| maklumat-agihan-semula.php — Detail host (semula, peranan 0–4) | Role-routes re-assignment detail (PPUU/Pengarah/KP) | ❌ none | 🟠 high | No re-assignment detail page; reassign reuses kes/agihan.blade |
| agihanbaru/ppuu.php — PPUU form (Pilihan A/B PP, tarikh syor, ulasan) | Pilihan A (own-cawangan) OR B (other-negeri) + syor date + ulasan → sejarah_ppuu | 🟡 partial | 🔴 critical | kes/agihan.blade is flat select from ALL lawyers; no Pilihan A/B, no negeri split, no syor/ulasan/sejarah_ppuu |
| agihanbaru/pengarah.php — Pengarah form (terima/tolak, assign PPUU, sokong, Print Surat) | Accept/reject + assign PPUU + sokong + Surat Tawaran | ❌ none | 🔴 critical | No per-role Pengarah view for case assignment; KeputusanController approves PERMOHONAN, not PP assignment |
| agihanbaru/ketuapengarah.php — KP final-approval form (status_KP, ulasanKP) | Final approval of new assignment (status 13 → decision) | ❌ none | 🔴 critical | No KP view approves a case assignment; KP-tier only in PermohonanPeguamController |
| agihansemasa/ppuu.php — PPUU in-progress (OYD + mahkamah + agih-semula trigger) | One PPUU view combining OYD/court/re-assign trigger | 🟡 partial | 🟡 medium | Current splits across Oyd/Mahkamah/Agihan controllers; no single screen, no overdue trigger |
| agihansemasa/pengarah.php — Pengarah in-progress view | Read/endorse view | ❌ none | ⚪ low | No dedicated equivalent; uses generic kes/show |
| agihansemasa/ketuapengarah.php — KP in-progress view | Read view | ❌ none | ⚪ low | No dedicated KP in-progress view; covered by kes/show |
| agihansemasa/oydinfo.php — Laporan Kes Mahkamah sub-form (repeating rows, close via tabsp) | Repeating party/status rows + tutup_fail with reasons | 🟡 partial | 🟠 high | MahkamahController adds ONE row at a time; multi-row batch + closure reasons absent |
| agihansemula/ppuu.php — PPUU re-assign form (old PP, Pilihan A/B, sebab_menolak, ulasan) | Shows old PP + sebab_menolak + Pilihan A/B + ulasan → sejarah_ppuu (→11) | 🟡 partial | 🟠 high | kes/agihan.blade shows current PP + single Alasan input + flat select; no Pilihan A/B, no →11 hand-off |
| agihansemula/pengarah.php — Pengarah re-assign endorsement (→15, Print Surat) | Endorses re-assignment (→15) + sejarah insert + Surat | ❌ none | 🟠 high | No Pengarah endorsement step on reassignment |
| agihansemula/ketuapengarah.php — KP re-assign decision (→1 / →14) | Diluluskan→1 DITAWARKAN / Tidak→14 TOLAK KE CAWANGAN | ❌ none | 🟠 high | No KP decision on reassignment; reassign goes straight to offer |
| serahan_semula.php — Serah Semula processor (status_agihan→5, 'Diserah Semula') | Tiny POST sets status_agihan=5 + status='Diserah Semula' | ❌ none | 🟡 medium | No 'Serah Semula' action; tutupFail/tolak are different states |
| Action — Pengarah: Terima + assign PPUU (0→8, sejarah_ppuu, email PPUU) | Creates sejarah_ppuu + audit + email | ❌ none | 🔴 critical | No 'assign to PPUU' action; PPUU never receives an assignment queue |
| Action — Pengarah: Tolak permohonan (0→9, email branch officers) | status=9 + sebab + emails Ketua Cawangan/Pengarah Negeri | 🟡 partial | 🟠 high | KeputusanController@tolak rejects PERMOHONAN, not status-9; no branch-officer emails |
| Action — PPUU: Pilih Peguam Pilihan 1/2 (sejarah_ppuu pilihan/cawangan/nama/kp) | Records pilihan_Agihan A/B + cawangan + kp + syor + ulasan | 🟡 partial | 🔴 critical | store writes only nama_pegawai_yang_dapat_kes from flat select; no Pilihan A/B columns |
| Action — PPUU: Hantar pilihan ke Pengarah (→10, email Pengarah) | Advances to status 10 + emails Pengarah | ❌ none | 🔴 critical | No submit-to-Pengarah step; staff assignment is immediate |
| Action — Pengarah: Simpan sokongan draft (no advance) | Save endorsement draft w/o advancing | ❌ none | 🟠 high | No draft-endorsement on assignment |
| Action — Pengarah: Disokong → KP (→13) | Advances to status 13 | ❌ none | 🔴 critical | No step advances a case assignment to KP |
| Action — Pengarah: Tidak Disokong → PPUU (→4, permohonan_kali MAX+1, rotation) | status→4, null PP, counter++, sejarah rotation, email | ❌ none | 🔴 critical | No send-back-to-PPUU path with counter + rotation |
| Action — PPUU: Kemaskini OYD (upsert butiran_oyd enriched by nokp) | Upserts OYD enriched umur/jantina/agama/oku/bangsa/etnik from forms | 🟡 partial | 🟡 medium | OydController is standalone manual CRUD; no enrichment, not invoked from assignment |
| Action — PPUU: Agih Semula Lebih Masa (7→4, 16-col sejarah) | Re-assigns on overdue + 16-col sejarah_peguam_panel + rotation | ❌ none | 🟠 high | 7-day const is display-only; no automated overdue→re-assign |
| Action — PPUU: Re-pick PP Semula Simpan/Hantar (→11) | Save (Pilihan A/B) then hantar→11 | 🟡 partial | 🟠 high | One-shot re-offer; no →11 save-then-submit |
| Action — Pengarah: Endorse re-assignment (→15) | →15 + sejarah insert | ❌ none | 🟠 high | No Pengarah endorsement on reassignment |
| Action — KP: Diluluskan → Tawar Semula (status_KP=0, →1, 3 emails) | Approve → status 1 + tarikh_penugasan + emails Pengarah/PPUU/PP | 🟡 partial | 🔴 critical | Offer happens directly from staff; only 1 KesDitawarkanMail to lawyer |
| Action — KP: Tidak Diluluskan → Tolak ke Cawangan (status_KP=1, →14) | →14 TOLAK SEMULA KE CAWANGAN + sebab + sejarah | ❌ none | 🟠 high | No KP-reject-to-cawangan; status 14 only a filter value w/ no producing action |
| Action — Serah Semula kes (status_agihan='5', 'Diserah Semula') | Hand-back state | ❌ none | 🟡 medium | No Diserah Semula hand-back action |
| Print — Surat Tawaran/Penugasan | Client-side window.print of a modal | ✅ full | ⚪ low | **Exceeds legacy**: CetakanController@agihan + dompdf Surat Penugasan (server-side PDF) |
| Filter/search on senarai pages (no_fail, nama, nokp, status_agihan) | Per-list assignment filters | 🟡 partial | ⚪ low | kes/index filters cawangan/status/kategori/q; no status_agihan/no_fail filters (buckets don't exist) |
| Close file / Laporan Kes Mahkamah (oydinfo→tabsp, repeating rows + reasons) | Multi-row submit + tutup_fail reasons | 🟡 partial | 🟠 high | Single-row laporan add + tutupFail closure; no batch + no closure reason fields |
| Email — PHPMailer notifications (5 recipient roles) | Emails PPUU/Ketua Cawangan/Pengarah Negeri/Pengarah/PP across transitions | 🟡 partial | 🟠 high | Only KesDitawarkanMail to selected lawyer; no other transition emails |
| sejarah_ppuu table — PPUU assignment history (aktif/tutup rotation) | Full PPUU pick + sokong + KP keputusan history spine | ❌ none | 🔴 critical | No sejarah_ppuu model/table/usage; only sejarah_peguam_panel (4 cols) exists |
| Status-code machine on forms.status_agihan (0,8,9,10,11,13,14,15 + sub-status) | 14-state numeric assignment spine | 🟡 partial | 🔴 critical | String statuses for offer/accept/reject; intermediate 8/10/11/13/15 + sub-status fields not produced |
| permohonan_kali re-assignment counter (MAX+1 per id_kes) | Increments per re-assignment for audit | ❌ none | 🟡 medium | No permohonan_kali usage; reassign logs row w/o counter |
| PPUU role-scoped assignment queue (sejarah_ppuu.idPPUU = userid) | PPUU sees only own assigned cases | ❌ none | 🟠 high | PPUU is not a participant in case agihan at all |
| set-semula.php — Lupa Kata Laluan (MISFILED → Auth) | Forgot-password temp password | ✅ full | ⚪ low | Correctly out-of-scope; PasswordResetController covers reset (improved) |
| SQLi-prone raw UPDATEs (formAgihanSemula/serahan_semula) | String-interpolated WHERE id | ✅ full | ⚪ low | **Remediated**: Eloquent ->update() everywhere, bound params |

## Module: pp-profil-daftar — Lawyer Panel Profile, Registration & Withdrawal

> Thin slice ported: reduced public registration, a 3-tier application approval chain, profile VIEWING, offer accept/reject. Biggest gaps are structural — registration captures ~13 of ~70 fields with ZERO of 18 PDF uploads; the qualification/firma/bank/pengkhususan tables don't exist; the 4-tier "Tarik Diri Mewakili OYD" withdrawal workflow is absent; lawyer self-service edit doesn't exist. Build FIXES two legacy security findings (no hardcoded PDO, FormRequest validation).

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| daftar.php — 7-step registration wizard (~70 fields) | 7 sections + 18 PDF uploads | 🟡 partial | 🔴 critical | daftar.blade + PeguamDaftarRequest collect only 13 fields; no Kelayakan/Firma/Bank/Pengkhususan/Senarai Semak |
| Registration document uploads — 18 PDF doc types | kadPengenalan/sijil/clp/cso1-5/bank/firma/etc → uploaded_files | ❌ none | 🔴 critical | Store persists only text; no file handling; uploaded_files has no kpBaru column |
| Registration captcha (6-digit) + refresh/verify | captcha_daftar | ❌ none | 🟡 medium | Only throttle:6,1 + honeypot |
| check_kp.php — live AJAX IC-duplicate guard | check_kp.php?kp= → {exists} | 🟡 partial | ⚪ low | Caught only at submit via unique rule; no AJAX |
| register.php — dead legacy alt form | Posts to non-existent daftarNew.php | ❌ none | ⚪ low | Intentional non-port |
| profil.php — logged-in PP self-service profile EDIT | Edit butiran/kelayakan/bidang/firma/bank + eVendor | 🟡 partial | 🔴 critical | profil.blade is READ-ONLY; no editable form/route for lawyer to update own record |
| profilUpdate.php — profile UPDATE handler | Updates butiran_2/3/4/5 + re-uploads PDFs | ❌ none | 🔴 critical | No equivalent controller/route |
| profilkemaskinipilihan.php — duplicate profile/bidang variant | Near-duplicate view | ❌ none | ⚪ low | Moot (no profile-edit surface); ship ONE component when built |
| Bidang Pengkhususan add/drop request (updateKes/addNewKes, gated) | status 3 gugur / 4 tambah + edit-mode toggle | ❌ none | 🟠 high | No butiran_peguam_panel_6 table, no AJAX, no UI |
| Bidang-kes Pengarah approval (pengarahKemaskini) | Approve/reject add/drop + ulasanPengarah | ❌ none | 🟠 high | No pengkhususan table or director-review screen |
| Two divergent bidang-kes handlers (consolidation) | Root vs query/ copies | ❌ none | ⚪ low | Feature absent; build single handler |
| ppinfo.php — profile loader (JOIN _2/3/4/5/6, 18 doc flags, label maps) | Full qualification/firma/bank/pengkhususan + doc flags | 🟡 partial | 🟠 high | Loads only v1 butiran into _butiran; CSO capped 1-3 (legacy 1-5), no _4/_5/_6, no doc map |
| tarik_diri.php — Tarik Diri Mewakili OYD router | Loads forms+laporan_kes+ref_kes, routes by peranan | ❌ none | 🔴 critical | No case-level withdrawal route; permohonan-peguam.tarik is APPLICATION cancellation |
| tarikdiri/peguampanel.php — PP withdrawal form (9 reasons, Section 24) | pilihanTarikDiri + ulasan + tarikhNextBicaraKes + akuanTarikDiri PDF | ❌ none | 🔴 critical | No PP withdrawal form; sejarah_peguam_panel has no withdrawal columns |
| Withdrawal approval chain — PPUU/Pengarah/KP forms | hantarPPUU/Pengarah/KP, advance status, email next tier, KP finalize | ❌ none | 🔴 critical | No withdrawal-review screens, no status 12/16/17, no ulasan columns, no tier emails |
| senarai-permohonan-tarikdiri.php — withdrawal queue (status IN 12,16,17) | Join forms+sejarah_ppuu+sejarah_peguam_panel | ❌ none | 🟠 high | No withdrawal list/route; sejarah_ppuu table absent |
| senarai-permohonan-peguam-panel.php — registration queue | permohonan_status filter + row→review | ✅ full | ⚪ low | permohonan-peguam/index + controller cover it |
| permohonan-baru.php + approval router (adminpt/pengarah/kp) | Role-routed 3-tier review | ✅ full | ⚪ low | show.blade exposes 3 tiers gated by hasRole |
| Registration approval — Pengarah sokongan (DISYORKAN/TIDAK + ulasan) | permohonan_status=5 | ✅ full | ⚪ low | @sokong sets sokonganPengarah + ulasan + tarikh |
| Registration approval — KP finalize (approve INSERTs login account) | Creates users_peguam_panel_2 login | ✅ full | 🟡 medium | @keputusan promotes to peguam_panel but does NOT create a users login row |
| Cancel registration (status=4 + tarikhBatal + sebabBatal) | Stage-gated cancel | 🟡 partial | 🟡 medium | @tarikDiri sets status=3 (not stage-gated, code/label differs) |
| Status code maps — permohonan_status 0–5 + checkbox_value_status 0-9 + sokongan | Full legacy maps | 🟡 partial | 🟠 high | Collapsed to 0/1/2/3; drops 4=DIBATALKAN, 5=SEMAKAN KP; checkbox_value_status not implemented |
| otherpeguampanel.php — AJAX get_details / get_list | Other-lawyer profile + cases for assignment | 🟡 partial | ⚪ low | Covered by peguam-panel.show + agihan via full pages; no JSON endpoint |
| deletefiles.php — per-document PDF delete | Trash buttons | ❌ none | 🟡 medium | No lawyer-document storage exists |
| SECURITY — remove hardcoded remote PDO | 10.19.202.135 plaintext creds | ✅ full | ⚪ low | **Resolved**: Eloquent + single configured connection |
| BUG — undefined $lokasiBerguam5_status | NULL/notice | ✅ full | ⚪ low | Resolved by omission |
| BUG — profilUpdate omits cso4/cso5 | Cannot replace Syariah cert 4/5 | ❌ none | 🟡 medium | No profile update/CSO upload; avoid when built |
| Lawyer login provisioning on approval (users_peguam_panel_2 insert) | Creates login on KP approval | 🟡 partial | 🟠 high | promote() creates only peguam_panel master; no users login row → lawyer can't log in |
| Offer accept/reject (peguam terima/tolak) | Lawyer-side tawaran response | ✅ full | ⚪ low | @terima/@tolak + tawaran.blade, ownership-checked |

## Module: pp-selenggara-status — Maintenance of JBG Officers + Panel Lawyers

> Dedicated Super-Admin/IT maintenance surface for the active/inactive lifecycle — NONE of which is reproduced. No status-deactivation, no justifikasi (sebabTidakAktif), no temp-password generation, and CRITICALLY no death-redistribution transaction. peguam_panel has no statusAktif/sebabTidakAktif columns. Public directory + status checker absent.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| selenggara-pegjbg.php — JBG officer LIST (peranan-filtered, last_login, status) | Single-table officer-account list | 🟡 partial | 🟠 high | Two disjoint lists (pengguna/index over users, pegawai/index over pegawai_jbg); no last_login + status combined |
| Tambah Pengguna JBG (create + 9-char temp password + welcome email) | tambahPegJBG | 🟡 partial | 🟠 high | UserController@store sets must_change but admin types password; no temp gen, no welcome email, no nokp dupe check |
| selenggara-pegjbg-detail.php — Edit officer (status + justifikasi) | sebabTidakAktif (Keluar/Pencen/Meninggal/Lain) | 🟡 partial | 🟠 high | @update edits name/email/role/is_active; no Justifikasi block, no reason capture |
| Jana Kata Laluan Sementara — officer temp-password reset + email | janaNewPass | ❌ none | 🟠 high | No admin-initiated temp-password gen; PasswordReset is self-service only |
| selenggara-peguampanel.php — Panel lawyer status LIST (toggle AKTIF/TIDAK) | Status list for peranan=1 | 🟡 partial | 🟠 high | agihan/beban + peguam-panel/show expose no status column/toggle; peguam_panel has no statusAktif |
| selenggara-peguampanel-detail.php — Edit lawyer STATUS (deactivate w/ justifikasi) | JK Disiplin / Meninggal / Lain-lain | ❌ none | 🔴 critical | No route/method/view deactivates a lawyer; @update only edits name/firm/contact |
| **DEATH-REDISTRIBUTION WORKFLOW** (deactivate deceased → auto agih-semula + history + emails) | Per active case → status_agihan=4, null assignee, sejarah inserts, email Pengarah/PPUU/KP | ❌ none | 🔴 critical | No equivalent; @store does MANUAL one-case reassign, no bulk pool-return, no sejarah_ppuu, no fan-out |
| Jana Kata Laluan Sementara — lawyer temp-password reset + email | janaNewPassPP | ❌ none | 🟡 medium | No admin temp-password gen for lawyers |
| Semak Beban Tugas (AJAX caseload count, drives death-warning) | COUNT forms WHERE status_agihan='2' | 🟡 partial | ⚪ low | @beban computes counts; no on-deactivation warning (flow absent) |
| status-peguam-detail.php — Full READ-ONLY lawyer dossier | Joins _2/3/4/5/6 + uploaded_files | 🟡 partial | 🟠 high | _butiran shows only CSO 1-3; omits YBGK/ADR/sijil/eVendor; reads v1 not v2 |
| Sejarah Tarik Diri modal (per-lawyer withdrawal history) | sejarah_peguam_panel WHERE status_rekod='selesai' | 🟡 partial | 🟡 medium | sejarah written on reassign/reject; no per-LAWYER aggregated withdrawal history |
| Cetak Butiran Peguam (printable dossier) | printModal() JS | ❌ none | ⚪ low | No print/PDF for lawyer profile |
| Padam Fail Lampiran (delete lawyer PDF, admin-only) | deletefiles.php | ❌ none | ⚪ low | LampiranController is CASE attachments only |
| semak.php — PUBLIC application status checker | Enter IC → SweetAlert status | ❌ none | 🟡 medium | No public status-check page/route |
| senarai-peguam-panel.php — PUBLIC searchable lawyer directory (Select2) | Filter butiran_peguam_panel_6.checkbox_value | ❌ none | 🟡 medium | No public directory; pengkhususan not modeled |
| panel.php — PUBLIC lawyer registration form (full + uploads) | Multipart pengkhususan/certs/firma/bank/docs | 🟡 partial | 🟠 high | daftar captures subset; no pengkhususan/certs/firma/bank/uploads |
| verify.php — captcha refresh/verify AJAX | Session captcha JSON | ✅ full | ⚪ low | Login number-captcha; registration honeypot+throttle — equivalent anti-abuse |
| query/selenggaraPengguna.php — CORE handler (5 actions, transactions, mail) | update/updatePegJBG/janaNewPass/janaNewPassPP/tambahPegJBG | 🟡 partial | 🔴 critical | Only tambah + partial update have analogues; both janaNewPass* + death-redistribution missing |
| Status code maps (statusAktif 0/1/NULL; sebabTidakAktif text/date; status_agihan 2/4/20) | Redistribution/death codes | 🟡 partial | 🟠 high | permohonan_status modeled; statusAktif/sebabTidakAktif columns don't exist; status_agihan uses string labels |

## Module: pp-kes-oyd — Lawyer Case-Specialization Update + Assisted-Person (OYD) Listing

> CRITICAL: the entire case-specialization add/drop lifecycle (Kemaskini Bidang Pengkhususan Kes) is ABSENT — no ButiranPeguamPanel6 model, controller, route, view, or badge. The 9-state status machine and 3-stage lawyer→Pengarah→KP review are unbuilt. Sub-flow B (OYD listing) is partially covered by peguam.kes but lacks OYD-specific columns, the status-set filter, and the >40-day overdue highlight.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| senarai-kemaskini-kes.php — review queue (role-gated KP vs Pengarah/Admin) | Lists lawyers w/ pending add/drop | ❌ none | 🔴 critical | No route/controller/view; no application code for butiran_peguam_panel_6 |
| maklumat-kemaskini-kes.php — review-and-decide screen (drop + add tables) | Category tabs, select-all, counters, Ulasan | ❌ none | 🔴 critical | No equivalent; PermohonanPeguamController handles registration only |
| State machine on checkbox_value_status (0,1,2,3,4,6,7,8,9) + DELETE-on-drop | Drop:3→7→DELETE, add:4→9→2, etc. | ❌ none | 🔴 critical | Table exists only as DDL; no model, no transition logic |
| Action: Lawyer requests DROP (updateKes, blocks if active matching case) | Block + no_fail list | ❌ none | 🔴 critical | No specialization request action, no block-on-active-case rule |
| Action: Lawyer requests ADD (addNewKes, INSERT IGNORE status=4) | Idempotent insert | ❌ none | 🔴 critical | No route/method inserts butiran_peguam_panel_6 |
| Action: Pengarah Kemaskini (3→7, 4→9, unticked→8, ulasanPengarah) | Recommend/not-recommend | ❌ none | 🔴 critical | @sokong only endorses registration, not specialization rows |
| Action: KP Luluskan (status-7 DELETE, status-9→2, unticked→6) | Final approval | ❌ none | 🔴 critical | @keputusan handles registration KP decision only |
| Notification badge: navbar countKemaskini (Pengarah/KP) | Role-specific pending badge | ❌ none | 🟡 medium | No kemaskini menu/badge in staff layout |
| Senarai_Orang_Yang_Dibantu.php — OYD listing (OYD-specific columns) | status_agihan IN 2,5,12,16,17 + OYD cols | 🟡 partial | 🟠 high | peguam.kes lists ALL statuses w/ generic columns; no Tarikh Khidmat Nasihat / Borang II / Pengantaraan |
| OYD red-row highlight (Diluluskan AND tarikh_pemakluman NULL AND age>40d) | >40-day overdue red row | ❌ none | 🟠 high | No such highlight; offer-overdue (7d) is a different rule |
| OYD search filters (Nama LIKE, No K/P =, Status) | Active GET filters | ❌ none | 🟡 medium | peguam.kes paginates with no filter inputs |
| OYD row click → case detail (tabs.php) | Clickable row | ✅ full | ⚪ low | Rows link to peguam.kes.show |
| OYD hard role gate (peranan==1, scoped to lawyer) | Lawyer-only, name-scoped | ✅ full | ⚪ low | role:peguam middleware + name scope |
| permohonan-baru.php — REGISTRATION approval (role-switch) | adminpt/pengarah/kp | ✅ full | ⚪ low | permohonan-peguam.* 3-tier flow matches |
| Registration field: syordariPengarah + ulasan_sokonganPengarah | Recommendation fields | ✅ full | ⚪ low | @sokong sokonganPengarah in:0,1 + ulasan max:600 |
| View uploaded KP PDF on review screen | uploaded_files KP link | ❌ none | ⚪ low | No kemaskini review screen to host it |
| Nama Peguam → status-peguam-detail (new tab) | Name link | 🟡 partial | ⚪ low | peguam-panel.show exists but not wired from (absent) kemaskini screen |
| category code canonicalization (SIV/JEN/SYA/PG, SIV/SIVL drift) | getJenisKes() map | ❌ none | 🟡 medium | No code handles category; no canonical map |
| Diagnostics page info.php (phpinfo) | Info-leak | ❌ none | ⚪ low | Correctly absent (security-positive) |
| Session idle auto-logout (30-min) | Destroy session + redirect | 🟡 partial | 🟡 medium | Relies on default Laravel session lifetime; no explicit 30-min idle logout |

---

# System: sistem-rekod-kes

## Module: rk-permohonan — Permohonan Bantuan Guaman (Borang 1) Legal-Aid Intake

> Solid STRUCTURAL parity: 3 legacy file-tiers consolidated into ONE role-gated wizard; guardian fields + tarikh_daftar preserved; CSV/PDF exports exist. But CRITICAL logic gaps: no_fail generation is a degraded stub (wrong format, no 24 branch codes, no per-branch sequencing); check_nokp duplicate detection absent; several decision/lifecycle columns missing; all legacy client toggles gone.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| admin_permohonan_bantuan_guaman.php — Admin intake | File-based admin tier | ✅ full | ⚪ low | Collapsed into kes/form gated by role middleware |
| permohonan_bantuan_guaman.php — Officer 4-step wizard (+ Borang 1A) | Guardian block + auto-unlock | 🟡 partial | 🟠 high | 5-step wizard; guardian NOT auto-unlocked when <18, no 1A gating |
| pengarah_permohonan_bantuan_guaman.php — Director intake twin | Twin file | ✅ full | ⚪ low | Consolidated; pengarah in STAFF_ROLES |
| no_fail generation (jFail.php) — JBG.STATE3(jenis)seq/MMYY, 24 codes | Per-branch+jenis ROW_NUMBER | 🟡 partial | 🔴 critical | genNoFail emits JBG/{abbrev}/{id}/{mmYY}; no state map, no sequencing, wrong format |
| check_nokp.php — duplicate detection + modal | nokp → {exists, records[]} | ❌ none | 🔴 critical | nokp rule is nullable|max:12; no exists check, no AJAX, no modal |
| get_jenis_kes.php — cascading kategori→jenis_kes | Filtered options + aktif_kes gate | 🟡 partial | 🟡 medium | Flat pluck, no kategori filter, no aktif_kes gate |
| cetakanMaklumatPermohonan.php — 2-page FPDF (~30 cols) | Full column inventory | 🟡 partial | 🟠 high | ringkasan.blade omits kaedah_pemakluman/tarikh_pemberitahuan/pembatalan/kategori_kes2/jenis_oyd/etc |
| export_permohonan_bantuan_guaman.php — CSV export | fputcsv envelope | ✅ full | ⚪ low | LaporanController@csv permohonan type |
| laporan/statistik_permohonan — report + stats | Report + statistik siblings | ✅ full | ⚪ low | LaporanController + StatistikController cover |
| Submit Borang 1 → INSERT (status='Baru', taraf='Pemohon') | Server constants | 🟡 partial | 🟡 medium | @store doesn't stamp status='Baru'/taraf='Pemohon' |
| Duplicate-check on No.KP (12 digits → modal) | onkeyup fetch | ❌ none | 🔴 critical | No JS handler, no modal, no AJAX |
| toggleCheckbox — Tarikh Khidmat Nasihat Ada/Tiada | Mutually-exclusive reveal | 🟡 partial | 🟡 medium | Plain optional date; no toggle, no conditional-required |
| checkKategoriKes — Pendamping Guaman child-name note | onchange guidance | ❌ none | 🟠 high | Plain select, no Pendamping Guaman handling |
| toggleEtnikField — Etnik only when Kaum=Lain-lain | Conditional enable | 🟡 partial | ⚪ low | Both free-text, always-enabled |
| toggleAgamaField — agamaLain when Agama=Lain-lain | Reveal/require | ❌ none | ⚪ low | Single free-text input; no agamaLain |
| Guardian auto-unlock (<18) | Step-3 lock until minor | 🟡 partial | 🟡 medium | Always-editable plain inputs; no age gate |
| Jantina auto-derived from No.KP (readonly) | Derive from digits | 🟡 partial | 🟡 medium | Manual select/number; no derivation |
| Didaftarkan Oleh (session nama; officer appends datetime) | From session | 🟡 partial | ⚪ low | Sets from user->name; no datetime append |
| Cawangan stamped from session | Officer cannot pick | 🟡 partial | 🟡 medium | User-selectable select; not auto-stamped from user branch |
| kaedah_pemakluman — JSON array multi-select | json_decode in PDF | ❌ none | 🟠 high | No cast, no input, not in PDF |
| tarikh_pemberitahuan_perakuan (Borang IV) | Decision date | ❌ none | 🟡 medium | Not in request/form/controller/PDF |
| Pembatalan Kelulusan Borang 1 + alasan_pembatalan | Cancel approval w/ reason | ❌ none | 🟠 high | Not anywhere; KeputusanController has lulus/tolak/tutupFail only |
| kategori_kes2 + jenis_oyd | Bidang kuasa + OYD type | ❌ none | 🟡 medium | Not in request/form/PDF |
| jenis_kes_lain + nyatakanLain | Free-text 'other' | ❌ none | 🟡 medium | Not in request/form/PDF |
| nama_pegawai_penyiasat | Investigating officer | ✅ full | ⚪ low | In MahkamahRequest + mahkamah form |
| status_agihan / agih_kepada — assignment + navbar notif | Assignment columns | ✅ full | ⚪ low | AgihanController + accept/reject + mail (exceeds legacy) |
| Padam Permohonan (reset) | type=reset | ✅ full | ⚪ low | Batal link + native reset |
| Cetak Maklumat Permohonan (PDF) wiring | ?id= action | ✅ full | ⚪ low | cetak.ringkasan + dompdf (column gap tracked above) |
| 30-min session timeout + auth gate | admin_navbar | 🟡 partial | 🟡 medium | Laravel auth + redirectGuestsTo; no 30-min idle (default 120) |
| kemaskini-aduan.php — IT helpdesk ticket | aduanv2 vendor workflow | ❌ none | ⚪ low | OUT-OF-SCOPE; correctly absent |
| Hantar Emel Vendor + edit/delete aduan | Vendor email + audit | ❌ none | ⚪ low | OUT-OF-SCOPE |

## Module: rk-pengantaraan — Mediation Reporting

> Legacy = 100% read-only reporting suite (~14 pages × 3 role-mirrors), each with CSV/FPDF + filters. Build correctly collapses role-mirrors and ports a THIN slice (3 report types) + KPI SLA aggregates. Heavy specialized reporting MISSING: no completed-mediation list, no monthly race/gender statistics, no per-branch assignment matrices, no SLA case *lists* (only aggregate %), no per-branch compliance stats, no per-statistik PDF.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| Fail Kes Selesai Pengantaraan — completed-mediation file list (~13 cols) | tarikh_perakuan, sebab_selesai, KPI 30-hari | ❌ none | 🟠 high | No 'fail-kes-selesai' report key |
| Maklumat Lanjut detail export (CSV) | export from list | ❌ none | 🟡 medium | No such CSV |
| Statistik Bulanan Kes Pengantaraan — monthly per-type race/gender matrix | Pendaftaran + buckets + selesai breakdown | ❌ none | 🟠 high | StatistikController has generic group counts; no race/gender matrix |
| PDF Statistik Bulanan Kes Pengantaraan | FPDF | ❌ none | 🟡 medium | No dompdf view |
| Statistik Penugasan Pengantaraan — per-branch (23) Sivil/Syariah/Jumlah | Year filter, fixed order | ❌ none | 🟠 high | byCawangan is top-12 generic, no fixed 23-branch matrix |
| Statistik Penugasan Bulanan — per-branch × 12-month | kategori + year | ❌ none | 🟠 high | byBulan is global trend, not per-branch matrix |
| PDF Statistik Penugasan Bulanan + Pendaftaran | FPDF landscape | ❌ none | 🟡 medium | No dompdf views |
| Senarai Khidmat Pengantaraan — >60-day SLA list | tempoh melebihi 60 hari | 🟡 partial | 🟠 high | KpiController computes 60-day % but no listable rows |
| Statistik Khidmat + CSV + PDF (per-branch 60-day compliance) | capai/tidak per branch | ❌ none | 🟠 high | No per-branch compliance table/CSV/PDF |
| Senarai Pemfailan Kes Terlibat — >120-day list | tempoh melebihi 120 hari | 🟡 partial | 🟠 high | 120-day % aggregate only, no list/export |
| Statistik Pemfailan Terlibat + CSV + PDF | Per-branch filing stats | ❌ none | 🟠 high | No per-branch filing-compliance table/CSV/PDF |
| Senarai Pemfailan Kes Tidak Terlibat — >60-day list | tempoh melebihi 60 hari | 🟡 partial | 🟠 high | 60-day % aggregate only, no list/export |
| Statistik Pemfailan Tidak Terlibat + CSV + PDF | Per-branch | ❌ none | 🟠 high | No per-branch table/CSV/PDF |
| Laporan Penugasan Pengantaraan (status_pengantaraan='Ya', status_sidang colors) | Color-coded sidang | 🟡 partial | 🟡 medium | Report exists but filter notNull (not ='Ya'), cols differ, no kategori filter |
| CSV export_laporan_penugasan | Generic CSV | 🟡 partial | ⚪ low | Exists but columns differ |
| Laporan Pengantaraan Tidak Dirujuk | nokp + jenis_kes(ref_kes) | 🟡 partial | 🟡 medium | Columns differ; no kategori filter |
| PDF + CSV Tidak Dirujuk | FPDF | 🟡 partial | ⚪ low | Generic export exists, layout differs |
| Laporan Pencapaian Penugasan — 3 KPI ratio formulas per branch | Perakuan/Penugasan/Sidang/Perjanjian ratios | 🟡 partial | 🟠 high | Lists by cara_selesai, NOT the 3 ratio formulas |
| PDF Laporan Pencapaian | FPDF | ❌ none | 🟡 medium | Renders wrong content, not 3-formula matrix |
| Filter: Month + Year (MONTH/YEAR on tarikh_perakuan) | Selects | 🟡 partial | 🟡 medium | Only cawangan + date range on tarikh_permohonan |
| Filter: Cawangan (23 branches) | Hardcoded UNION | ✅ full | ⚪ low | Select present (distinct from data) + CawanganScope |
| Filter: kategori_kes / jenis_kategori | Sivil/Syariah | 🟡 partial | 🟡 medium | StatistikController has it; LaporanController has none |
| Filter: jenis_kes (SIV/SYA/JEN/PG via ref_kes) | FIELD ordering | ❌ none | 🟡 medium | No jenis_kes filter |
| Filter: Year-only (penugasan matrices) | Dynamic year list | ❌ none | 🟡 medium | KPI accepts tahun; matrices don't exist |
| Action: Reset filter | PHP_SELF | ✅ full | ⚪ low | Reset links present |
| Action: Cari/Submit (GET) | Filter reload | ✅ full | ⚪ low | All GET forms |
| Export: CSV (filter-derived filename) | 6 export_*.php | 🟡 partial | 🟡 medium | Generic fputcsv for 3 ported types only |
| Export: FPDF Print (page-no + Dikemaskini footer) | ~8 cetakan_*.php | 🟡 partial | 🟡 medium | dompdf for 3 types; no per-statistik PDFs |
| View: read-only DataTables + ref_kes lookup + pagination | No pagination | ✅ full | ⚪ low | paginate(30)+withQueryString (improvement) |
| Role mirrors: admin/pengarah/unprefixed triplets | 3× file copies | ✅ full | ⚪ low | Collapsed to role middleware |
| Security: SQLi fix | Raw concat | ✅ full | ⚪ low | Eloquent bindings throughout |

## Module: rk-statistik — Statistics Dashboards + PDF Stat Printouts (per-branch SLA KPI matrices)

> Legacy = SIX per-branch SLA matrix dashboards (BIL + CAWANGAN + 4 kategori × {CAPAI/TIDAK/PERATUS%}) over a hardcoded 23-row JBG branch UNION, each with FPDF A3-landscape PDF + numeric drill-downs. Build has the SAME FIVE SLA computations (KpiController DATEDIFF thresholds 40/60/120/7/60 match exactly) but renders a single year-scoped stacked-bar chart — NO branch dimension, NO month filter, NO CAPAI/TIDAK/PERATUS columns, NO per-branch PDF, NO drill-down.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| Statistik Khidmat Pengantaraan (per-branch matrix, 60d) | BIL+CAWANGAN+4 kategori × capai/tidak/peratus | 🟡 partial | 🔴 critical | SLA math present; rendered as stacked-bar, no per-branch matrix/footer |
| Statistik Pemfailan Terlibat (per-branch matrix, 120d) | Per-branch CAPAI/TIDAK 120 | 🟡 partial | 🔴 critical | fail_dengan math matches; no matrix/footer |
| Statistik Pemfailan Tiada (per-branch matrix, 60d) | Per-branch | 🟡 partial | 🔴 critical | fail_tanpa matches; no matrix |
| Statistik Serahan Perintah (per-branch matrix, 7d) | Per-branch | 🟡 partial | 🔴 critical | serahan matches; no matrix |
| Statistik Perakuan Bantuan Guaman (per-branch matrix, 40d) | Per-branch + month filter | 🟡 partial | 🔴 critical | perakuan matches; no matrix, no month dropdown |
| Statistik Kesilapan Penjanaan Nombor Fail (12-month matrix, admin-only) | Per-branch JAN..DIS counts | ❌ none | 🟠 high | No controller/route/view |
| Statistik Penugasan/Pendaftaran (per-branch Sivil/Syariah/Jumlah) | Count matrix | 🟡 partial | 🟠 high | LaporanController is flat row list, not count matrix |
| Month (Bulan) filter on KPI dashboards | Jan-Dis select | ❌ none | 🟠 high | Only Tahun input; no month select |
| Year filter on SLA dashboards | 2024-2030 select | ✅ full | ⚪ low | whereYear($def['month'], $year) |
| Cawangan dimension — hardcoded 23-branch FIELD-ordered list | 23-branch UNION | ❌ none | 🔴 critical | cawanganList is distinct() from data; KPI has no per-branch rows |
| PERATUS KEPATUHAN % column (per branch per kategori) | capai*100/NULLIF ROUND 2 | 🟡 partial | 🟠 high | One overall % per KPI, not per-branch-per-kategori cells |
| Grand-total / JUMLAH KESELURUHAN footer | tfoot totals | ❌ none | 🟡 medium | Per-KPI cards, no footer totals |
| Drill-down numeric cells → senarai_* lists | Per cawangan+kategori+month | ❌ none | 🟠 high | Static SVG bars, no hyperlinks |
| PDF: Statistik Khidmat (FPDF A3 landscape) | Mirrors 60d matrix | ❌ none | 🟠 high | statistik.pdf is generic cards, not matrix |
| PDF: Statistik Pemfailan Terlibat (A3, 120d) | FPDF | ❌ none | 🟠 high | No per-branch matrix PDF |
| PDF: Statistik Pemfailan Tiada (A3, 60d) | FPDF | ❌ none | 🟠 high | No per-branch matrix PDF |
| PDF: Statistik Serahan Perintah (A3, 7d) | FPDF | ❌ none | 🟠 high | No per-branch matrix PDF |
| PDF: Statistik Perakuan (A3, 40d) | FPDF | ❌ none | 🟡 medium | No per-branch matrix PDF |
| PDF: Statistik Pendaftaran/Penugasan | Count matrix PDF | ❌ none | 🟡 medium | Flat-row only |
| Action: CARI / Tapis (month+year) | fa-search submit | 🟡 partial | ⚪ low | Cari for year only; no month |
| Action: SET SEMULA (reset) | Clear GET | ✅ full | ⚪ low | Set Semula link |
| Action: MUAT TURUN / Cetak PDF (year+month+cawangan) | target=_blank inline | ❌ none | 🟠 high | KPI screen has NO PDF/Cetak button |
| PDF: Statistik Kesilapan (12-month matrix) | FPDF | ❌ none | 🟡 medium | No kesilapan report/PDF |
| Auth-gate: admin-only on kesilapan (peranan!=1) | Hard redirect | ❌ none | 🟡 medium | Report missing → gate also absent |
| Role-based navbar/tab routing (stat view variants) | peranan 1/2/else | 🟡 partial | ⚪ low | Role-gated sidebar via middleware; no per-role stat tabs |
| Excel/CSV export of statistics | Legacy has NONE | ✅ full | ⚪ low | **Exceeds**: @excel (Maatwebsite) + @csv |
| SQL-injection-safe filtering | Concatenated $_GET | ✅ full | ⚪ low | Eloquent + trusted-column whitelists |

## Module: rk-export — Excel/CSV Exports

> Legacy = 14 specialized CSV endpoints (12–56 cols each) with shared envelope (title + filter-summary + headers), NoKP forced to Excel text, derived BULAN/TAHUN columns, universal "Kesilapan Menjana Nombor Fail" exclusion, HQ-vs-branch gating, 5 DATEDIFF SLA-breach reports + ref_kes list. Build replaces all with ONE generic LaporanController exposing 6 thin reports (~7 cols) + Statistik xlsx + KPI dashboard. Build is SUPERIOR on SQL safety and fixes the legacy kesilapan cawangan hole via CawanganScope.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| export_senarai_kes.php — ref_kes reference list (grouped, admin extra cols) | Banner rows by jenis_kes | ❌ none | 🟠 high | RefKesController is CRUD only; no export |
| export_laporan_kes_mahkamah.php — laporan_kes JOIN forms (12 cols) | Prepared stmts + filters | ❌ none | 🟠 high | Single-case dompdf only; no aggregate CSV |
| export_permohonan_bantuan_guaman.php — forms (49 cols + reasonMap) | Date basis tarikh_permohonan | 🟡 partial | 🔴 critical | permohonan report = 7 cols; missing 42 cols, reasonMap, envelope |
| export_pendaftaran_fail_kes.php — forms (29 cols) | tarikh_perakuan, hygiene | 🟡 partial | 🔴 critical | 7 cols; missing ~22 cols, NOT LIKE %null%, derived BULAN/TAHUN, envelope |
| export_status_fail.php — forms (56 cols, status_pemfailan) | Widest, 4 derived states | 🟡 partial | 🔴 critical | 7 cols; missing ~49 cols, status_pemfailan filter, getStatusPemfailan(), envelope |
| export_kesilapan_nombor_fail.php — INVERSE filter (37 cols) | Fail Tutup + Kesilapan | ❌ none | 🟠 high | No report key; alasan_kesilapan_no_fail never exported |
| export_laporan_pengantaraan_tidak_dirujuk.php — (15 cols, ='Tidak') | alasan_tidak_rujuk | 🟡 partial | 🟠 high | Filter is whereNull (not ='Tidak'); 7 cols, missing alasan |
| export_laporan_penugasan_pengantaraan.php — (34 cols + sejarah_sidang) | ='Ya', latest MAX(id) | 🟡 partial | 🟠 high | Filter notNull (not 'Ya'); 8 cols, no sejarah_sidang join |
| export_senarai_khidmat_pengantaraan.php — >60d (51 cols + TEMPOH) | SLA breach | ❌ none | 🟠 high | KPI % only; no row-level CSV |
| export_senarai_serahan_perintah_kes.php — >7d (52 cols) | SLA breach | ❌ none | 🟠 high | KPI % only; no breach CSV |
| export_senarai_perakuan_melebihi_40_hari.php — >40d (53 cols) | + kelulusan/sumbangan | ❌ none | 🟠 high | KPI % only; no breach CSV |
| export_senarai_pemfailan_kes_terlibat_pengantaraan.php — >120d (53 cols) | status='Ya' | ❌ none | 🟠 high | KPI % only; no breach CSV |
| export_senarai_pemfailan_kes_tiada_pengantaraan.php — >60d (53 cols) | status='Tidak' | ❌ none | 🟠 high | KPI % only; no breach CSV |
| Shared CSV envelope (title + filter-summary + header rows) | safeFilename() | ❌ none | 🟡 medium | @csv writes only header + data; filename is <type>-<Ymd-His> |
| NoKP forced to Excel text (=\"<digits>\") | formatNokp() | ❌ none | 🟡 medium | Raw nokp; no Excel-text guard |
| Derived BULAN / TAHUN sibling columns | getMonth/getYear | ❌ none | 🟡 medium | Dates rendered once; no derived columns |
| Universal exclusion (Fail Tutup + Kesilapan) | All forms exports | ❌ none | 🟠 high | No 'Kesilapan...' string anywhere; error cases leak into reports |
| Year + Month filters on tarikh_perakuan | SLA + kesilapan | ❌ none | 🟡 medium | Only cawangan + range on tarikh_permohonan |
| HQ-vs-branch cawangan gating (isHQ free-choice; non-HQ locked) | own-branch floats top | 🟡 partial | 🟡 medium | CawanganScope is global isolation; no GET free-choice, no own-branch-top |
| SQL safety (legacy raw interpolation) | SQLi-exposed | ✅ full | ⚪ low | **Resolved**: Eloquent bound params |
| Guest/session auth gate | Per-file session check | ✅ full | ⚪ low | Staff middleware group + redirectGuestsTo |
| PDF export of reports (legacy: NONE) | CSV-only | ✅ full | ⚪ low | **Exceeds**: dompdf + xlsx are a superset |

## Module: rk-cuti-users-poster — Leave / Users / e-Poster / Password / Dashboards

> Mostly at full/better-than parity — password change, user/officer/poster deletes reimplemented with Eloquent + role gates + CSRF + audit (legacy SQLi/authz/input-bug fixed). Single CRITICAL gap: the entire Cuti Umum (public-holiday) module — RefCuti model exists but NO controller/route/view/CRUD. Secondary: no role-aware public poster gallery, no notification bell dropdown, no 30-min idle logout, partial dashboard cawangan/aggregation set.

| Feature | Legacy behavior | Status | Severity | Evidence |
|---------|-----------------|--------|----------|----------|
| formTambahCuti.php — Tambah Cuti Umum (INSERT ref_cuti, 16-state) | idnegeri checkbox string | ❌ none | 🔴 critical | RefCuti model exists; no controller/route/view/CRUD |
| formUpdateCuti.php — Kemas kini Cuti (checkbox editor) | idnegeri substr decode | ❌ none | 🔴 critical | No CutiController/route/view |
| formKemaskiniCuti.php — legacy free-text editor + handler | UPDATE ref_cuti | ❌ none | 🟠 high | No cuti CRUD; consolidate into one editor |
| ref_cuti.idnegeri denormalized 16-slot string | Per-state involvement | ❌ none | 🟠 high | RefCuti has no idnegeri cast/pivot |
| Senarai Cuti / list_cuti entry point | Selenggara menu | ❌ none | 🟠 high | Selenggara sidebar has no Cuti link |
| admin_tukar_kata_laluan.php — Tukar Kata Laluan | Update users.kata_laluan | ✅ full | ⚪ low | @changePassword (current_password + confirmed min:8); legacy bugs fixed |
| Password policy strength (12 chars + classes) | Client JS rules | 🟡 partial | ⚪ low | Only min:8 + different; no complexity rule |
| admin_delete_user.php — Padam Pengguna (AJAX) | Unparameterized DELETE | ✅ full | ⚪ low | @destroy Eloquent + self-block + CSRF + audit |
| delete_pengguna.php — Padam Pengguna (prepared) | DELETE + redirect | ✅ full | ⚪ low | Same @destroy |
| delete_pegawai.php — Padam Pegawai JBG | Prepared DELETE | ✅ full | ⚪ low | @destroy + audit + CSRF |
| e-poster.php — admin CRUD | Tambah/Edit/Padam | ✅ full | ⚪ low | PosterController CRUD + private disk + audit + DELETE/CSRF |
| e-poster.php — role-aware PUBLIC gallery (active-only, PDF cards) | peranan!=1 sees aktif | 🟡 partial | 🟡 medium | Admin list-table only; no public active-only card gallery |
| e-poster.php — Cari Poster | GET tajuk LIKE | ✅ full | ⚪ low | @index ?q LIKE |
| e-poster.php — Notis + Manual link | Static notice | ❌ none | ⚪ low | No notice/manual link |
| admin_navbar.php — Notification bell (forms status 9,14) | COUNT + dropdown | 🟡 partial | 🟡 medium | $perluTindakan on dashboard only; no topbar bell |
| admin_navbar.php — 30-min idle auto-logout | Server check + JS | ❌ none | 🟡 medium | Default Laravel session lifetime; no idle timeout |
| admin_navbar.php — Selenggara/profile menu tree | Full menu | 🟡 partial | ⚪ low | Most links present; Tukar Kata Laluan not in nav |
| dashboard.php — Unified Dashboard (role-locked cawangan + filters) | cawangan/month/year | 🟡 partial | 🟡 medium | @utama has no cawangan/month/year filter or role-lock picker |
| dashboard.php — Chart.js aggregations (12 cuts) | keputusan/status/reason/etc | 🟡 partial | 🟡 medium | KPI grid + trend + donut; missing pembatalan/status_sidang/status_pengantaraan/sumbangan cuts |
| admin_dashboard.php — duplicate dashboard (DEPRECATED) | Superseded | ✅ full | ⚪ low | Single @utama; not duplicated |
| Notifikasi Perlu Tindakan banner → drilldown | Rejected-cases banner | 🟡 partial | ⚪ low | $perluTindakan rows present; ensure each links to kes.show |
| Log Keluar / session auth | logout.php | ✅ full | ⚪ low | POST /logout + invalidate/regenerate |

---

> Note: the input also contained a placeholder `test` module with a single stub feature (`x`, none/low) — excluded as noise. Real total = 246.
