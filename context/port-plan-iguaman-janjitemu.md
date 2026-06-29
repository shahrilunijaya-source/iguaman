# Port Plan — iGuaman Janji Temu / Khidmat Nasihat → 2in1

**Goal:** Port the standalone *Sistem Janji Temu Khidmat Nasihat* (legal-advice appointment + advisory system) into the existing `2in1` Laravel monolith as a new subsystem, following the same legacy-parity discipline used for the peguam-panel + rekod-kes batches.

**Source repos**
- `be_iguaman-master` — ASP.NET Core 8 + EF Core 8 + **PostgreSQL** + ASP.NET Identity + JWT.
- `fe-iguaman-master` — Nuxt 2 / Vue 2 + BootstrapVue + ApexCharts.

**Target:** `2in1` — Laravel 13 + Blade + MySQL + plain `SystemAuthController` auth.

**Supporting maps** (full column/route/field detail, in scratchpad):
- `be-map.md` — .NET schema, entities, 26 endpoints, business rules.
- `fe-map.md` — 49 pages, wizard fields, reports, roles.
- `2in1-map.md` — existing models, masters, role/scope system, layouts.

---

## 1. Verdict

- **No code reuse.** Source is C#/.NET + Nuxt/Vue; target is Laravel/Blade. 100% rewrite.
- **Domain is adjacent, not duplicate.** Source = public advisory applications + appointment booking. 2in1 = internal panel-lawyer + case records. Both are JBG (Legal Aid) systems and share masters.
- **Feasible as a port project**, sequenced into batches. Bigger than any single batch done so far.

---

## 2. Architecture decision

Integrate into the 2in1 monolith (one app, one DB, one auth) — **not** a side-by-side service.

| Concern | Decision |
|---|---|
| Backend | Laravel controllers + Eloquent (thin controllers, FormRequests), matching existing 2in1 pattern. |
| Frontend | Blade + existing `system.css` shell. Rebuild Vue pages as Blade views. |
| New tables | Laravel Blueprint migrations (the new subsystem is greenfield — do **not** add to the raw `legacy-domain.sql` dump). |
| Auth | Reuse `users` + `SystemAuthController`. Source logs in by IC (No. Pengenalan); 2in1 logs in by email — keep 2in1 email login for staff. **Public applicant login is a decision point (§7).** |
| Scoping | Apply `CawanganScope` to branch-bound new models. |

---

## 3. Entity / table map

`.NET table` → `2in1 action`. Reuse where a master already exists.

| .NET (PostgreSQL) | Purpose | 2in1 action |
|---|---|---|
| `Negeris` | States | **REUSE** `ref_negeri` |
| `AspNetUsers` (Pengguna) | Users | **REUSE/EXTEND** `users` (+ new fields, + public user_type) |
| `AspNetRoles` (Peranan) | Roles | **MAP** to 2in1 string `role` (see §6); no separate table |
| holiday calendar | Public holidays | **REUSE** `ref_cuti` (already present, used for working-day calc) |
| `Kategoris` | Case category | **REUSE/EXTEND** `ref_kes` (verify field parity) or new `ref_kategori_kn` |
| `Cawangans` (CawanganJBG) | Branches (4 types: JBG/JKM/Mahkamah/Penjara) | **NEW** `cawangan` master (2in1 currently stores branch as a free string only) |
| `JKMs` | Welfare dept records | **NEW** `jkm` |
| `Penjaras` | Prison records | **NEW** `penjara` |
| `JenisKess` | Case type (sivil/jenayah/syariah) | **NEW** `jenis_kes` (or ref) |
| `KhidmatNasihats` | **Advisory application (core)** | **NEW** `khidmat_nasihat` |
| `TemuJanjis` | Appointments | **NEW** `temu_janji` |
| `SlotTemujanjis` | Appointment slots | **NEW** `slot_temu_janji` |
| — (in fe only) | Session times / day closures / rooms | **NEW** `sesi_janji_temu`, `penutupan_hari_operasi`, `bilik` |
| — (in fe only) | Feedback / satisfaction survey | **NEW** `maklumbalas` |
| attachments | Uploads | **REUSE** `uploaded_files` pattern |
| — | Audit | **REUSE** `audit_trail` |

