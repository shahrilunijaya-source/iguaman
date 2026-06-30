# iGuaman Janji Temu / Khidmat Nasihat — Full Parity Map

> Source-of-truth mapping of the **.NET iGuaman** system (`be_iguaman-master` ASP.NET + `fe-iguaman-master` Nuxt 2) → what to build in **2in1** (Laravel 13 + Blade + MySQL).
> Produced 2026-06-30 from deep reads of both originals + 2in1 reuse inventory.

---

## 0. Headline findings

1. **2in1 has ZERO of this.** No janji temu / khidmat nasihat routes, controllers, models, or views. The current 2in1 = parity rebuild of the **PHP legacy** (rekod-kes + peguam-panel) only. This citizen portal is a **different original**.
2. **The Nuxt frontend is the true scope, not the .NET backend.** The FE calls ~40 endpoints across 16 controllers; the `be_iguaman` .NET snapshot implements only ~20 (8 controllers). Whole subsystems the FE depends on — `Public/*`, `HariCutiOff`, `CawanganMahkamah`, `Jawatan`, `MaklumBalas`, `Bilik`, `Tetapan`, `Laporan`, dashboard counts, slot auto-generate — **do not exist in the .NET snapshot**. Build to the **frontend contract**, treat the .NET code as schema hints only.
3. **Two role universes.** The KN system roles (`PELANGGAN`, `PEMBANTU TADBIR`, `PEGAWAI KHIDMAT NASIHAT`, `SUPERADMIN`, `PENGURUSAN`) are NOT the 2in1 8-role set — only `pembantu_tadbir` overlaps by name. Needs a reconciliation decision (§6).
4. **No citizen user exists in 2in1.** All 2in1 public surface is anonymous. KN needs real citizen accounts (`PELANGGAN`) with **IC-based login** (`noPengenalan`), not email.
5. **Originals have no FK constraints, plaintext password column, hardcoded UTC+8, untyped status strings.** Do NOT port those defects — fix in the Laravel rebuild.

---

## 1. Domain model → Laravel schema parity

`.NET` is PostgreSQL/Guid. 2in1 is MySQL/bigint snake_case. Rebuild with bigint PKs + real FKs + enums.

| .NET entity / table | Laravel table (new) | Reuse in 2in1? | Notes |
|---|---|---|---|
| `Pengguna` / AspNetUsers | extend `users` | **REUSE** `users` | add `user_type='awam'`, IC login (`nokp` exists), citizen profile cols (jantina, agama, bangsa, alamat) |
| `Peranan` / AspNetRoles | Spatie `roles` | **REUSE** | add KN roles (§6) |
| `Negeri` / Negeris | — | **REUSE** `ref_negeri` | 16 states already seeded |
| `Kategori` / Kategoris | `kn_kategori` | build | advisory category (≠ `ref_kes`) |
| `JenisKes` / JenisKess | `kn_kategori_kes` | build | FE has 3-level tree: kategori → kategori_kes → **subkategori** (deeper than .NET) |
| (FE only — subkategori) | `kn_subkategori` | build | exists in FE, missing in .NET snapshot |
| `KhidmatNasihat` / KhidmatNasihats | `khidmat_nasihat` | build | core application record; ⚠ check `forms.tarikh_khidmat_nasihat` first (§7) |
| `TemuJanji` / TemuJanjis | `temu_janji` | build | appointment; link to slot |
| `SlotTemujanji` / SlotTemujanjis | `slot_temu_janji` | build | FE auto-generates per branch+bilik+date |
| `CawanganJBG` / Cawangans | `cawangan_jbg` (+`bilik`) | build | currently free-text in 2in1; needs master + rooms + session/weekend config |
| `JKM` / JKMs | `cawangan_jkm` | build | welfare branches |
| `Penjara` / Penjaras | `cawangan_penjara` | build | prison branches |
| (FE) CawanganMahkamah | `cawangan_mahkamah` | **REUSE-ish** `mahkamah_sivil`/`mahkamah_syariah` | consolidate |
| (FE) Jawatan | `ref_jawatan` | build | free-text `pegawai_jbg.jawatan` today |
| (FE) HariCutiOff (umum) | — | **REUSE** `ref_cuti` + `CutiNegeri` | public holidays |
| (FE) HariCutiOff (negeri) | extend `ref_cuti` | **REUSE** | state holidays via bitmask helper |
| (FE) PenutupanOperasi | `penutupan_operasi` | build | per-branch/room closures |
| (FE) MaklumBalas | `maklum_balas` | build | satisfaction survey |
| (FE) Tetapan | `tetapan` | build | generic key/value settings |

