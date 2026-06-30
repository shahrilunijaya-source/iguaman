# Legacy iGuaman — Advisory (Khidmat Nasihat) + Appointment (Janji Temu) System

> Consolidation audit map 03. READ-ONLY analysis of the legacy iGuaman system.
> Source: BACKEND `be_iguaman-master` (ASP.NET Core 8 / C# / EF Core / PostgreSQL) +
> FRONTEND `fe-iguaman-master` (Nuxt 2 / Vue 2 / BootstrapVue).
> Owner: **BHEUU — Bahagian Hal Ehwal Undang-Undang** / **JBG** (Jabatan Bantuan Guaman).
> Title in app: "Sistem Janji Temu Khidmat Nasihat" / "Sistem Khidmat Nasihat & Temu Janji".

---

## 0. CRITICAL FINDING — backend is a stub, frontend is the spec

The two repos are **badly out of sync**. The Nuxt frontend is a mature, near-complete
application; the ASP.NET backend is an **early MVP scaffold** that implements only a
fraction of what the frontend calls.

| Aspect | Backend (`be_iguaman-master`) | Frontend (`fe-iguaman-master`) |
|--------|-------------------------------|--------------------------------|
| Maturity | MVP scaffold, ~8 controllers, mostly basic CRUD | Full app: 50+ pages, multi-step flows, reports, role gating |
| DB | PostgreSQL (Npgsql), 11 domain tables + Identity | n/a (consumes API) |
| Category tree | FLAT — `Kategori` + `JenisKes` only (2 levels, FK never wired) | 3-level tree `Kategori → kategoriKes[] → subKategoris[]` |
| Endpoints frontend calls that **DO NOT EXIST in backend** | — | `KhidmatNasihat/CreateSendiriKhidmatNasihat`, `CreateWakilKhidmatNasihat`, `GetAvailableDateForSlotTemujanji`, `GetAvailableTimeForSlotTemujanji`, `SetKhidmatNasihatSlotTemujanji`, `ByCawanganJBG`, `ByUser`, `ByPKN/dashboardCount`, `TemuJanji/SetPegawaiKhidmatNasihat`, `SetKeputusanPegawaiTerimaKes`, `TemuJanji/SlotTemujanji/AutoCreate`, `HariCutiOff/*`, `CawanganJBG/AddBilik`/`Bilik`, `MaklumBalas`, `Laporan/*`, `Jawatan/*`, `Tetapan/*`, `Public/*`, `Peranan` CRUD, `CawanganMahkamah`, `CawanganPenjara`, `CawanganJKM` |

**Implication for the 2in1 rewrite:** treat the **frontend as the authoritative
behavioural spec**. The backend only tells you the persisted column shape for the
entities that were actually built (Pengguna, KhidmatNasihat, TemuJanji, SlotTemujanji,
Kategori, JenisKes, Negeri, CawanganJBG, JKM, Penjara, Peranan). All richer behaviour
(category tree, slot auto-generation, feedback, reports, closures) is implied by the
frontend's API contracts and UI logic, and was never implemented server-side here.

---

## 1. Business objective

Digitise how the Malaysian public reaches **government legal aid / legal advisory**
(Khidmat Nasihat Guaman). Per the backend's own `docs/system-overview.html`:

> "A citizen describes a case, the system classifies it, surfaces available consultation
> slots at the right branch, and locks in an appointment with an assigned officer — all
> under one identity and authorization model."

Three actor classes:
- **Citizens (Awam / PELANGGAN)** — register, lodge a **Khidmat Nasihat** advisory request
  (tagged by case category & type), book a **Temu Janji** appointment in an open slot.
- **Officers / Staff (Pegawai)** — `PEMBANTU TADBIR` register walk-ins, assign cases to a
  `PEGAWAI KHIDMAT NASIHAT` (PKN), accept/reject, confirm attendance, complete the session.
- **Administrators (SUPERADMIN)** — govern roles, users, states, branches, the category
  tree, holidays, sessions/slots. `PENGURUSAN` = read-only management/reporting tier.

Tagline on landing: *"Permohonan Khidmat Nasihat Guaman Yang Anda Perlukan, Hanya Di Hujung Jari."*

---

## 2. Users / roles / permissions

### Roles (string names, ASP.NET Identity `Peranan : IdentityRole<Guid>`)
Seeded role: **`SUPERADMIN`** (`common/SeedData.cs`, demo login No.Pengenalan `880101015500` / `Admin@123`).
Roles referenced across the frontend (counts = literal occurrences):

| Role | Usage | Capabilities (derived from `hasRole`/`hasUserRole`/`useRoleAccess` gates) |
|------|-------|--------------------------------------------------------------------------|
| `SUPERADMIN` (55) | Network admin | Pengguna (awam/jbg/penjara/jkm) CRUD, Cuti Umum + Cuti Negeri, Cawangan JBG/Penjara/JKM, Tetapan (Negeri/Jawatan/Peranan/Kategori), all reports, slot mgmt |
| `PEMBANTU TADBIR` (58) | Counter clerk | Register applicant as representative (`sebagaiWakil` forced), Penutupan Operasi, assign case to PKN, slot generation, reports |
| `PEGAWAI KHIDMAT NASIHAT` (27) | Advisory officer (PKN) | Accept/reject assigned case, confirm attendance, complete session, reports |
| `PELANGGAN` (42) | Citizen | New application (diri-sendiri / wakil), own KN list (`ByUser`), feedback |
| `PENGURUSAN` (8) | Management | Reports only |
| `PEGAWAI PENJARA` (1) | Prison officer (implied) | Lodge on behalf of inmate (`idPenjara` path, payment=0) |
| `PEGAWAI JKM` (1) | Welfare officer (implied) | Lodge as guardian (`idJKM` path, payment=0) |

### Enforcement
- **Frontend** is the real gate: `middleware/authenticated.js` (redirect `/` if not logged in),
  `utils/accessControl.js` `useRoleAccess(...)` (redirect `/unauthorized` if role not allowed),
  per-element `v-if="hasUserRole([...])"`. Current role read from
  `store.state.auth.user.currentRole.peranan.nama`.
- **Backend** authorization is effectively **OFF** — `PenggunaController` `[Authorize]` is
  commented out; no controller carries role policies. `[Authorize]` is registered
  (`UseAuthentication`/`UseAuthorization` in `Program.cs`) but unused on endpoints. Login
  returns a JWT (HS256, `TokenService`) but nothing requires it server-side.

### Auth flow
- `POST /api/Auth/login` (`AuthController`) — body `{ noPengenalan, kataLaluan }`. Looks up by
  `UserName` then `NoPengenalan`; `CheckPasswordAsync`; returns
  `{ token, tokenInfo:{token}, user:{ id, nama, noPengenalan, emel, cawanganJBG, negeriTetap, userRoles[], currentRole } }`.
- Nuxt `@nuxtjs/auth-next` local strategy: token at `tokenInfo.token` (Bearer), user at `user`,
  localStorage prefix `iguaman_auth.`. JWT claims: `sub`, `NameIdentifier`, `noPengenalan`,
  `Name`, `Jti`, `role[]`. Lifetime 24h (`appsettings.json`). CAPTCHA on login (`components/captcha.vue`, client-side only).
- Citizen self-register: `POST /Public/CreatePenggunaAwam` (+ `Public/CheckNoPengenalanPengguna`,
  `Public/CheckEmelPengguna`, `Public/Negeri`) — **backend has no `PublicController`** (only
  `UsersController.DaftarPengguna` → `POST /api/Users/DaftarPengguna`).
- Forgot password: `POST /Public/RequestResetPassword` (not implemented in backend).
- Password set/update pages: `/katalaluan/set-katalaluan`, `/katalaluan/kemaskini-katalaluan`.

---

## 3. Advisory application flow (Khidmat Nasihat)

Citizen-facing wizard: `pages/khidmatnasihat/permohonan-baru.vue`. **4 steps**
(`components/StepNavigation.vue`):

```
Step 1: Maklumat Permohonan  → Step 2: Bayaran  → Step 3: Slot Janji Temu  → Step 4: Perakuan
```

### Step 1 — Maklumat Permohonan (application details)
- **Jenis Permohonan radio** (driven by who is applying):
  - Default citizen: `diriSendiri` (for self) OR `sebagaiWakil` (as representative/guardian).
  - `PEMBANTU TADBIR` / prison / klinik guaman: forced `sebagaiWakil` ("Pendaftaran Melalui
    Kaunter / Klinik Guaman / Penjara").
  - JKM user: forced `sebagaiWakil` ("Permohonan Sebagai Wakil Diri / Penjaga").
- Components: `components/permohonan-baru/diri-sendiri.vue` and `.../sebagai-wakil.vue`.
- **Self-fields auto-filled** from session: NoKP, nama, telefon, emel, umur (computed from IC
  digits via `kiraanUmur`), jantina, agama, bangsa, alamat, poskod, negeri.
- **Kumpulan Umur** derived: `<18 KANAK-KANAK`, `18–59 DEWASA`, `>=60 WARGA EMAS`.
- **Category tagging**: `GET /Negeri`, `GET /Kategori`; cascading select
  `idKategori → idSubKategori` (see §6 category tree).
- **Eligibility / income test (saringan)** — drives the payment path:
  - Annual income threshold **RM 50,000** (`jumlahPendapatan`).
  - If "sumbangan" (contribution) mode but income `<= 50000` → error "kurang daripada had pendapatan".
  - If NOT sumbangan but income `> 50000` → error "melebihi had pendapatan".
  - `isPendampingJenayah` (criminal-companion) **bypasses** the income check entirely.
- Submit: `POST /KhidmatNasihat/CreateSendiriKhidmatNasihat` (or `CreateWakilKhidmatNasihat`)
  with `statusKn: 'DRAF'`, `jenisPermohonan: 'DIRI SENDIRI' | 'SEBAGAI WAKIL'`, `DokumenSokongan`.
  On re-edit: `PUT /KhidmatNasihat/{id}`. Returns `{ id, idKategori, idSubKategori }`.

### Step 2 — Bayaran (payment)
- Component `components/permohonan-baru/bayaran.vue`. Fee logic:
  - **RM 0** if `isPercuma` (free) OR prison (`idPenjara`) OR JKM (`idJKM`) path.
  - **RM 260.00** if `isLaluanSumbangan` / `?sumbangan=true` (contribution / above-threshold path).
  - **RM 10.00** default.
- Persists via `GET /KhidmatNasihat/{id}` → merge → `PUT /KhidmatNasihat/{id}` with
  `jenisBayaran`, `jumlahBayaran` (0/10/260), `isSumbangan`.
- Officer (pengesahan) records `statusBayaran` + `nomborResit` (skipped when amount = 0).

### Step 3 — Slot Janji Temu (appointment slot) — see §4.

### Step 4 — Perakuan (declaration / certify)
- Component `components/permohonan-baru/perakuan.vue`. `Perakuan` boolean on the KN record.
- After perakuan the application leaves DRAF and becomes **BAHARU** (submitted).

### Print / view
- `components/print/maklumat-permohonan.vue` (960 lines) — printable application summary
  (`vue-html-to-paper`).
- Update path: `pages/khidmatnasihat/kemaskini-permohonan/_id.vue` with the same step
  sub-components under `components/kemaskini-permohonan/` (diri-sendiri, sebagai-wakil,
  bayaran, slot-janji-temu, perakuan). Criminal/civil case fields surface here:
  `mangsaTarikhPertuduhan`, `mangsaTarikhSebutanKes` (charge / mention dates → court cases).

---

## 4. Appointment / slot booking (Temu Janji + Slot)

### Booking (citizen, Step 3) — `components/permohonan-baru/slot-janji-temu.vue`
- Select **Cawangan JBG** (`GET /CawanganJBG`) — disabled & auto-set for prison/JKM paths.
- **Available date** lookup: `GET /KhidmatNasihat/GetAvailableDateForSlotTemujanji/{idCawanganJBG}`.
- **Available time** lookup: `GET /KhidmatNasihat/GetAvailableTimeForSlotTemujanji/{idCawanganJBG}?selectedDate=...`.
- **Lead-time rule (client-side):** earliest bookable date = **4 working days** ahead, skipping
  the branch's weekend. Weekend config per branch: `JUMAAT - SABTU` (days 5,6) or default
  `SABTU - AHAD` (days 0,6) — Kelantan/Terengganu style vs national.
- `kaedahTemuJanji` fixed `'SECARA FIZIKAL'` (physical; no virtual option wired).
- Persist: `PUT /KhidmatNasihat/{id}` (sets `idCawanganJBG`) then
  `PUT /KhidmatNasihat/SetKhidmatNasihatSlotTemujanji/{id}` with `{ kaedahTemuJanji, tarikhMulaSlot, masaMulaSlot }`.

### Slot generation (admin) — `pages/cawangan/jbg/slotJanjiTemu/_id.vue`
- `POST /TemuJanji/SlotTemujanji/AutoCreate/{idCawanganJBG}` with:
  - `idBilik` (room; `'ALL'`→null = all rooms), `dateStart`, `dateEnd`,
  - `slotWeightage` (minutes per slot, e.g. `'30m'`),
  - `workingHour` (`HH:mm-HH:mm`), `lunchHour` (`rehatMula-rehatAkhir`),
  - `isIncludeWeekend` (bool).
- List: `GET /TemuJanji/SlotTemujanjiByIdCawanganJBG/{id}`. Delete one:
  `DELETE /TemuJanji/SlotTemujanji/{id}`. Auto-delete: `DELETE /TemuJanji/SlotTemujanji/AutoDelete/{id}`.
- 400 from AutoCreate is shown as "Slot janji temu telah penuh atau tarikh yang dipilih jatuh pada hari cuti."
- Slot row fields: `bilikInfo`, `tarikhMulaSlotTJ`/`tarikhTamatSlotTJ`, `masaMula`, `masaTamat`.

### Rooms (Bilik) — `pages/cawangan/jbg/bilik/_id.vue`
- `GET /CawanganJBG/Bilik/{id}`, `POST CawanganJBG/AddBilik/{id}`,
  `PUT CawanganJBG/UpdateBilik/{id}/{bilikId}`, `DELETE CawanganJBG/RemoveBilik/{id}/{bilikId}`.
- **No Bilik table exists in the backend** — rooms are a frontend-only concept here.

### Branch calendar view — `pages/cawangan/jbg/kalendar/_id.vue`
- Shows holidays + closures overlaid on slot availability.

### Backend reality for appointments
- `TemuJanji` table (see §7) carries `TarikhTemuJanji`, `MasaMula/AkhirTemuJanji`,
  `TempatTemuJanji`, `Status` (string), `IdKhidmatNasihat`, `CawanganJBG`, `IdPegawaiKN`.
- `SlotTemujanji` table is **trivial** — `NamaSlot`, `IsTemujanji`, `TarikhSlot`, `Status` (bool).
  No room/time/capacity/working-hour columns; the AutoCreate engine the frontend expects is
  **not implemented**.

---

## 5. Officer processing flow

Officer queue: `pages/khidmatnasihat/index.vue`. List source depends on role:
`GET /KhidmatNasihat/ByCawanganJBG/{idCawangan}` (staff) or `GET /KhidmatNasihat/ByUser` (citizen).
Detail/processing page: `pages/khidmatnasihat/pengesahan-janjitemu/_id.vue`.

### 5a. Assign to PKN (pengesahan janji temu / case assignment)
- Done by `PEMBANTU TADBIR`. Picks officer from
  `GET /TemuJanji/GetListOfPegawaiKhidmatNasihat/{...}`.
- On confirm:
  1. `PUT /KhidmatNasihat/{id}` — saves `isPesertaKlinikGuaman`, `statusBayaran`, `nomborResit`, charge/mention dates.
  2. `PUT /TemuJanji/{idJanjiTemu}/SetPegawaiKhidmatNasihat/{idPegawaiKN}` — assigns the PKN.
  - Success: "Penugasan Kepada Pegawai Berjaya Dihantar."

### 5b. PKN accept / reject (`acceptReject`)
- `PUT /TemuJanji/{idJanjiTemu}/SetKeputusanPegawaiTerimaKes` with `{ isTerimaKes, alasan }`.
  - Reject requires `alasan` (reason).
  - On **accept**: also `PUT /KhidmatNasihat/{id}` setting **`statusKN: 'DALAM PROSES'`**.
  - On **reject**: only the TemuJanji decision is written (no KN status change).

### 5c. Attendance + completion (`save` — "Mengesahkan Kehadiran")
- `PUT /TemuJanji/{idJanjiTemu}` with `{ kaedahTemujanji:'SECARA FIZIKAL', isPelangganHadir, ulasanPegawai }`.
- Plus `PUT /KhidmatNasihat/{id}` setting **`statusKN: 'SELESAI'`** and finalising the
  category triple `idKategori / idKategoriKes / idSubKategori`.
- Two messages: "Sesi Khidmat Nasihat Telah Selesai" (hadir) vs
  "...Selesai Tanpa Kehadiran Pelanggan" (tidak hadir).

### 5d. Cancellation
- Citizen/clerk can cancel a DRAF/eligible record: `PUT /KhidmatNasihat/{id}` with
  `statusKN: 'BATAL'`. Delete (DRAF only): `DELETE /KhidmatNasihat/{id}`.

### Detail components
- `components/pengesahan/maklumat-diri.vue` (1254 lines) — full applicant + case detail view.

---

## 6. Category tree (kategori → kategori-kes → subkategori)

**3-level taxonomy** in the frontend (consumed at `onChangeKategori`):
```
Kategori            (e.g. JENAYAH / SIVIL / SYARIAH)   { id, jenisKategori, kategoriKes[] }
 └─ KategoriKes     (case category, "kategori kes")     { id, jenisKategoriKes, subKategoris[] }
     └─ SubKategori (sub-type)                          { id, jenisSubKategori }
```
- Read: `GET /Kategori` (returns the nested tree). The application stores the resolved
  `idKategori`, `idKategoriKes`, `idSubKategori` on the KN record (finalised by PKN at SELESAI).
- Admin CRUD (`pages/tetapan/senarai-kategori/...`):
  - Kategori: `GET/POST/PUT/DELETE /Kategori`, `GET /Kategori/{id}`.
  - KategoriKes: `GET /Kategori/GetKategoriKes/{id}/{...}`, `POST /Kategori/CreateKategoriKes/{id}`,
    `PUT /Kategori/UpdateKategoriKes/{id}/{kesId}`, `DELETE /Kategori/DeleteKategoriKes/{id}/{kesId}`.
  - SubKategori: `POST /Kategori/CreateSubKategori/{id}/{kesId}`,
    `PUT /Kategori/UpdateSubKategori/{id}/{kesId}/{subId}`,
    `DELETE /Kategori/DeleteSubKategori/{id}/{kesId}/{subId}`.

**Backend reality:** only a FLAT 2-level model — `Kategori { Id, JenisKategori }` and
`JenisKes { Id, Jenis_Kes, IdKategori }`. The `kategoriKes`/`subKategoris` nesting and all the
nested CRUD endpoints are **NOT in the backend**. `JenisKes.IdKategori` FK is declared but never
configured as a relationship in `OnModelCreating` (empty). **Memory note `ref-kes-not-kn-tree`
applies: this litigation taxonomy is NOT the KN advisory tree.**

---

## 7. Database tables (backend EF Core / PostgreSQL `IGuaman`)

DbContext `Repositories/AppDbContext.cs : IdentityDbContext<Pengguna, IdentityRole<Guid>, Guid>`.
All PKs are `uuid`. Migrations auto-applied on boot (`dbContext.Database.Migrate()`).
`OnModelCreating` defines **no custom relationships, indexes, or constraints** beyond Identity
defaults — every FK below is a bare `Guid` column with no DB-level FK.

| Table (DbSet) | Key columns | Notes |
|---------------|-------------|-------|
| `AspNetUsers` (`Penggunas`) | `Id`, `NoPengenalan`, `JenisPengenalan`, `Nama`, `AlamatTetap1-3`, `Poskod`, `Bandar`, `NoTel`, `Emel`, `Jantina`, `Agama`, `Bangsa`, `KodKeselamatan`, `StatusPengguna`, `KataLaluan`, `JawatanPegawai`, `GredPegawai`, `BahagianPegawai`, `IsPegawai`, `PerananId`, `NegeriId`, `CawanganJbgId`, audit (`TarikhCipta/Kemaskini`, `CiptaOleh/KemaskiniOleh`) + Identity cols (`PasswordHash`, `UserName`, etc.) | `Pengguna : IdentityUser<Guid>`. `NoPengenalan` NOT unique-indexed. `KataLaluan` stored alongside Identity `PasswordHash`. |
| `AspNetRoles` (`Peranans`) | `Id`, `Name`, `NormalizedName`, `Discriminator` | `Peranan : IdentityRole<Guid>`. |
| `AspNetUserRoles` / `UserClaims` / `RoleClaims` / `UserLogins` / `UserTokens` | Identity standard | |
| `KhidmatNasihats` | `Id`, `JenisPermohonan`, `JenisKes`, `NamaMangsa`, `IdPengenalanMangsa`, `JenisPengenalanMangsa`, `JantinaMangsa`, `UmurMangsa`, `Bangsa`, `Agama`, `TarikhLahirMangsa` (date), `NamaWakil`, `AnakNo`, `AlamatSurat1-3`, `Poskod`, `Perakuan` (bool), audit, `JumlahBayaran` (double), `StatusBayaran` (bool), `UlasanPermohonan`, `UlasanPegawai`, `StatusKN`, `IdTemuJanji`, `IdPengguna`, `IdKategori`, `IdNegeri` | Central advisory record. The frontend uses MANY more fields (income, sumbangan, charge/mention dates, idKategoriKes, idSubKategori, idCawanganJBG, dokumenSokongan, maklumBalas) that are NOT columns here. |
| `TemuJanjis` | `Id`, `TarikhTemuJanji` (date), `MasaMulaTemuJanji`/`MasaAkhirTemuJanji` (time), `TempatTemuJanji`, `Status` (string), audit, `IdKhidmatNasihat`, `CawanganJBG`, `IdPegawaiKN` | Appointment. Attendance / accept-reject fields the frontend writes (`isPelangganHadir`, `isTerimaKes`, `alasan`) are NOT columns. |
| `SlotTemujanjis` | `Id`, `NamaSlot`, `IsTemujanji` (bool), `TarikhSlot` (date), `Status` (bool) | Trivial — no room/time/capacity. |
| `Kategoris` | `Id`, `JenisKategori` | Flat; no nesting. |
| `JenisKess` | `Id`, `Jenis_Kes`, `IdKategori` | 2nd taxonomy level; FK not wired. |
| `Negeris` | `Id`, `NamaNegeri`, `KodNegeri` | States reference. |
| `Cawangans` (`CawanganJBG`) | `Id`, `NamaCawangan`, `KodCawangan`, `StatusJBG` (bool), `Alamat1-3`, `Poskod`, `NoTel`, `IdNegeri` | JBG branches. No Bilik/room child. |
| `JKMs` | `Id`, `NamaCawanganJKM`, `KodCawanganJKM`, `StatusCawanganJKM`, `Alamat1-3`, `Poskod`, `NoTel`, `IdNegeri` | Welfare offices. |
| `Penjaras` | `Id`, `NamaCawanganPenjara`, `KodCawanganPenjara`, `StatusCawanganPenjara`, `Alamat1-3`, `Poskod`, `NoTel`, `IdNegeri` | Prisons. |

**Tables the frontend implies but the backend LACKS:** `Bilik` (rooms), `KategoriKes`/`SubKategori`
(tree levels), `MaklumBalas` + `JawapanBatch1` (feedback), `HariCutiOff` (holidays — umum/negeri),
`PenutupanOperasi` (operation closures), `Jawatan` (positions), `Tetapan` (settings),
`CawanganMahkamah` (courts), `Laporan` aggregates, document-upload (`DokumenSokongan`).

Migrations: `20240902040532_InitialData`, `20240903043506_UpdateTable` (added domain tables +
renamed Pengguna cols), `20240903070045_PenjaraAndJkmTable`, `20240904124302_AddSlotTemuJanjiTable`.

---

## 8. Statuses & transitions

### KhidmatNasihat status (`StatusKN`, string)
```
DRAF ──(perakuan/submit)──▶ BAHARU ──(PKN accepts: SetKeputusanPegawaiTerimaKes + PUT KN)──▶ DALAM PROSES ──(attendance confirmed)──▶ SELESAI
   │                                                                                                                                       
   └──(cancel)──▶ BATAL          (BAHARU/DALAM PROSES can also ──▶ BATAL)
```
- `DRAF` (warning badge) — editable/deletable, blocks starting a new application.
- `BAHARU` (primary) — submitted, awaiting officer assignment/decision.
- `DALAM PROSES` (warning) — PKN accepted the case.
- `SELESAI` (success) — session completed (with/without attendance); feedback unlocked.
- `BATAL` (dark) — cancelled.
- Also surfaced: `DIKECUALIKAN` (exempted) badge in the list filter.

### TemuJanji decision/attendance flags (written by officer flow)
- PKN decision: `isTerimaKes` (true/false) + `alasan` → `SetKeputusanPegawaiTerimaKes`.
- Attendance: `isPelangganHadir` (true/false) + `ulasanPegawai` → `PUT /TemuJanji/{id}`.
- `TemuJanji.Status` (string) + `SlotTemujanji.Status` (bool, slot taken/free).

### Payment
- `StatusBayaran` (bool) + `nomborResit`; `JumlahBayaran` ∈ {0, 10, 260}; `isSumbangan` flag.

---

## 9. Feedback (Maklum Balas)

- Public form: `pages/khidmatnasihat/borang-maklumbalas/soalan-maklumbalas.vue` (layout `global`,
  opened with `?khidmatNasihatId=...`). Submitted to **`POST /MaklumBalas/{khidmatNasihatId}`**.
- Questions (this is the **batch-1** feedback set — matches memory `port-iguaman-janjitemu` batch
  naming; read back as `maklumBalas.jawapanBatch1s`):
  1. **Soalan 1** (multi-checkbox, ≥1 required) — how did you hear about us:
     `soalan1A` Portal/laman JBG, `soalan1B` Media sosial, `soalan1C` Rujukan keluarga/rakan,
     `soalan1D` Jabatan/agensi kerajaan, `soalan1E` Lain-lain (+`soalan1LainLain` free text).
  2. **Soalan 2** (radio, required) — service rating `soalan2A` ∈ {`CEMERLANG`, `BAIK`,
     `KURANG MEMUASKAN`}.
  3. **Soalan 3** (textarea) — `soalanCadangan` improvement suggestions.
- Result view (officer/read-only): `.../keputusan-maklumbalas/_id.vue` — reads
  `GET /KhidmatNasihat/{id}` → `maklumBalas.jawapanBatch1s`.
- Feeds the **Tahap Kepuasan Pelanggan** and **Cara Mengetahui Mengenai JBG** reports.
- **Backend has no `MaklumBalas` controller/table** — feedback is frontend-spec only.

---

## 10. Calendar config: holidays (cuti) + closures (penutupan operasi) + sessions (sesi)

All under `pages/kalendar/`. **None implemented in backend** (`HariCutiOff` controller absent).

| Page | Role | Endpoints |
|------|------|-----------|
| `kalendar-cuti.vue` (Cuti Umum / public holidays) | SUPERADMIN | `POST /HariCutiOff/CreateHariCutiOffUmum`, `GET /HariCutiOff/GetAllHariCuti`, `GET /HariCutiOff/GetHariCutiGrouped`, `DELETE /HariCutiOff/{id}`; fields `namaCuti`, `tarikh` |
| `kalendar-cuti-negeri.vue` (Cuti Negeri / state holidays) | SUPERADMIN | `POST /HariCutiOff/CreateHariCutiOffForNegeri` (per `idNegeri`) |
| `penutupan-hari-operasi.vue` (operation closures) | PEMBANTU TADBIR | `POST /HariCutiOff/CreatePenutupanOperasi`, `PUT /HariCutiOff/UpdatePenutupanOperasi/{id}`, `GET /HariCutiOff/GetAllPenutupanOperasi`; fields `tarikhMulaDate/Time`, `tarikhAkhirDate/Time` |
| `penetapan-sesi-janji-temu.vue` (session/slot template) | admin | session template per branch: `Pilih_Slot`, `modPilihSlot` (hari/minggu/bulan), `idNegeri`, `hari.hariAktif` (active-days mask A-I-S-R-K-J-S = Ahad..Sabtu), `sesi`, `status_semasa` |

Holidays/closures are checked client-side in slot booking and slot generation (a date on a
holiday → "jatuh pada hari cuti", booking lead-time skips weekends).

---

## 11. Reports (Laporan)

Menu gated to `SUPERADMIN, PEMBANTU TADBIR, PEGAWAI KHIDMAT NASIHAT, PENGURUSAN`. Each report =
a filter/summary page + a printable `laporan-statistik.vue`. Export via `xlsx` + `vue-html-to-paper`.

| Report | Path | Backend endpoint (mostly NOT implemented) |
|--------|------|-------------------------------------------|
| Statistik KN Mengikut Cawangan (by branch) | `/laporan/...-mengikut-cawangan` | `GET /Laporan/LaporanStatistikKhidmatNasihatSyariahByCawangan?month&year` |
| Statistik KN Kategori Kes (by case category) | `/laporan/...-kategori-kes` | `GET /Laporan/LaporanStatistikKhidmatNasihatSivil?month&year` |
| Statistik KN Sub Kategori | `/laporan/...-subkategori` | `GET /Laporan/GetLaporanStatistikJenisKhidmatNasihatSivilSubKategori?month&year` |
| Statistik Pendaftaran KN (registrations) | `/laporan/...-pendaftaran-khidmat-nasihat` | (frontend aggregate) |
| Pandangan Undang-Undang (legal opinions) | `/laporan/...-pandangan-undang-undang` | (frontend aggregate) |
| Statistik Cara Mengetahui Mengenai JBG (how-heard) | `/laporan/...-cara-mengetahui-mengenai-jbg` | from MaklumBalas soalan 1 |
| Statistik Tahap Kepuasan Pelanggan (satisfaction) | `/laporan/...-tahap-kepuasan-pelanggan` | from MaklumBalas soalan 2 |
| Mengikut Kaum/Jantina (race/gender) | `/laporan/...-mengikut-kaum-jantina` | (frontend) |
| (legacy backups) Sivil/Jenayah/Syariah by category | `/laporan/backup/...` | mixed |

Report dimensions in play: month/year, cawangan (branch), negeri, kategori → kategori-kes →
sub-kategori, kaum/jantina/kumpulan-umur, jenis permohonan, how-heard, satisfaction.

---

## 12. Reference data domains (geography & venues)

- **Negeri** (states): `GET /Negeri`, admin CRUD `pages/tetapan/senarai-negeri.vue` →
  `/Negeri` POST/PUT/DELETE. Public variant `GET /Public/Negeri`.
- **Cawangan JBG** (legal-aid branches): `pages/cawangan/jbg/`, full CRUD `/CawanganJBG/*`,
  has child Bilik (rooms) + per-branch slot/calendar.
- **Cawangan Penjara** (prisons): `pages/cawangan/penjara/`, `/CawanganPenjara` (read in dashboard).
- **Cawangan JKM** (welfare): `pages/cawangan/jkm/`, `/CawanganJKM`.
- **Cawangan Mahkamah** (courts): `pages/cawangan/mahkamah/`, `/CawanganMahkamah` (used in wakil flow + reports).
- **Jawatan** (positions): `pages/tetapan/senarai-jawatan.vue`, `/Jawatan` CRUD.
- **Peranan** (roles): `pages/tetapan/senarai-peranan/`, `/Peranan` CRUD + per-user access
  `pages/tetapan/senarai-peranan/akses-pengguna/_id.vue`.
- **Tetapan** (generic settings): `/Tetapan/getTetapan`, `/Tetapan/createTetapan/{...}`.
- **User management** by audience: `pages/pengguna/{awam,jbg,penjara,jkm}/`,
  detail `pengguna/jbg/butiran.vue`.

---

## 13. Notifications / email / SMS / integrations

- **No real notification, email, or SMS infrastructure** in either repo. No SMTP, SendGrid,
  Twilio, queue, or background job. The only "notification" is an in-app badge/bell in
  `layouts/default.vue` (counts pending `khidmatNasihatData`, links to `/khidmatnasihat`).
- Implied-but-unbuilt comms: forgot-password (`/Public/RequestResetPassword`), feedback link
  delivery — all stubs.
- **Integrations**: none external. Backend is self-contained (PostgreSQL + JWT). No JKM/Penjara/
  Mahkamah system integration — those are just local reference tables. `dukcapil`/MyKad parsing
  is purely client-side IC-digit math (`kiraanUmur`).
- Backend security posture: CORS locked to `https://iguaman-bheuu.gov.my` but
  `SetIsOriginAllowed(_ => true)` (effectively open); HSTS + HTTPS redirect on; Swagger in
  Local/Dev. Frontend sets `X-XSS-Protection`, `X-Frame-Options: SAMEORIGIN`,
  `X-Content-Type-Options: nosniff`; CSP commented out.

---

## 14. Tech stack summary

| Layer | Backend | Frontend |
|-------|---------|----------|
| Language | C# / .NET 8 | JS (Vue 2 options API) |
| Framework | ASP.NET Core Web API | Nuxt 2.15 (SPA, `ssr:false` plugins) |
| DB / ORM | PostgreSQL 5432 `IGuaman` / EF Core 8 (Npgsql) | — |
| Auth | ASP.NET Identity (Guid) + JWT HS256, `TokenService` | `@nuxtjs/auth-next` local + `@nuxtjs/axios` (base `localhost:5081/api`) |
| UI | Swagger | BootstrapVue 2, SweetAlert2, ApexCharts/Chart.js, vue-select, vuelidate, moment, xlsx, vue-html-to-paper |
| Seed | `SeedData` SUPERADMIN | — |
| Deploy | Dockerfile + docker-compose | Dockerfile + docker-compose, `.gitlab-ci.yml` |

---

## 15. Consolidation notes (for the 2in1 rewrite)

1. **Rebuild from the FRONTEND contract, not the backend.** The backend lacks the category
   tree, slot engine, feedback, holidays, closures, reports, rooms, and document upload that the
   product needs. Port the Nuxt API contracts (§§3–11) as the requirements.
2. **KN lifecycle to replicate exactly:** `DRAF → BAHARU → DALAM PROSES → SELESAI` (+ `BATAL`,
   `DIKECUALIKAN`), with the assign-PKN → accept/reject → attendance → complete officer chain.
3. **Eligibility/saringan** = income test at **RM 50,000**, with three fee tiers (RM 0 free /
   prison / JKM; RM 10 standard; RM 260 sumbangan) and a criminal-companion bypass.
4. **Category tree is 3-level** (Kategori → KategoriKes → SubKategori) — NOT the flat backend
   model, and NOT `ref_kes` litigation taxonomy (per memory `ref-kes-not-kn-tree`).
5. **Slot model** needs rooms (Bilik), working/lunch hours, per-slot minute weightage, weekend
   inclusion, holiday/closure awareness, and a 4-working-day booking lead time — none of which
   exist server-side in legacy.
6. **Roles**: SUPERADMIN, PEMBANTU TADBIR, PEGAWAI KHIDMAT NASIHAT, PELANGGAN, PENGURUSAN
   (+ prison/JKM officer variants). Move authorization to the SERVER (legacy enforced it only
   client-side — a security hole).
7. **Feedback batch-1** (3 questions) → satisfaction + how-heard reports. Aligns with the
   2in1 "batch-12 maklum_balas" already in the codebase (per recent commits).
8. No notifications/email/SMS exist to port — design fresh if required.

---

## 16. Key source files (absolute paths)

### Backend (`c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/be_iguaman-master/`)
- `Program.cs` — DI, Identity, JWT, CORS, auto-migrate + SUPERADMIN seed.
- `appsettings.json` — Npgsql conn `IGuaman@5432`, JWT 24h, CORS `iguaman-bheuu.gov.my`.
- `Controllers/{Auth,KhidmatNasihat,TemuJanji,SlotTemuJanji,Kategori,Pengguna,Peranan,Negeri}Controller.cs`
- `Services/{KhidmatNasihat,TemuJanji,SlotTemujanji,Pengguna,Peranan,Kategori,Negeri,Token}Service*.cs`
- `Repositories/AppDbContext.cs`; `Models/**`; `Migrations/2024090*`; `common/{SeedData,AppSettings,SessionManager,Constants/*}.cs`
- `docs/system-overview.html` — backend's own architecture doc (business objective source).

### Frontend (`c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/fe-iguaman-master/`)
- `nuxt.config.js`, `middleware/authenticated.js`, `utils/accessControl.js`, `store/index.js`, `layouts/default.vue` (sidebar IA + role gates).
- `pages/khidmatnasihat/permohonan-baru.vue` + `components/permohonan-baru/{diri-sendiri,sebagai-wakil,bayaran,slot-janji-temu,perakuan}.vue` (4-step flow).
- `pages/khidmatnasihat/index.vue`, `.../pengesahan-janjitemu/_id.vue`, `.../kemaskini-permohonan/_id.vue`, `.../borang-maklumbalas/{soalan-maklumbalas.vue, keputusan-maklumbalas/_id.vue}`.
- `pages/cawangan/jbg/{index,bilik/_id,slotJanjiTemu/_id,kalendar/_id}.vue`; `pages/kalendar/{kalendar-cuti,kalendar-cuti-negeri,penutupan-hari-operasi,penetapan-sesi-janji-temu}.vue`.
- `pages/tetapan/senarai-kategori/**` (3-level tree CRUD), `pages/laporan/**`, `pages/dashboard.vue`, `pages/{log-masuk,daftar-pengguna,index}.vue`.
