# Map 07 — 2in1: Khidmat Nasihat + Janji Temu + Awam (citizen portal)

**System:** 2in1 (NEW consolidated Laravel 13 / MySQL app) — `iGuaman/2in1`
**Scope:** Legal-advisory (Khidmat Nasihat) + appointment engine (Janji Temu) + public citizen portal (Awam). Batches 7–13 as actually built.
**Read-only audit.** Source paths are absolute under the project root.

---

## 0. Where the logic lives (important)

There is **no `app/Services/` directory**. All "service" logic lives in **`app/Support/`** (the project's convention). The classes named in the brief map as follows:

| Brief name | Actual file |
|---|---|
| SlotAvailabilityService | `app/Support/SlotAvailabilityService.php` |
| LaporanKnService | `app/Support/LaporanKnService.php` |
| (KN create/slot lifecycle) | `app/Support/KhidmatNasihatService.php` |
| (officer processing) | `app/Support/KhidmatProsesService.php` |
| (slot generation) | `app/Support/SlotGenerator.php` |
| (fee computation) | `app/Support/KhidmatBayaran.php` (pure, no DB) |
| (state-holiday bitmask) | `app/Support/CutiNegeri.php` |

Controllers are deliberately thin; business rules + status guards sit in the Support classes. Audit writes go through `app/Support/Audit.php`.

---

## 1. The three subsystems at a glance

```
CITIZEN (Awam, user_type=awam)              STAFF (user_type=staff)
  /awam/daftar  /awam/login  (IC login)       /system  (email login)
        |                                            |
   /awam dashboard (PortalController)          KhidmatNasihatController (wizard, khidmat.manage)
        |                                            |
   saringan -> permohonan/baharu -> store      saringan -> baharu -> store (DIRI_SENDIRI or SEBAGAI_WAKIL)
        |   (DIRI_SENDIRI only)                      |
        +--> KhidmatNasihatService.create + bookSlot <--+   (SHARED service)
                          |
                   SlotAvailabilityService  <- slot supply from SlotGenerator
                          |
                   temu_janji (MENUNGGU)
                          |
        KhidmatProsesController (khidmat.proses) — officer worklist
          assign PKN (BAHARU->DALAM_PROSES)
          terima/tolak/kehadiran/selesai (temu_janji lifecycle)
          buka-kes (SELESAI KN -> forms litigation case)
                          |
                   status_kn = SELESAI
                          |
        MaklumBalasController (PUBLIC link) — satisfaction feedback
        LaporanKhidmatNasihatController — 8 statistical reports
```

---

## 2. Routes (all real, from `routes/web.php`)

### Public / guest
| Method · URI | Name | Controller |
|---|---|---|
| GET `/awam/daftar` | `awam.daftar` | `Awam\PublicAuthController@showDaftar` |
| POST `/awam/daftar` (throttle 6/1) | `awam.daftar.store` | `…@daftar` |
| GET `/awam/login` | `awam.login` | `…@showLogin` |
| POST `/awam/login` (throttle 10/1) | `awam.login.attempt` | `…@login` |
| POST `/awam/logout` (auth) | `awam.logout` | `…@logout` |
| GET `/maklum-balas/{no_permohonan}` (throttle 6/1) | `maklum-balas.show` | `MaklumBalasController@show` |
| POST `/maklum-balas/{no_permohonan}` (throttle 6/1) | `maklum-balas.store` | `…@store` |

### Citizen portal — `middleware(['auth','permission:awam.portal'])` prefix `awam`
| Method · URI | Name | Controller |
|---|---|---|
| GET `/awam` | `awam.dashboard` | `Awam\PortalController@index` |
| GET `/awam/permohonan/saringan` | `awam.permohonan.saringan` | `Awam\PermohonanController@saringan` |
| POST `/awam/permohonan/saringan` | `awam.permohonan.saringan.semak` | `…@saringanSemak` |
| GET `/awam/permohonan/baharu` | `awam.permohonan.create` | `…@create` |
| POST `/awam/permohonan` (throttle 10/1) | `awam.permohonan.store` | `…@store` |
| GET `/awam/permohonan/{khidmat}` | `awam.permohonan.show` | `…@show` |
| POST `/awam/permohonan/{khidmat}/batal` | `awam.permohonan.batal` | `…@cancel` |
| POST `/awam/permohonan/{khidmat}/jadual-semula` | `awam.permohonan.reschedule` | `…@reschedule` |
| POST `/awam/permohonan/{khidmat}/lampiran` (throttle 20/1) | `awam.lampiran.store` | `…@upload` |
| GET `/awam/permohonan/{khidmat}/lampiran/{fail}/muat-turun` | `awam.lampiran.download` | `…@download` |

### Slot JSON — shared `permission:slot.view|awam.portal`
| GET `/slot/tarikh` | `slot.tarikh` | `SlotController@availability` |
| GET `/slot/masa` | `slot.masa` | `SlotController@times` |

### Staff KN wizard — `permission:khidmat.view` then `permission:khidmat.manage`
`khidmat.index`, `khidmat.show`, `khidmat.saringan`(+semak), `khidmat.create`, `khidmat.store`, `khidmat.edit`, `khidmat.update`.

### Staff KN officer processing (Batch 11) — `permission:khidmat.proses`
`khidmat.proses.index`, `…assign`, `…temu.terima`, `…temu.tolak`, `…temu.kehadiran`, `…temu.selesai`, `…buka-kes`.

### Calendar/slot admin — `permission:slot.manage`
`slot.index`, `slot.sesi`(updateSession), `slot.generate`, `slot.destroy`, `penutupan.index/create/store/destroy`.

### Read-only month calendar — `permission:slot.view`
`jadual.index` (`JadualJanjiTemuController`).

### Reference masters
- Cawangan + Bilik — `permission:selenggara.cawangan` (`cawangan.*`, `cawangan.bilik.store/destroy`).
- Kategori KN tree — `permission:selenggara.kategori_kn` (`kategori-kn.*`, 3-level CRUD).
- Cuti Negeri — `permission:selenggara.cuti` (`cuti-negeri.*`).

### 8 KN reports — `permission:laporan.view` prefix `laporan-kn`
`index`, `pandangan-uu`(+`.excel`), `cara-mengetahui`, `mengikut-cawangan`, `mengikut-kategori`, `mengikut-subkategori`, `pendaftaran`(+`.excel`), `kepuasan`, `kaum-jantina`.

---

## 3. Citizen self-service flow (Awam) — BUILT

1. **Register / login by IC.** `PublicAuthController`. Login uses **No. KP (`nokp`)** not email, via `Auth::attempt(['nokp','password','user_type'=>awam,'is_active'=>true])`. Register creates `user_type=awam`, `role=awam`, `must_change_password=false`, `assignRole('awam')`, then auto-login. Both forms guarded by a **session math captcha** (`captcha_sum`) + register has a **honeypot** (`website` field `prohibited`). Throttled at routes.
2. **Dashboard** (`PortalController@index`): lists the citizen's own KN rows (`where id_pengguna = auth id`), paginated 10, showing `no_permohonan`, `status_kn`, appointment date. View `awam/dashboard.blade.php`.
3. **Saringan (screening)** (`PermohonanController@saringan`/`saringanSemak`): validates `saringan_jenis` (sivil_syariah|pendamping_jenayah), two eligibility questions (`tiada_nasihat_terdahulu`, `tiada_perkara_dikecualikan` — both must be "Ya"), `pendapatan_bawah_had`, `terima_terma` (accepted). Disqualifying answer → redirect back with `saringan_gagal`. Pass → `session('awam_saringan')` = `{jenis, lulus:true, sumbangan}` then forward to create. `sumbangan` = sivil/syariah + income above had.
4. **Permohonan baharu** (`…@create`/`store`): wizard view `awam/permohonan/form.blade.php` with a **live slot picker** (JS `fetch` to `slot.tarikh` then `slot.masa`). `AwamPermohonanRequest`: draft vs `aksi=hantar`. On `hantar`, server **re-asserts** `session('awam_saringan.lulus')===true` (403 otherwise). `store` runs in a DB transaction: `KhidmatNasihatService::create()` then `bookSlot()` if submitting. Sets `id_pengguna` = current user, `id_pengenalan_mangsa` defaults to `user->nokp`, `jenis_permohonan=DIRI_SENDIRI`, fee via `KhidmatBayaran::kira()`, status `BAHARU` (or `DRAF`). Clears `awam_saringan` session on submit. Audit `khidmat_nasihat` INSERT.
5. **Show + manage** (`…@show`): `Gate::authorize('view')` (owner-gated). Displays appointment, fee, documents. Self-service:
   - **Cancel** (`@cancel`): `Gate update` + `assertCancellable` (temu must not be HADIR/TIDAK_HADIR/SELESAI/BATAL and not past) → `releaseSlot` + `status_kn=BATAL`.
   - **Reschedule** (`@reschedule`): `AwamRescheduleRequest` (`tarikh after:today`) → `service->reschedule` (release + rebook). View only shows the form when temu is MENUNGGU/DISAHKAN and future.
   - **Upload** (`@upload`): `AwamLampiranRequest` (`mimes:pdf,jpg,jpeg,png`, `max:5120`=5MB), throttled 20/1, stored on `local` disk dir `lampiran`; `file_type` is MIME-derived (`$file->extension()`), not client-controlled. Row in `uploaded_files` (`id_khidmat`). Audit INSERT.
   - **Download** (`@download`): `Gate view` + row must belong to this KN (`id_khidmat`) → streamed from `local` disk.
   - **Maklum Balas link**: shown only when `status_kn===SELESAI`; links to public `maklum-balas.show` by `no_permohonan`.

**Ownership policy** (`KhidmatNasihatPolicy`): `view`/`update` both require `user->isAwam() && kn.id_pengguna === user.id`. Staff-created KN have `id_pengguna=null` and are **not** visible in the citizen portal.

---

## 4. Staff KN create wizard — BUILT

`KhidmatNasihatController` (`khidmat.manage`). Same shared `KhidmatNasihatService`. Differences vs citizen path:
- Supports **both** `DIRI_SENDIRI` and `SEBAGAI_WAKIL` (PENJARA / JKM / MAHKAMAH). `mapInput()` branches on `isWakil()`/`isMahkamah()`.
- Eligibility gate (`assertSaringanGate`): enforced **only** for non-wakil submits; reads `session('saringan.lulus')` (authoritative — ignores client hidden field). Wakil + draft bypass.
- Fee (`KhidmatBayaran::kira`): wakil PENJARA/JKM → RM0; PENDAMPING JENAYAH/GUAMAN → RM0; SIVIL/SYARIAH + income > RM50,000 → RM260 (Laluan Sumbangan); MAHKAMAH wakil pays normal matrix; default RM10; `is_percuma` overrides all → RM0.
- `no_permohonan` generated as `KN/{kod}/{year}/{seq}` in `KhidmatNasihatService::nextNoPermohonan`.
- Edit/update allowed **only while DRAF** (`abort_unless status_kn===DRAF`).
- Form `khidmat-nasihat/form.blade.php` cascades kategori→kes→subkategori client-side from a flat tree, and wires the same slot picker (`slot.tarikh`/`slot.masa`).

---

## 5. Janji Temu (appointment engine) — BUILT

### Slot supply: `SlotGenerator` (`slot.manage`)
Generates `slot_temu_janji` rows per (cawangan, optional bilik) over a date range, stepping `tempoh_slot_minit` between `masa_buka`/`masa_tutup` (defaults 09:00–17:00, 30 min). Skips weekends (branch `hari_minggu` or Sat/Sun), state holidays (`ref_cuti`+`CutiNegeri` matched on `cawangan.negeri_id`), and closures (`penutupan_operasi`). Idempotent on `(cawangan,bilik,date,masa_mula)`; `MAX_RANGE_DAYS=180`. Bulk `insert()` in chunks of 500. `SlotGenerationController` also exposes **destroy** (delete unbooked slots) and **updateSession** (branch weekend/hours/slot-length config).

### Availability: `SlotAvailabilityService`
A date is bookable iff ALL: (1) ≥ today + `MIN_WORKING_DAYS=4` **working** days (weekends skipped while counting); (2) not weekend; (3) not a state holiday; (4) not inside a closure; (5) ≥1 open slot (`is_temujanji=false`,`status_aktif=true`). `availableDates()`/`availableTimes()` feed the JSON picker; `dayStatuses()` feeds the read-only calendar (weekend/holiday/closure/open). `today` is injectable (testable). NB the lead-time uses ISO weekday literals `[6,7]` deliberately (a code comment warns Carbon::SUNDAY=0 would leave Sundays bookable).

### Booking: `KhidmatNasihatService::bookSlot/releaseSlot/reschedule`
`bookSlot` locks an open slot `FOR UPDATE`, creates `temu_janji` (status `MENUNGGU`), flips `slot.is_temujanji=true`, and back-links `khidmat.id_temu_janji`. Race-safe (422 "Slot tidak lagi tersedia" if gone). `releaseSlot` frees the slot + sets temu `BATAL`. `reschedule` = release + rebook in one transaction.

### Read-only calendar: `JadualJanjiTemuController` (`slot.view`)
Month grid per cawangan; overlays booked `temu_janji` (excludes BATAL) on `dayStatuses()`. Malay month/weekday labels. View `jadual/index.blade.php`.

---

## 6. Officer processing — BUILT (`KhidmatProsesService` + `KhidmatProsesController`, `khidmat.proses`)

**Branch isolation is explicit** (KN keys on `cawangan_id`, has **no CawanganScope**): a staff officer pinned to `user->cawangan` (and lacking `cawangan.view-all`) is limited to that branch's id; view-all / no-branch sees all. Worklist `index` paginates 25 with filters (status_kn, pegawai, kategori, date range, q) + 3 dashboard count tiles (BAHARU/DALAM_PROSES/SELESAI).

**Actions** (all guarded; illegal transition throws `RuntimeException` → redirect-back-with-error; writes use `lockForUpdate` in a transaction):

| Action | Guard | Effect |
|---|---|---|
| `assign` (Agih PKN) | `status_kn` must be BAHARU | set `id_pegawai_kn`, `tarikh_proses=now`, `status_kn=DALAM_PROSES`. Comment notes this **fixes a legacy bug** where `CreateTemuJanji` dropped `IdPegawaiKN`. |
| `terima` | temu MENUNGGU | temu → DISAHKAN |
| `tolak` | temu MENUNGGU | temu → BATAL, record `ulasan_pegawai` |
| `kehadiran` | temu DISAHKAN | temu → HADIR or TIDAK_HADIR |
| `selesai` | temu HADIR | temu → SELESAI **and** `status_kn=SELESAI` |
| `bukaKes` | `status_kn=SELESAI` AND `id_forms===null` | create a `forms` litigation row (prefilled from KN), back-link `id_forms`; generate `no_fail` via `NoFailGenerator` if blank; `normalizeNokp()` strips dashes + caps at 12 chars to fit the legacy `forms.nokp` column. |

UI: `khidmat-nasihat/proses-index.blade.php` + partial `partials/proses-actions.blade.php` render the contextual buttons (Agih / Sahkan / Tolak(prompt reason) / Hadir / Tidak Hadir / Selesai / Buka Kes / Lihat Kes).

---

## 7. Maklum Balas (feedback) — BUILT

`MaklumBalasController` — **PUBLIC, no auth** (locked design decision), throttled 6/1. `show(no_permohonan)`: if KN not SELESAI → `belum-tersedia` view; if feedback exists → `terima-kasih`; else → `borang`. `store`: re-checks SELESAI + not-already-submitted server-side, persists `maklum_balas` (`soalan_1a..1e` checkboxes, `soalan_1_lain_lain`, `soalan_2a` enum CEMERLANG/BAIK/KURANG_MEMUASKAN, `soalan_cadangan`, `dihantar_dari_ip`). **One per KN**: DB unique index on `khidmat_nasihat_id` + app guard; a concurrent duplicate (`ER_DUP_ENTRY` 1062) is swallowed as success. `MaklumBalasRequest` requires ≥1 soalan_1 box and `soalan_1_lain_lain` required_if 1e.

---

## 8. The 8 KN reports — BUILT (`LaporanKhidmatNasihatController` + `LaporanKnService`, `laporan.view`)

Branch scope resolved by `resolveBranchFilter()` reusing `KhidmatProsesService::branchFilter()` (pinned officer forced to branch; view-all may narrow). Month/year grouping = `MONTH/YEAR(created_at)`.

| # | Report | Route name | Shape | Excel |
|---|---|---|---|---|
| 1 | Pandangan Undang-Undang | `pandangan-uu` | detail list (paginate 30) | `PandanganUuExport` (.xlsx) |
| 2 | Cara Mengetahui JBG | `cara-mengetahui` | bucket counts (soalan_1a–e via maklum_balas join) | print CSS |
| 3 | Mengikut Cawangan | `mengikut-cawangan` | branch × 12-month pivot | print CSS |
| 4 | Mengikut Kategori Kes | `mengikut-kategori` | kategori × 12-month pivot | print CSS |
| 5 | Mengikut Sub Kategori | `mengikut-subkategori` | subkategori × 12-month pivot | print CSS |
| 6 | Pendaftaran Khidmat Nasihat | `pendaftaran` | detail list (paginate 30) | `PendaftaranKnExport` (.xlsx) |
| 7 | Tahap Kepuasan Pelanggan | `kepuasan` | satisfaction counts (soalan_2a via join) | print CSS |
| 8 | Mengikut Kaum/Jantina | `kaum-jantina` | bangsa × (Lelaki/Perempuan) pivot | print CSS |

Reports 2 & 7 `JOIN maklum_balas -> khidmat_nasihat`, scoped on the KN's `cawangan_id`+`created_at`. Detail exports go through maatwebsite/Excel.

---

## 9. Statuses + transitions

### `khidmat_nasihat.status_kn` (`KhidmatNasihat::STATUS_KN`)
`DRAF → BAHARU → DALAM_PROSES → SELESAI`, plus `BATAL`.
- `DRAF`: draft save (no slot booked). Editable only in DRAF.
- `BAHARU`: submitted + slot booked (temu MENUNGGU).
- `DALAM_PROSES`: PKN assigned.
- `SELESAI`: appointment attended + completed. Unlocks maklum-balas + buka-kes.
- `BATAL`: citizen-cancelled (releases slot).

### `temu_janji.status` (`TemuJanji::STATUS`, enforced in `KhidmatProsesService::TEMU_TRANSITIONS`)
`MENUNGGU → DISAHKAN → {HADIR | TIDAK_HADIR}`, `HADIR → SELESAI`; `MENUNGGU → BATAL` (tolak); cancel/reschedule set/release via service. Allowed-from sets are hard-coded; anything else throws.

### Payment
`status_bayaran` (boolean) + `jumlah_bayaran` exist and are computed/stored, but there is **no payment-confirmation UI/route** — see gaps.

---

## 10. Completion + hanging states (audit findings)

| State | Observation | Severity |
|---|---|---|
| **No `TIDAK_HADIR` terminal handling** | When `kehadiran(false)` sets temu `TIDAK_HADIR`, `status_kn` stays `DALAM_PROSES` forever. No reschedule-after-no-show, no auto-close. Officer cannot move it to SELESAI (guard requires HADIR) and cannot reopen. **Hanging state.** | HIGH |
| **Payment never confirmed** | `jumlah_bayaran` computed, `status_bayaran` defaults false; no route/controller flips it. Fee is informational only. | MEDIUM |
| **`DALAM_PROSES` with rejected appointment** | `tolak` sets temu BATAL but leaves `status_kn` unchanged (BAHARU or DALAM_PROSES). The KN is now appointment-less; no rebook path on the staff side. | MEDIUM |
| **Citizen reschedule lead-time mismatch** | `AwamRescheduleRequest` only enforces `after:today`; `bookSlot` then requires a real open slot, so an out-of-window date 422s. UX-rough but not a data hole. | LOW |
| **Staff-created KN invisible to citizen** | Staff wizard sets `id_pengguna=null`; such rows never appear in the Awam portal even if the same person later registers. By design, but worth flagging for consolidation. | LOW |
| **`khidmat_nasihat` has no CawanganScope** | Branch isolation is re-implemented in 3 places (`KhidmatProsesService::branchFilter`, `LaporanKnService::resolveBranchFilter`, report queries). Consistent today but fragile — any new KN query must remember to scope. | INFO |
| **No DB FKs on key links** | `id_temu_janji`, `id_forms`, `id_pegawai_kn` (on temu_janji), `id_mahkamah`, `id_negeri` are unconstrained columns (integration/legacy convention). Referential integrity is app-enforced only. | INFO |

---

## 11. Key tables / migrations

| Table | Migration |
|---|---|
| `cawangan`, `bilik` | `2026_06_30_100001_create_cawangan_and_bilik_tables.php` (+ `…120002_add_session_config_to_cawangan`) |
| `khidmat_nasihat` | `…110001_create_khidmat_nasihat_table` (+ `110002` income, `110003` wakil/saringan, `110004` officer-processing: `id_pegawai_kn`,`tarikh_proses`,`id_forms`) |
| `slot_temu_janji`, `temu_janji`, `penutupan_operasi` | `…120001_create_temu_janji_and_slot_tables` |
| `maklum_balas` | `…120010_create_maklum_balas_table` (unique `khidmat_nasihat_id`) |
| `uploaded_files` (+`id_khidmat`) | `…000010_add_id_khidmat_to_uploaded_files` |
| `awam` role + `awam.portal` perm | `…130002_seed_awam_role_permission` (NOT in `RolePermissionSeeder::MATRIX` — separate migration) |
| KN category tree | `ref_kategori_kn` / `ref_kategori_kes_kn` / `ref_subkategori_kn` |

---

## 12. Models

`KhidmatNasihat` (status consts, relations: pengguna, pegawaiKn, forms, mahkamah(), cawangan, kategori, subkategori, temuJanji, maklumBalas) · `TemuJanji` · `SlotTemuJanji` · `Cawangan` (`weekendDays()`, JENIS=[JBG,JKM,PENJARA], hasMany bilik) · `Bilik` · `RefKategoriKn`/`RefKategoriKesKn`/`RefSubkategoriKn` (3-level tree) · `MaklumBalas` · `PenutupanOperasi` · `RefCuti` (shared with Cuti Umum; `CutiNegeri` bitmask, 16 states) · `User` (`TYPE_AWAM`/`TYPE_STAFF`, `isAwam`/`isStaff`, `homeRoute`, IC `nokp` login).