**Status enums to define (originals use untyped strings):**
- `khidmat_nasihat.status_kn`: `DRAF · BAHARU · DALAM_PROSES · SELESAI · BATAL`
- `temu_janji.status`: (infer) `MENUNGGU · DISAHKAN · HADIR · TIDAK_HADIR · SELESAI`
- `jenis_permohonan`: `DIRI_SENDIRI · SEBAGAI_WAKIL`

---

## 2. Feature / route parity matrix

Legend: 🟥 build new · 🟨 reuse-with-extend · 🟩 already in 2in1

### Public / citizen (guest)
| Feature | FE page | Laravel route (proposed) | Status |
|---|---|---|---|
| Landing | `index.vue` | `GET /` (replace welcome stub) | 🟨 |
| Hubungi kami | `hubungi-kami.vue` | `GET /hubungi-kami` | 🟥 |
| Citizen registration | `daftar-pengguna.vue` | `GET/POST /daftar` + IC/email uniqueness checks | 🟥 |
| Login (IC-based) | `log-masuk.vue` | extend `SystemAuthController` for `nokp` login | 🟨 |
| Forgot / set password | `set-katalaluan.vue` | reuse `PasswordResetController` | 🟩 |

### Khidmat Nasihat (citizen + KN staff)
| Feature | FE page | Status |
|---|---|---|
| Eligibility screening (3 modals: saringan/income/T&C) | `khidmatnasihat/index` | 🟥 |
| Application list (by user / cawangan / PKN) + filters | `khidmatnasihat/index` | 🟥 |
| **4-step wizard** | `permohonan-baru.vue` | 🟥 |
| — Step 1 Maklumat (diri-sendiri) | `diri-sendiri.vue` | 🟥 |
| — Step 1 Maklumat (sebagai-wakil: penjara/JKM/mahkamah) | `sebagai-wakil.vue` | 🟥 |
| — Step 2 Bayaran (RM10 / RM260 sumbangan / RM0 penjara+JKM / isPercuma) | `bayaran.vue` | 🟥 |
| — Step 3 Slot janji temu (≥4 working days, weekend/holiday/closure aware) | `slot-janji-temu.vue` | 🟥 |
| — Step 4 Perakuan (declaration → status BAHARU) | `perakuan.vue` | 🟥 |
| Edit draft | `kemaskini-permohonan/_id` | 🟥 |
| Doc upload (dokumen sokongan PDF) | wizard | 🟥 (use multipart, not base64) |
| Pengesahan janji temu: PKN assign / accept-reject / attendance | `pengesahan-janjitemu/_id` | 🟥 |
| Maklum balas (satisfaction survey) | `borang-maklumbalas/*` | 🟥 |

### Appointments + calendar (staff)
| Feature | FE page | Status |
|---|---|---|
| Janji temu list (date filter, print/export) | `janjitemu/index` | 🟥 |
| Jadual janji temu (calendar) | `janjitemu/jadual-janji-temu` | 🟥 |
| Slot auto-generate per branch/room | `cawangan/jbg/slotJanjiTemu/_id` | 🟥 |
| Cuti umum CRUD | `kalendar/kalendar-cuti` | 🟨 (`ref_cuti`) |
| Cuti negeri CRUD | `kalendar/kalendar-cuti-negeri` | 🟨 (`ref_cuti`+bitmask) |
| Penetapan sesi (branch hours/weekend) | `kalendar/penetapan-sesi-janji-temu` | 🟥 |
| Penutupan hari operasi | `kalendar/penutupan-hari-operasi` | 🟥 |