**Source schema notes to carry over (from be-map):**
- All source PKs are uuid; 2in1 uses auto-increment `id` — use 2in1 convention, not uuid.
- Domain FKs in source are loose uuid columns with **no DB constraints** — add proper FK constraints in Laravel.
- Status fields are loose strings (no enums) — define PHP enums / consts in Laravel.

---

## 4. Status lifecycle (Khidmat Nasihat)

`statusKN`: **DRAF → BAHARU → DALAM PROSES → SELESAI** (+ **BATAL**).

- Wizard creates record at step 1 (DRAF), PUT-merges each step, step 4 declaration sets **BAHARU**.
- Officer processing (`pengesahan-janjitemu`) moves BAHARU → DALAM PROSES (assign officer, accept/reject case, record attendance) → SELESAI on completion.

Model this as a Laravel enum + guarded transition methods (mirror the gated-lifecycle pattern already in 2in1's batch 3).

---

## 5. Module port breakdown + UI

Source = 49 pages, 11 modules, 26 API endpoints. Rebuild as Blade under the staff shell.

| Module | Source pages | Port scope |
|---|---|---|
| **Auth** | log-masuk, daftar-pengguna, katalaluan (set/kemaskini) | Reuse 2in1 auth; add public register **if** public portal approved. Source login-by-IC → keep email for staff. |
| **Dashboard** | dashboard | Stats cards (counts by status/cawangan). |
| **Khidmat Nasihat (core)** | index, permohonan-baru (4-step wizard), kemaskini-permohonan/:id, pengesahan-janjitemu/:id, borang-maklumbalas | Biggest piece. Wizard (§5a), officer processing, feedback. |
| **Janji Temu** | janjitemu/index, jadual-janji-temu | Appointment list + schedule view. |
| **Kalendar** | cuti, cuti-negeri, penetapan-sesi-janji-temu, penutupan-hari-operasi | Holiday calendar (reuse ref_cuti), session config, day closures. |
| **Cawangan** | jbg (+bilik/kalendar/slot), jkm, mahkamah, penjara | Branch masters + per-branch rooms/slots/calendar. |
| **Pengguna** | awam, jbg, jkm, penjara (+ butiran) | User management by type. |
| **Tetapan** | senarai-jawatan, senarai-kategori (+subkategori), senarai-negeri, senarai-peranan (+akses) | Reference-data CRUD + role-access matrix. |
| **Laporan** | 8 active reports | §5b. |
| **Profil** | profil | Profile edit. |

### 5a. Khidmat Nasihat wizard (4 steps)

1. **Maklumat Permohonan** — branch: *diri-sendiri* OR *sebagai-wakil*. Collects identity / case / address / category.
2. **Bayaran** — fee logic: `0` waived (prison/JKM), `260` sumbangan, else `10`.
3. **Slot Janji Temu** — pick branch → available date → available time. Availability = holidays (ref_cuti) + state-weekend + branch closures.
4. **Perakuan** — declaration; sets `statusKN = BAHARU`.

Pattern: create-on-step-1, PUT-merge per step. In Laravel: one `khidmat_nasihat` row, partial-update endpoints per step, server-side FormRequest per step.

### 5b. Reports (8 active)

pendaftaran khidmat nasihat · pandangan undang-undang · by cawangan · by kategori-kes · by subkategori · by kaum-jantina · cara-mengetahui-JBG · tahap-kepuasan.
Filters: Tahun / Bulan / Cawangan / Kategori. Charts via ApexCharts (vanilla), export via Laravel Excel.

---

## 6. Role mapping

Source roles (5): SUPERADMIN, PENGURUSAN, PEMBANTU TADBIR, PEGAWAI KHIDMAT NASIHAT, PELANGGAN (+ prison/JKM carrier flags).

| Source role | 2in1 role |
|---|---|
| SUPERADMIN | `admin` |
| PENGURUSAN | `pengarah` / `koordinator` |
| PEMBANTU TADBIR | `pembantu_tadbir` (legacy const exists) |
| PEGAWAI KHIDMAT NASIHAT | `pegawai` (or new `pegawai_kn`) |
| PELANGGAN (public applicant) | **NEW** `user_type = awam` — decision point §7 |

**Security:** source gating is **client-side only** and `[Authorize]` is **not enforced** on any endpoint. In Laravel, enforce server-side via `EnsureRole` middleware + policies on every route. Do not replicate the source's open-API posture.

---

## 7. Open decisions (need answer before batch 1)

1. **Public portal?** Source has public self-registration + public applicants (PELANGGAN) booking their own appointments. 2in1 today is internal-officer only. Options:
   - **(A) Internal-only** — staff create advisory records on behalf of walk-ins. Smaller, safer.
   - **(B) Full public portal** — add `awam` user_type, public register/login, captcha, self-service booking. Much bigger, adds public attack surface.
2. **Cawangan master vs string.** 2in1 stores branch as a free string + `CawanganScope`. Source treats branches as real entities (4 types, rooms, slots, calendars). Recommend introducing a real `cawangan` table and migrating the string usage — but that touches existing scoping. Confirm.
3. **Kategori reuse.** Reuse `ref_kes` for advisory categories, or new `ref_kategori_kn` (+ subkategori)? Need a field-level compare.

---

## 8. Bugs to fix during port (do NOT copy)

From be-map findings:
1. `KhidmatNasihat` create silently drops its FK fields (saved as empty) — fix so FKs persist.
2. UTC+8 baked into `timestamptz` values (double-offset) — use Carbon + app timezone correctly.
3. `CreateTemuJanji` drops `IdPegawaiKN` — persist it.
4. **No appointment slot logic exists** (no overlap/double-booking/availability checks). All real scheduling rules are **new work** in Laravel, not a port.
5. Auth configured but unenforced — enforce in Laravel.

---

## 9. Suggested batch sequence

Mirrors the existing "legacy parity batch N" cadence. Each batch = atomic commits + reviews.

| Batch | Scope | Depends on |
|---|---|---|
| **7 — Foundations** | New masters: `cawangan` (+type), `jkm`, `penjara`, `jenis_kes`, kategori/subkategori. Migrations + models + CawanganScope + Tetapan CRUD UI. Role const additions. | §7 decisions |
| **8 — Khidmat Nasihat core** | `khidmat_nasihat` table/model, 4-step wizard (Blade + per-step FormRequest), status lifecycle, attachments. | 7 |
| **9 — Appointment + calendar** | `slot_temu_janji`, `temu_janji`, `sesi_janji_temu`, `penutupan_hari_operasi`, `bilik`; **new** availability/double-booking logic; calendar + slot config UI. | 7, 8 |
| **10 — Officer processing** | `pengesahan-janjitemu` flow (assign officer, accept/reject, attendance, complete); janji temu list/schedule. | 8, 9 |
| **11 — Feedback + reports** | `maklumbalas` survey; 8 statistik reports (ApexCharts + Laravel Excel export). | 8–10 |
| **12 — Public portal** *(only if §7→B)* | `awam` user_type, public register/login, captcha, self-service booking. | all |

---

## 10. Library replacements (Vue → Laravel/Blade)

| Source | Replace with |
|---|---|
| `@nuxtjs/auth-next` (JWT) | Laravel session auth (existing) |
| BootstrapVue | Bootstrap/Tailwind in Blade (match 2in1 `system.css`) |
| vuelidate | Laravel FormRequest validation |
| vue-apexcharts | ApexCharts (vanilla JS) |
| vue-sweetalert2 | SweetAlert2 (vanilla) |
| vue-html-to-paper | Blade print view / PDF |
| moment / vue-moment | Carbon |
| xlsx | Laravel Excel (maatwebsite) — *flag new dependency* |
| custom 4-digit captcha | re-implement (2in1 already has a number captcha) |

---

## 11. Effort / risk

- **Effort:** multi-week. Heaviest: the 4-step wizard, the appointment/slot+calendar engine (mostly net-new logic, not a port), and 8 charted reports.
- **Risk hotspots:**
  - Cawangan string→table migration vs existing `CawanganScope`.
  - Public portal scope creep + new attack surface (if §7→B).
  - Slot availability engine is greenfield (source has none).
  - New dependency: Laravel Excel for xlsx export.
- **Low risk:** masters CRUD, reference data, dashboard.