### Reference / admin (SUPERADMIN)
| Feature | FE page | Status |
|---|---|---|
| Cawangan JBG (+ bilik) CRUD | `cawangan/jbg/*` | 🟥 |
| Cawangan Penjara CRUD | `cawangan/penjara` | 🟥 |
| Cawangan JKM CRUD | `cawangan/jkm` | 🟥 |
| Cawangan Mahkamah CRUD | `cawangan/mahkamah` | 🟨 (`mahkamah_*`) |
| Negeri CRUD | `tetapan/senarai-negeri` | 🟨 (`ref_negeri`) |
| Jawatan CRUD | `tetapan/senarai-jawatan` | 🟥 |
| Kategori tree CRUD (kategori/kes/subkategori) | `tetapan/senarai-kategori/*` | 🟥 |
| Peranan + akses matrix | `tetapan/senarai-peranan/*` | 🟩 (2in1 RBAC UI exists) |
| User mgmt: awam/jbg/penjara/jkm + admin reset | `pengguna/*` | 🟨 (extend 2in1 `pengguna`) |
| System settings | `tetapan/index` | 🟥 |

### Dashboard + reports
| Feature | FE page | Status |
|---|---|---|
| Role dashboard (status counts + charts) | `dashboard.vue` | 🟥 |
| 9 statistical reports (cawangan, kategori, subkategori, pendaftaran, pandangan UU, cara tahu JBG, kepuasan, kaum/jantina) + charts + Excel/PDF | `laporan/*` | 🟥 |

---

## 3. API contract the frontend expects (build these endpoints/logic)

Full endpoint list captured. Highlights the .NET snapshot is **missing** but FE needs:
- `Public/*` — Negeri, CheckNoPengenalanPengguna, CheckEmelPengguna, CreatePenggunaAwam, RequestResetPassword, VerifyResetPasswordLink, SetPassword
- `KhidmatNasihat/ByUser|ByCawanganJBG|ByPKN` (+ `dashboardCount`), `GetAvailableDateForSlotTemujanji/{cawangan}`, `CreateSendiri…`, `CreateWakil…`
- `TemuJanji/GetListOfPegawaiKhidmatNasihat`, `SetPegawaiKhidmatNasihat`, `SetKeputusanPegawaiTerimaKes`, `SlotTemujanjiByIdCawanganJBG`, `SlotTemujanji/AutoCreate|AutoDelete`
- `HariCutiOff/*` (grouped holidays, closures), `CawanganJBG/Bilik/*`, `Jawatan/*`, `MaklumBalas`, `Tetapan/*`, `Laporan/*`
- Dashboard count shape: `{ countStatusBaharu, countStatusDalamProses, countStatusSelesai }`

> In Blade the "API" = controllers returning views + form posts. No need to replicate REST JSON unless a JS calendar/slot picker needs an AJAX endpoint (slot availability → return JSON for Flatpickr).

---

## 4. Slot-availability engine (the hard part)

Booking date rules (from `slot-janji-temu.vue`):
- Minimum **4 working days** ahead.
- Exclude **weekends** per branch's weekend config (some states Fri/Sat, others Sat/Sun).
- Exclude **public holidays** (cuti umum + cuti negeri for branch state) → reuse `ref_cuti` + `CutiNegeri::decode()`.
- Exclude **operational closures** (`penutupan_operasi`) per branch/room.
- Times come from generated `slot_temu_janji` (per branch + bilik + date), `is_temujanji` toggled on booking.

Port as a `SlotAvailabilityService` returning disabled-dates + open-times JSON for a Flatpickr/date picker.

---

## 5. Reuse inventory (don't rebuild)

| Reuse | From 2in1 |
|---|---|
| `users` table + `Auth::attempt` + force-change + reset | extend for citizen/IC login |
| `ref_negeri` (16 states) | state dropdowns |
| `ref_cuti` + `CutiNegeri` bitmask | holiday checks |
| `pegawai_jbg` | officer attribution |
| `mahkamah_sivil` / `mahkamah_syariah` | court reference |
| Spatie `permission:`/`role:` middleware + `@can` | gating |
| `Audit::log()` | every write |
| `layouts/staff.blade.php` | all KN-staff views |
| `@include('partials.chatbot')` | drop on public pages |
| `throttle:N,1` + FormRequest (OydRequest style, Malay labels) | public booking forms |
| `PeguamDaftarController` DB-transaction multi-write | registration/wizard submit |

**Build-new infra:** public layout (welcome is still the Laravel stub), citizen user type, cawangan master, slot engine, kategori tree, reports.

---

## 6. Open decisions (need user sign-off before build)

1. **Citizen accounts** — add `user_type='awam'` rows with IC (`nokp`) login? (Recommended: yes; FE uses real `PELANGGAN` accounts.) Or anonymous session booking?
2. **Role reconciliation** — map KN roles into the existing Spatie set:
   - `PELANGGAN` → new `pelanggan`
   - `PEMBANTU TADBIR` → existing `pembantu_tadbir` (reuse) or KN-scoped?
   - `PEGAWAI KHIDMAT NASIHAT` → new `pegawai_kn`
   - `SUPERADMIN` → existing `admin`?
   - `PENGURUSAN` → new `pengurusan` (reports-only)
   Decide: one unified role set vs domain-scoped permissions.
3. **`forms.tarikh_khidmat_nasihat`** already exists in legacy `forms` — was advisory folded into `forms`? Confirm before creating a separate `khidmat_nasihat` table (§7 risk).
4. **Login identity** — citizen logs in by **IC**, staff by email/username. Unify the login form or split citizen vs staff entry?
5. **Payment** — counter-cash only (`TUNAI DI KAUNTER JBG`), no gateway. Confirm no online payment needed.

---

## 7. Risks

- ~~`forms` has 50+ legacy columns incl `tarikh_khidmat_nasihat` — advisory may have been partially modelled there. **Investigate before schema work**~~ **RESOLVED 2026-06-30:** `forms` (98 cols, 59 rows) is the **rekod-kes CASE spine** (`App\Models\Form`). `tarikh_khidmat_nasihat` is a populated per-case milestone *date* (e.g. 2024-02-01), NOT an advisory-application entity. **No duplication — build a dedicated `khidmat_nasihat` table.** Same words, different thing (case date field vs citizen application+appointment).
  - **Integration point (batch 11):** iGuaman KN is the *front door*. An accepted KN application should spawn/link a `forms` case record; the appointment date populates `forms.tarikh_khidmat_nasihat`. Wire the KN→case bridge in officer processing, not as a duplicate store.
- The `be_iguaman` .NET snapshot is an **early/partial** version; building only to it will under-scope. The FE contract is authoritative.
- Reference data volume (cawangan JBG + bilik, penjara, JKM, mahkamah, jawatan, kategori tree) must be seeded — source the lists.

---

## 8. Batch breakdown (LOCKED sequence 7 → 13)

> This matches the already-locked plan in `context/port-plan-iguaman-janjitemu.md` (decisions locked 2026-06-30). Public portal is batch **13** (LAST), not batch 8. This parity map is a **complement** to that plan — it adds the FE-vs-BE endpoint-gap finding (§2, §3), the reuse matrix (§5), and the slot engine spec (§4).

| Batch | Scope | Key deliverables |
|---|---|---|
| **7 (DONE)** | RBAC refactor | 8 roles + 33 perms (Spatie), full-app re-gate, `admin` super-admin, Peranan+Akses UI, suite 91/91 |
| **8** | Foundations / masters | cawangan master (JBG/JKM/Mahkamah/Penjara) +bilik, 3-level `ref_kategori_kn`+`ref_subkategori_kn`, `ref_jawatan` |
| **9** | Khidmat Nasihat | 4-step wizard (sendiri/wakil/penjara/JKM), bayaran logic, doc upload, status flow |
| **10** | Appointment / slot / calendar | `temu_janji`+`slot_temu_janji`, cuti umum/negeri, penetapan sesi, penutupan operasi, **SlotAvailabilityService (NET-NEW)** |
| **11** | Officer processing | KN list/filters, pengesahan (PKN assign/accept/attendance), janji temu list+jadual |
| **12** | Feedback + reports | maklum balas, 9 statistical reports + charts + Excel/PDF, dashboards |
| **13** | Public portal (awam) | `user_type=awam`, public register/login (IC), captcha, self-service booking, public layout, hubungi-kami |

**Decisions ALREADY locked** (do not re-ask): full public portal in scope (batch 13); real cawangan master table; new normalized `ref_kategori_kn`+`ref_subkategori_kn` (do NOT overload `ref_kes`); DB-driven RBAC via spatie/laravel-permission ^7; `users.role` kept as display/rollback mirror.

---

*Next step: Batch 8 (foundations/masters). The §6 "open decisions" here are mostly already locked — only confirm the `forms.tarikh_khidmat_nasihat` investigation (§7) before KN schema work.*
