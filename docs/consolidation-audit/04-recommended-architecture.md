# Deliverable 4 — Recommended Consolidated Architecture

> **System consolidation audit (Malaysian legal-aid, JBG / BHEUU).** READ-ONLY analysis — this document
> proposes a target architecture; no source code was modified.
>
> **Builds on (and stays consistent with):**
> - Maps 01–09 (`docs/consolidation-audit/maps/`)
> - `02-feature-comparison-matrix.md` · `03-gap-analysis.md` · `06-redundancy-and-removal-list.md`
> - `status-and-workflow-governance.md` · `roles-and-access-control.md` · `data-and-database-review.md`
>
> **Verified against live code** at commit `735dd4f` (branch `main`): `app/Support/*` (22 classes),
> `app/Http/Controllers/*` (49 controllers incl. `Awam/`), `app/Console/Commands/*`, `app/Mail/*`,
> `routes/web.php`, `routes/console.php`. **There is no `app/Services/`, no `app/Notifications/`** today.
>
> **What this document is.** A target-state blueprint: the cohesive core-module map, the shared-service
> layer to extract from today's fat controllers, the one-authoritative-owner-per-concept rule, the unified
> role structure, the integration topology, how the chatbot becomes a *controlled interface into the same
> core* (not a parallel workflow), and one reporting source of truth. It is the architecture the remediation
> work (Deliverable 5) should converge on — it does **not** re-list every gap (see Deliverable 3) or every
> removal (see Deliverable 6); it gives them a home.

---

## 0. Design principles (the rules the whole architecture obeys)

These principles resolve the *systemic* defects the prior deliverables found. Every later section is an
application of one of them.

| # | Principle | Fixes (from prior deliverables) |
|---|---|---|
| **P1** | **One authoritative owner per concept.** Each business concept (case, lawyer, advisory, appointment, branch, identity, status) has exactly ONE table + ONE service that may mutate it. Everything else reads. | Dual `status_agihan` encoding (B-1/B-3); two agihan front-ends (B-4); PKN in two tables (C4); KN⇄TJ dual link (C5); lawyer IC under 3 names (C7). |
| **P2** | **Business logic lives in a service layer, not in controllers/views.** Extract today's `app/Support/*` into a real `app/Domain/<context>/` service layer with thin controllers that only validate + dispatch. | Fat controllers (`KeputusanController`, `PermohonanPeguamController`, `KhidmatProsesService` reaching across domains); logic-in-views legacy smell ported forward. |
| **P3** | **State machines are explicit and enforced at the model/service edge, not by which button renders.** One `transition($from,$event)→$to` table per workflow; illegal `$to` rejected below the controller. | No DB enum/check on any status (X-2); `$guarded=['id']` mass-assignment; STUCK-1..7 dead-ends; ungated lawyer `terima/tolak` (B-5). |
| **P4** | **Authorization is one mechanism, server-side, and the matrix never lies.** Every enforced capability = one `permission:` gate that is actually checked; decorative permissions deleted; ownership via policies; branch isolation via one scope abstraction. | 11 decorative permissions (§4.4 roles doc); 3 gating styles (X-1); read-side leaks (§4.5 roles doc); admin-escalation (§4.1 roles doc); `CawanganScope` on `forms` only (M8). |
| **P5** | **One identity, one branch key, one reporting source of truth.** Unified `users`; `cawangan_id` everywhere (retire the free-string branch key over time); reports read a single canonical query layer with shared status definitions. | Branch string vs id (C6); SLA vs KPI `khidmat` end-date conflict (G-M7); computed-vs-stored status drift (A-3); 10 inconsistent `status*` columns. |
| **P6** | **The chatbot is an interface, not a system.** It may *read* the same core through the same auth/permission/business rules — never a second copy of any workflow or data. | Chatbot is cleanly decoupled today (map 04); this keeps it that way while enabling future authorized "ask about my case". |
| **P7** | **Brownfield discipline: integrate, never fork.** Schema changes are reversible migrations + archive (Deliverable 6 §11); legacy table/column names preserved until a deliberate normalization epic; no parallel rewrites resurrected. | Three competing schema sources (C1/F1); `sistem-rekod-kes-laravel` + `spk-laravel` leftovers; ETL reading a non-existent table (F2). |

---

## 1. Target core modules (one cohesive map)

The consolidated system is **one Laravel monolith** organised into **eight bounded core modules** plus a
thin **Platform** kernel. Each module owns its tables, its service(s), its state machine(s), and its
permission group. Modules talk to each other only through **named integration seams** (§6), never by
reaching into another module's tables.

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│  PLATFORM KERNEL  (cross-cutting — every module depends on it; it depends on none)     │
│  Identity & Auth · RBAC · Branch Isolation · Audit · Notifications · Reporting Kernel  │
└──────────────────────────────────────────────────────────────────────────────────────┘
        ▲            ▲             ▲              ▲              ▲             ▲
        │            │             │              │             │              │
┌───────────────┐ ┌───────────────┐ ┌──────────────┐ ┌────────────────┐ ┌──────────────┐
│ 1. ADVISORY   │ │ 2. APPOINTMENT│ │ 3. CASE       │ │ 4. ASSIGNMENT  │ │ 5. PANEL      │
│   (Khidmat    │ │   (Janji Temu │ │   (Rekod Kes  │ │   (Agihan +    │ │   LAWYER      │
│    Nasihat)   │ │    + Slot)    │ │    spine)     │ │    Tarik Diri) │ │   (Peguam)    │
│               │ │               │ │               │ │                │ │               │
│ khidmat_      │ │ slot_temu_    │ │ forms (spine) │ │ forms.status_  │ │ peguam_panel  │
│  nasihat      │ │  janji        │ │ laporan_kes   │ │  agihan        │ │ butiran_pp_2  │
│ maklum_balas  │ │ temu_janji    │ │ sejarah_      │ │ sejarah_ppuu   │ │  .._6         │
│ ref_kategori_ │ │ penutupan_    │ │  pegawai      │ │ sejarah_peguam │ │ uploaded_     │
│  kn tree      │ │  operasi      │ │ sejarah_sidang│ │  _panel        │ │  files(kpBaru)│
│ uploaded_     │ │ (bilik)       │ │ butiran_oyd   │ │                │ │               │
│  files(KN)    │ │               │ │ uploaded_     │ │ KemaskiniBidang│ │ KemaskiniBdg  │
│               │ │               │ │  files(kes)   │ │  (pengkhususan)│ │  (lawyer side)│
└───────┬───────┘ └───────┬───────┘ └──────┬────────┘ └───────┬────────┘ └──────┬───────┘
        │  buka-kes seam   │  books          │  agih seam        │ promote seam    │
        └────────►─────────┴───────►─────────┴─────────►─────────┴────────►────────┘

┌───────────────┐ ┌───────────────┐ ┌──────────────────────────────────────────────────┐
│ 6. CITIZEN     │ │ 7. REFERENCE  │ │ 8. CHAT (controlled interface — read-only into     │
│   PORTAL (Awam)│ │   & ADMIN     │ │    the core via Platform auth/permissions)         │
│ self-service   │ │ cawangan, ref_│ │ ChatbotController proxy → FastAPI microservice      │
│ over module 1  │ │ kes, mahkamah,│ │ (RAG/LLM). NO own workflow, NO own data.           │
│ + 2 (booking)  │ │ cuti, jawatan,│ │ Future: authorized read of modules 1/3 per RBAC.   │
│ + maklum_balas │ │ users, roles  │ │                                                    │
└───────────────┘ └───────────────┘ └──────────────────────────────────────────────────┘
```

### 1.1 Module charter (what each owns and may mutate)

| # | Module | Owns (authoritative tables) | Owning service(s) | State machine(s) | Permission group |
|---|---|---|---|---|---|
| **K** | **Platform Kernel** | `users`, `roles`/`permissions`/pivots, `audit_trail`, `sessions`, `cache*`, `jobs*` | `Identity`, `Rbac`, `BranchScope`, `Audit`, `Notifier`, `ReportKernel` | — | `urus.*`, `audit.*`, `system.area`, `cawangan.view-all` |
| **1** | **Advisory (Khidmat Nasihat)** | `khidmat_nasihat`, `maklum_balas`, `ref_kategori_kn`/`_kes_kn`/`subkategori_kn`, KN `uploaded_files` | `AdvisoryService` (create/screen/fee), `AdvisoryProcessingService` (officer chain), `FeedbackService` | `status_kn` (DRAF→BAHARU→DALAM_PROSES→SELESAI/BATAL) | `khidmat.*`, `selenggara.kategori_kn`, `maklumbalas.*` |
| **2** | **Appointment (Janji Temu)** | `slot_temu_janji`, `temu_janji`, `penutupan_operasi`, `bilik` | `SlotAvailabilityService`, `SlotGenerator`, `AppointmentService` (book/release/reschedule) | `temu_janji.status` (MENUNGGU→DISAHKAN→HADIR/TIDAK_HADIR→SELESAI/BATAL) | `slot.*` |
| **3** | **Case (Rekod Kes spine)** | `forms` (litigation lifecycle cols), `laporan_kes`, `sejarah_pegawai`, `sejarah_sidang`, `butiran_oyd`, case `uploaded_files` | `CaseService`, `DecisionService` (keputusan/jana/batal/tutup), `MediationService`, `CourtService`, `NoFailGenerator` | `forms.status` (Baharu→Diterima→…→Fail Tutup / Ditolak / Dibatalkan) | `kes.*`, `pengantaraan.*`, `mahkamah.*`, `oyd.*`, `lampiran.*`, `cetakan.*` |
| **4** | **Assignment (Agihan + Tarik Diri)** | `forms.status_agihan` (the assignment sub-state of the case), `sejarah_ppuu`, `sejarah_peguam_panel` | **`AgihanService` (sole writer)**, `TarikDiriService`, `LebihMasaService` | `status_agihan` (0→8→10→13→1→2 … + TD 12→16→17→6 + 4/7/9/15) | `agihan.*` |
| **5** | **Panel Lawyer (Peguam)** | `peguam_panel`, `butiran_peguam_panel_2.._6`, lawyer `uploaded_files`(kpBaru) | `PanelApplicationService` (approve/promote), `PeguamLifecycleService`, `PengkhususanService` | `permohonan_status` (0→1/2/3), `checkbox_value_status`, `statusAktif` | `peguam.*`, `peguam_panel.*`, `lawyer.area` |
| **6** | **Citizen Portal (Awam)** | (no own domain tables — operates module 1 + 2 with ownership policy) | reuses `AdvisoryService` + `AppointmentService` + `FeedbackService` via owner-scoped facade | reuses `status_kn`, `temu_janji.status` | `awam.portal` |
| **7** | **Reference & Admin** | `cawangan`, `ref_kes`, `mahkamah_sivil`/`syariah`, `ref_cuti`, `ref_negeri`, `ref_jawatan`, `ref_lokasi_berguam`, `pegawai_jbg`, `posters` | thin CRUD services; `Identity`/`Rbac` for users/roles | reference `aktif` flags | `selenggara.*`, `urus.*` |
| **8** | **Chat (controlled interface)** | (no tables) | `ChatbotProxyService` (today) → optional `ChatContextService` (future authorized read) | — | reuses caller's permissions; no own gate beyond `throttle` |

> **Cohesion rule.** A controller in module N may only call services in module N **or** a *published seam*
> (§6). It must never `DB::table()` or `Model::query()` against another module's tables. Example to retire:
> `KhidmatProsesService::bukaKes()` writing into `forms` directly should instead call `CaseService::openFromAdvisory()`
> (the buka-kes seam), so the Case module stays the only writer of `forms`.

---

## 2. Shared services — the extraction targets (today: fat controllers, no `app/Services/`)

**Current reality (verified).** Business logic is split between 49 fat controllers and a flat `app/Support/`
bag of 22 classes that mixes true services (`AgihanService`), value objects (`StatusAgihan`), report builders
(`SlaMatrix`, `WideExport`), and helpers (`Audit`, `CutiNegeri`). There is **no `app/Services/` directory**,
**no `app/Notifications/`**, and only 3 ad-hoc `app/Mail/` classes. Cross-module logic leaks (e.g.
`KhidmatProsesService` writing `forms`).

**Target.** Promote `app/Support/` into a real domain service layer under `app/Domain/<context>/`, and add the
**six Platform shared services** the brief calls out. Controllers shrink to: validate (FormRequest) → call one
service method → return view/redirect. Services own transactions, state-machine guards, audit writes, and
notification dispatch.

### 2.1 The six Platform shared services to extract

| Shared service | Extract from (today) | Single responsibility | Replaces / fixes |
|---|---|---|---|
| **`SlotAvailability`** | `app/Support/SlotAvailabilityService.php` + `SlotGenerator.php` + `CutiNegeri.php` (working-day/holiday/closure math is duplicated in SLA/KPI day-counts too) | One authority for "is this date/time bookable" and "generate supply": working-day lead time, weekend/holiday/closure exclusion, slot supply. | Working-day logic re-implemented in `SlaMatrix`/`KpiController` day-counts; lead-time literals scattered. |
| **`Assignment` (Agihan)** | `app/Support/AgihanService.php` + `StatusAgihan.php` + `LebihMasaService.php`; **absorb** `AgihanController` single-step writes + `PeguamController` lawyer accept/reject | **Sole writer of `forms.status_agihan` + `sejarah_ppuu` + assignment history.** All offers/accepts/rejects/timeouts/redistribution route through it, writing ONE (numeric) encoding. | B-1, B-3, B-4, B-5, STUCK-1; two parallel front-ends; ungated lawyer writes. |
| **`StatusTransition`** | (does not exist) — generic kernel used by every workflow service | One `transition(Workflow, $from, $event): $to` engine + a per-workflow transition table; rejects illegal transitions below the controller; emits the audit + notification events. | X-2 (no enforced machine), STUCK-2/3/4 (undefined branches), A-1, $guarded mass-assignment. |
| **`Reporting` (ReportKernel)** | `app/Support/SlaMatrix.php`, `KesilapanMatrix.php`, `PengantaraanMatrix.php`, `WideExport.php`, `SlaListExport.php`, `LaporanKnService.php` + the 8 statistik/laporan controllers | One canonical query+definition layer: shared status definitions, shared SLA end-date columns, branch scoping, then thin CSV/PDF/Excel presenters on top. | G-M7 (SLA vs KPI end-date), A-3 (computed-vs-stored), report logic duplicated across controllers. |
| **`Audit`** | `app/Support/Audit.php` (record-level only; `field_name`/`old/new` always NULL) | One writer for `audit_trail`; **upgrade to field-level diffs** (old→new), actor = `users.id` (not name string), via a model `Auditable` trait so services don't hand-roll calls. | Record-level-only audit; 25+ scattered `Audit::log()` call sites; actor-by-name. |
| **`Notifications` (Notifier)** | the 3 `app/Mail/*` classes + `NotifikasiAgihan.php` | One notification dispatch layer (`app/Notifications/` + queued mailers) covering the **9 legacy triggers** (registration decision, credential delivery, deceased-lawyer reassignment, withdrawal-tier + cancellation letter, password-regen, agihan, reminders). In-app bell + email channels. | G-H2 (9→4 collapse), G-C2 (credential delivery), G-C3 (cancellation letter), no notification bell. |

### 2.2 Supporting domain services (per module, also extracted from controllers)

| Module | Domain services | Pulled out of |
|---|---|---|
| 1 Advisory | `AdvisoryService`, `AdvisoryProcessingService`, `FeedbackService`, `KhidmatBayaran` (fee, already pure) | `KhidmatNasihatController`, `KhidmatProsesController`, `Awam\PermohonanController`, `MaklumBalasController` |
| 2 Appointment | `AppointmentService` (book/release/reschedule), `SlotAvailability`, `SlotGenerator` | `KhidmatNasihatService` (book logic lives here today — move it), `SlotController`, `SlotGenerationController` |
| 3 Case | `CaseService`, `DecisionService` (keputusan/jana/batal/tutup + 30-day rule + menteri override), `MediationService`, `CourtService`, `NoFailGenerator` | `KesController`, `KeputusanController`, `PengantaraanController`, `MahkamahController` |
| 4 Assignment | `Assignment` (Agihan), `TarikDiriService`, `LebihMasaService` | `AgihanController`, `AgihanSpineController`, `PeguamController` (accept/reject), `TarikDiriController` |
| 5 Panel Lawyer | `PanelApplicationService` (semak/sokong/keputusan/promote), `PeguamLifecycleService`, `PengkhususanService`, `LawyerDocuments` | `PermohonanPeguamController`, `PeguamPanelController`, `KemaskiniBidangController`, `PeguamDaftarController` |

> **Net effect.** Controllers become ≤50-line dispatchers (the global coding-style limit). State, transactions,
> audit, and notifications concentrate in services that the **chatbot, the citizen portal, the staff UI, and
> any future API all share** — which is what makes the chatbot "an interface into the same core" rather than a
> parallel path (§6).

---

## 3. Data ownership — one authoritative entity per concept

The single source-of-truth assignment (consistent with `data-and-database-review.md` §2 and
`status-and-workflow-governance.md` §1). **"Owner module" is the only module whose service may write.**

| Concept | Authoritative table | Owner module/service | Read-only consumers | Today's violation to fix |
|---|---|---|---|---|
| **Identity / login** | `users` | Kernel · `Identity` | every module | OK (unified). Lock `role↔user_type` consistency; forbid non-admin minting `admin` (§4.1 roles doc). |
| **Branch** | `cawangan` (typed) | Kernel · `BranchScope` / module 7 | every module via `cawangan_id` | C6 — migrate `forms.cawangan` (string) → `cawangan_id`; keep `nama` mirror during transition only. |
| **Case (litigation)** | `forms` | Case · `CaseService` | Assignment, Advisory(buka-kes), Reporting | Advisory writes `forms` directly (`bukaKes`) — route via `CaseService::openFromAdvisory`. |
| **Assignment state** | `forms.status_agihan` + `sejarah_ppuu` | **Assignment · `Assignment` (sole writer)** | Case, Panel Lawyer, Reporting | B-1/B-3/B-4 — single-step + lawyer-side writes must go through `Assignment`, ONE numeric encoding. |
| **Court report** | `laporan_kes` | Case · `CourtService` | Reporting | F3/§3 — `id_kes varchar(20)` → clean+cast to int, add FK. |
| **Beneficiary (OYD)** | `butiran_oyd` | Case · `CaseService` | Assignment, Reporting | M8/§4.5 — add branch isolation (currently none → cross-branch PII leak). |
| **Lawyer master** | `peguam_panel` (key `kp_peguam`) | Panel · `PanelApplicationService` | Assignment (by id, not name) | M9/C7/WF-3 — switch case↔lawyer linkage from `nama_peguam` string to `kp_peguam`/id; add `forms.kp_peguam_dapat_kes`. |
| **Lawyer application/profile** | `butiran_peguam_panel_2` (+`_3.._6`) | Panel | Reporting | F5/F1 — reconcile `_3..6`+`sejarah_ppuu` shapes against the **real `sistemspk.sql` dump**; normalize to lawyer-profile + child tables later. |
| **Advisory record** | `khidmat_nasihat` | Advisory · `AdvisoryService` | Appointment, Citizen, Reporting, (future) Chat | C5 — `id_temu_janji` ⇄ `temu_janji.id_khidmat_nasihat`: pick ONE direction as canonical. |
| **Appointment** | `temu_janji` + `slot_temu_janji` | Appointment · `AppointmentService` | Advisory, Citizen, Reporting | C4 — PKN officer: `khidmat_nasihat.id_pegawai_kn` is authoritative; `temu_janji.id_pegawai_kn` is a derived snapshot (or drop it). |
| **Feedback** | `maklum_balas` (unique per KN) | Advisory · `FeedbackService` | Reporting | OK — best-modelled new table. |
| **PKN officer (who advises)** | `khidmat_nasihat.id_pegawai_kn` (FK) | Advisory | Appointment (snapshot) | C4 — one home; sync or remove the duplicate. |
| **Audit log** | `audit_trail` | Kernel · `Audit` | `AuditController` | denormalized by design; upgrade to field-level + actor id. |
| **Reference data** | `cawangan`, `ref_kes`, `mahkamah_*`, `ref_cuti`, `ref_negeri`, `ref_jawatan`, `ref_lokasi_berguam` | Reference module 7 | every module | F7 — collapse `mahkamah_sivil`+`mahkamah_syariah` → one `mahkamah` + `jenis`; normalize charsets (`ref_cuti` latin1, `posters` collation). |
| **KN category tree** | `ref_kategori_kn`→`_kes_kn`→`subkategori_kn` | Advisory / Reference | Advisory, Reporting | KEEP separate from litigation `ref_kes` (D3 / memory `ref-kes-not-kn-tree`) — do **not** merge. |

**Hard ownership rules:**
1. `forms.status_agihan` is part of the Case row physically but is the **Assignment module's** state — only
   `Assignment` writes it. (This is the single biggest ownership fix; it dissolves B-1 through B-5.)
2. The Citizen Portal (module 6) **owns no tables** — it is a permission-scoped facade over Advisory +
   Appointment, enforced by `KhidmatNasihatPolicy` ownership. Staff-created KN (`id_pengguna=null`) stay
   invisible to citizens by that policy (documented, intentional).
3. No module reads another module's tables directly; it calls the owner's service or a published seam (§6).

---

## 4. Unified role structure

Keep the **9 roles** (they map to real org positions per `roles-and-access-control.md` §1) but **fix the
three structural defects** and **collapse 40 permissions → ~24 enforced capabilities** so the Akses matrix
stops lying (§4.4 roles doc).

### 4.1 Roles (9, unchanged set — semantics tightened)

| Role | `user_type` | Lineage | Branch reach | Notes |
|---|---|---|---|---|
| `admin` | staff | PP `5` / RK `1` / ADV `SUPERADMIN` | all | `Gate::before` super-admin. **Only role that may assign `admin`** (fix §4.1). |
| `ketua_pengarah` | staff | PP `4` | all (`cawangan.view-all`) | final approver (3-tier top). **Add to `khidmat.proses`** (asymmetry fix, §4.7). |
| `pengarah` | staff | PP `3` / RK `2` | own branch | approve/reject/close/sokong. |
| `koordinator` | staff | new in 2in1 | all (`cawangan.view-all`) | cross-branch ops; confirm it is a real position, else fold into ppuu+view-all. |
| `pegawai` | staff | RK `0` / ADV `PKN` | own branch | front-line officer + KN processing. |
| `ppuu` | staff | PP `2` | own branch | case distributor (agihan tier 2). |
| `pembantu_tadbir` | staff | PP `0` / ADV `PEMBANTU TADBIR` | own branch | clerk/counter. |
| `peguam` | lawyer | PP `1` | own cases | external panel lawyer (own portal). |
| `awam` | awam | ADV `PELANGGAN` | own records | citizen (IC login). **Add to `RolePermissionSeeder::ROLES` + `RoleController::SYSTEM_ROLES`** (fix §4.3/F6). |

Dropped legacy roles (intentional, per roles doc §1): `PENGURUSAN` (reports folded into staff roles),
`PEGAWAI PENJARA`/`PEGAWAI JKM` (handled as `jenis_wakil` paths, not roles — but confirm prison/JKM
onboarding), `PPUU SA` special account, RK "Putrajaya = HQ" rule (replaced by `cawangan.view-all`).

### 4.2 Permissions — collapse to ~24 enforced capabilities (one per real gate)

**Delete the 11 decorative permissions** (`kes.view/create/update`, `pengantaraan.manage`,
`mahkamah.manage`, `lampiran.manage`, `cetakan.view`, `oyd.manage`, `kpi.view`, `peguam_panel.manage`,
`peguam.permohonan.view`) and `menu.selenggara` (UI-only). Replace with **enforced** group gates so the
matrix reflects reality:

| Group | Permission | Granted to | Enforced at (target) |
|---|---|---|---|
| area | `staff.area` (was `system.view`) | 7 staff roles | outer route group |
| area | `lawyer.area` | peguam | route + ownership |
| area | `awam.portal` | awam | route + `KhidmatNasihatPolicy` |
| kes | `kes.manage` (folds the case/mediation/court/oyd/cetakan/kpi reads+writes) | staff | **new route group on /kes, /oyd, /kpi, /cetak** |
| kes | `kes.keputusan` | pengarah, ketua_pengarah | `DecisionService` `can()` (keep) |
| agihan | `agihan.view` (read queues) | pengarah, koordinator, ppuu, ketua_pengarah | **new route gate on senarai/maklumat** (fix read-leak §4.5) |
| agihan | `agihan.pengarah`/`agihan.ppuu`/`agihan.kp` | as today | route (keep) |
| khidmat | `khidmat.view`/`khidmat.manage`/`khidmat.proses` | as today (+ KP to proses) | route (keep) |
| peguam_panel | `peguam_panel.view` (read applications/queues) | pengarah, koordinator, ppuu, pembantu_tadbir, ketua_pengarah | **new route gate on permohonan-peguam, kemaskini-bidang, tarik-diri list** |
| peguam_panel | `peguam.semak`/`peguam.sokong`/`peguam.keputusan` | as today | `PanelApplicationService` `can()` (keep) |
| selenggara | `selenggara.manage` (folds 8) OR keep per-area if delegation is real | pengarah, koordinator, ketua_pengarah | route (keep) |
| slot | `slot.view`/`slot.manage` | as today | route (keep) |
| admin | `urus.pengguna`/`urus.peranan`/`audit.view` | as today **+ §4.3 guards** | route + new guards |
| scope | `cawangan.view-all` | koordinator, ketua_pengarah | `BranchScope` (keep) |

### 4.3 Hard authorization fixes (do regardless of the collapse)

1. **Privilege escalation (CRITICAL, §4.1 roles doc):** `UserRequest::authorize()` must
   `return $this->user()->can('urus.pengguna')` **and** forbid assigning `admin` unless actor
   `hasRole('admin')`; enforce `role↔user_type` consistency. Add a test: "non-admin cannot mint an admin."
2. **Matrix self-escalation (CRITICAL, §4.2 roles doc):** `RolePermissionController` must deny granting
   `urus.peranan`/`urus.pengguna`/`audit.view` to non-admin roles and never let `admin`'s matrix be emptied.
3. **Citizen-gate protection (HIGH, §4.3 / F6):** move `awam` role + `awam.portal` into the canonical seeder
   and the protected `SYSTEM_ROLES` list.
4. **Read-side leaks (HIGH, §4.5):** add real `permission:` gates on the agihan/tarik-diri/kemaskini-bidang/
   permohonan-peguam list+show routes; add branch isolation to `butiran_oyd` and `khidmat_nasihat`.

### 4.4 Branch isolation — one abstraction, all branch-scoped tables

Today `CawanganScope` is a global scope on **`forms` only**; KN isolation is hand-rolled in 3 places, and
`butiran_oyd`/`temu_janji`/`slot_temu_janji` have none. **Target:** one `BranchScope` kernel applied as a
global scope to every branch-owned model (`forms`, `khidmat_nasihat`, `temu_janji`, `slot_temu_janji`,
`butiran_oyd`, and the agihan/lawyer queues), keyed on `cawangan_id`, bypassed by `cawangan.view-all`,
memoized per request. Delete the 3 hand-rolled KN filters (WF-4).

---

## 5. State-machine governance (cross-module, enforced by `StatusTransition`)

Every status field becomes a **PHP 8.3 backed enum** with an explicit transition table owned by its module's
service and enforced by the kernel `StatusTransition` engine **at the model/service edge** — closing the
mass-assignment gap (`$guarded=['id']`) and the STUCK dead-ends. DB-level `CHECK`/enum constraints back the
enums (MySQL 8 supports them). Consistent with `status-and-workflow-governance.md` §12.

| Workflow | Field | Owner service | Must-fix transitions (STUCK register) |
|---|---|---|---|
| Case | `forms.status` | `CaseService` | add `Dibatalkan` (batal); SLA flag on `Diterima` stall (A-2). |
| Assignment | `forms.status_agihan` | `Assignment` | **ONE numeric encoding**; lawyer offer reads `bucketValues([DITAWARKAN])` (STUCK-1); add `9→0/tutup` recovery (STUCK-2); add `ensureStatus` to lawyer terima/tolak (B-5); guard single-step against mid-spine clobber (B-4). |
| Withdrawal | `status_agihan` 12/16/17/6 | `TarikDiriService` | gate `permohonan_status='3'` properly (D-1); document case-`4`/row-`6` divergence for ETL (C-1). |
| Panel application | `permohonan_status` | `PanelApplicationService` | gate Tarik Diri + add from-guard (D-1/D-4). |
| Pengkhususan | `checkbox_value_status` | `PengkhususanService` | name/handle `0` (E-1). |
| Advisory | `status_kn` | `AdvisoryProcessingService` | `TIDAK_HADIR → SELESAI` or reschedule (STUCK-3); `tolak` sets explicit KN fate (STUCK-4). |
| Appointment | `temu_janji.status` | `AppointmentService` | invariant linking `status_kn` ↔ `temu_janji.status` (G-4). |
| Mediation | `status_pengantaraan`, `status_sidang` | `MediationService` | enum-ize (H-1/H-2) so statistik gates stop dropping typo rows. |

---

## 6. Integration structure (the named seams)

Modules integrate **only** through these published seams. No module reaches into another's tables.

```
ADVISORY ──"buka-kes"──► CASE        AdvisoryProcessingService.bukaKes()
                                       → CaseService.openFromAdvisory(KN): creates forms row,
                                         back-links khidmat_nasihat.id_forms, generates no_fail.
                                       (Today bukaKes writes forms directly — re-route through CaseService.)

ADVISORY ──"book"──────► APPOINTMENT  AdvisoryService.submit()
                                       → AppointmentService.book(slot): temu_janji MENUNGGU, slot taken,
                                         back-link khidmat_nasihat.id_temu_janji (ONE canonical direction).

CASE ─────"agih"───────► ASSIGNMENT   CaseService routes an approved case to the panel
                                       → Assignment.offer(): the ONLY writer of forms.status_agihan + sejarah_ppuu.

ASSIGNMENT ─"promote-read"─► PANEL    Assignment reads peguam_panel by kp_peguam (id, NOT name).

PANEL ────"promote"────► KERNEL       PanelApplicationService.promote()
                                       → Identity.provisionLogin() + Notifier.sendCredentials() (email — fixes G-C2).

ANY MODULE ─"audit"────► KERNEL       service writes via Audit (field-level diffs, actor=users.id).
ANY MODULE ─"notify"───► KERNEL       service dispatches via Notifier (9 triggers, in-app bell + email).
ANY MODULE ─"report"───► KERNEL       Reporting reads canonical queries + shared status defs.

CITIZEN PORTAL ────────► ADVISORY+APPOINTMENT (owner-scoped facade; KhidmatNasihatPolicy enforces ownership).

CHAT ──"controlled read"─► PLATFORM (auth/permission) ─► ADVISORY/CASE  (future, §7) — never a parallel workflow.
```

**External integration topology (unchanged where it works):**

| Integration | Pattern | Owner | Notes |
|---|---|---|---|
| **Chatbot microservice** | Server-side proxy (`ChatbotController` → FastAPI). Browser never sees bot creds. | Kernel + module 8 | Keep as microservice (D9). Operational hardening only: rotate 5 secrets, lock `/docs`, stop reflecting headers, CORS to real origin, move `BOT_API_*` to a secret store. |
| **Mail** | Laravel Mail via `Notifier`, **queued** (use the present `jobs` tables). | Kernel | Prod driver + rotate leaked `aplikasi.jbg@bheuu.gov.my` app-password. |
| **Legacy data (ETL)** | `legacy:import` from `sistemspk` → `iguaman_2in1` (one-time + reconcilable). | Kernel command | **Fix F2** (`users_peguam_panel_3` does not exist), **F1/C1** (reconcile `_3..6`+`sejarah_ppuu` against the real dump), **F3** (8 missing `forms` cols). |
| **No external system integration** (JKM/Penjara/Mahkamah are local reference tables, not live systems). | — | Reference module | Matches legacy; net-new only if scoped. |

---

## 7. The chatbot as a controlled interface into the same core

**Goal (P6).** The chatbot must use the **same auth, the same permissions, the same business rules, and the
same data** as everything else — never a second copy of any workflow or any data. It is a *read interface*,
not a system.

**Today (verified, map 04 / map 08 §6):** cleanly decoupled — `ChatbotController@ask` is a server-side proxy
to a FastAPI RAG service; the bot has **zero** access to 2in1 records, roles, or permissions; it answers only
generic public JBG info. That decoupling is correct and must be preserved. The architecture below keeps it a
microservice while making it a *controlled* interface if/when "ask about my case" is ever scoped.

**Target shape:**

| Aspect | Rule | How it lands in this architecture |
|---|---|---|
| **Same auth** | The proxy runs inside the Platform Kernel. The end-user's 2in1 session/identity (not a shared bot credential) determines what the bot may surface. | `ChatbotController` already proxies server-side; add the authenticated `user` context to the request so any record lookup is *that user's*. |
| **Same permissions** | Any record the bot reads goes through the **owning module's service + RBAC + `BranchScope` + ownership policy** — exactly the path the UI uses. The bot calls `ChatContextService`, which calls `AdvisoryService::forUser($user)` / `CaseService` under the user's permissions. No bypass, no service-account super-read. | New `ChatContextService` (module 8) is a thin authorized reader over modules 1/3 — it has **no SQL of its own**. |
| **Same business rules** | The bot never *mutates*. It cannot transition a status, book a slot, or create a case. Any "do X" intent is rendered as a deep link into the real UI workflow (which enforces the state machine). | `ChatContextService` is read-only; write intents return a CTA link to the gated route. |
| **Same data** | The bot reads the live core tables through services — never a synced copy or a parallel store. Its FAISS/RAG corpus stays for *public information* only (Akta, procedures, directory), strictly separate from per-user record reads. | RAG corpus = public docs (unchanged). Per-user reads = live core via services. Two clearly separated lanes. |
| **Surface** | Public widget stays on the landing page for generic info. An *authenticated* widget (staff/citizen) is the only place record-context answers are allowed, gated by the same permissions. | Decide surfacing per `roles-and-access-control.md`; gate the authenticated widget behind `staff.area`/`awam.portal`. |

**Net:** the chatbot becomes one more *consumer of the service layer* (alongside the staff UI, the citizen
portal, and any future API) — which is exactly why extracting the service layer (P2/§2) is the prerequisite
that makes "controlled interface" possible without duplicating logic.

---

## 8. Reporting structure (one source of truth, consistent definitions)

**Today:** report logic is spread across `LaporanController` (6 narrow), `LaporanPenuhController` (5 wide-CSV),
`StatistikController`, `StatistikSlaController`+`SlaMatrix`, `StatistikPengantaraanController`+`PengantaraanMatrix`,
`KesilapanController`+`KesilapanMatrix`, `KpiController`, `LaporanKhidmatNasihatController`+`LaporanKnService`.
This is broad parity, but with **two definition conflicts**: SLA `khidmat` uses `tarikh_persetujuan` while the
equivalent KPI uses `tarikh_selesai` (G-M7), and "Selesai/Pemfailan Selesai/Belum Difailkan" are **computed**
in `WideExport`/`LaporanPenuhController` and can disagree with stored `forms.status` (A-3).

**Target: a single Reporting kernel (one source of truth, shared definitions, thin presenters).**

```
                         ┌──────────────────────────────────────────────┐
                         │  Reporting Kernel  (app/Domain/Reporting/)     │
                         │                                                │
   ONE definitions  ───► │  • StatusDefinitions  (canonical status sets, │
   registry             │     bucket maps, "Selesai/Difailkan" derive)  │
                         │  • SlaDefinitions     (ONE end-date column per │
   ONE query layer  ───► │     metric — SLA & KPI read the SAME def)     │
   (branch-scoped)       │  • Aggregations       (matrix builders)        │
                         │  • RowSets            (wide/narrow row builders)│
                         └───────────────┬───────────────┬───────────────┘
                                         │               │
                         ┌───────────────▼──┐   ┌─────────▼──────────┐
   thin presenters  ───► │ CSV (WideExport) │   │ PDF (dompdf)       │   Excel (maatwebsite)
                         └──────────────────┘   └────────────────────┘
```

**Rules:**
1. **One definitions registry.** SLA day-counts, KPI day-counts, status buckets, the "Kesilapan Menjana
   Nombor Fail" universal exclusion, and the computed pemfailan status are defined **once** and consumed by
   every dashboard/report/export. SLA and KPI for the same metric read the **same end-date column** (resolve
   G-M7 in the registry, not per-controller).
2. **One branch-scoped query layer.** All-branch management matrices (`SlaMatrix`, `PengantaraanMatrix`,
   `KesilapanMatrix`) bypass row-scope deliberately and are gated to HQ via `statistik.view`; everything else
   reads through `BranchScope`. This is the *only* sanctioned scope bypass and it lives in one place.
3. **Computed sub-statuses are derived from the canonical status definitions, never re-derived ad hoc** — so
   the on-screen status and the report status can no longer diverge (A-3).
4. **Presenters are dumb.** CSV/PDF/Excel are formatting-only layers over the kernel's RowSets/Aggregations;
   no business logic in a presenter. The pengantaraan wide-export columns that degrade to `-Tiada Maklumat-`
   (G-M6) light up automatically once `MediationService` populates the source columns — no report change.
5. **KN and litigation reporting share the kernel** but keep separate definition namespaces (litigation
   `ref_kes` vs advisory `ref_kategori_kn` — D3), so a "dedupe" never accidentally merges the two taxonomies.

---

## 9. Target module / folder layout

The concrete Laravel layout that realizes §1–§8. Controllers stay thin; the `app/Support/` bag is promoted
into `app/Domain/<context>/`; Platform shared services get a real home.

```
app/
├── Domain/                          # business logic moves here (out of controllers + flat Support/)
│   ├── Platform/                    # KERNEL — shared services (the brief's extraction targets)
│   │   ├── Identity/                #   IdentityService (provisionLogin, role↔user_type rules)
│   │   ├── Rbac/                    #   permission seeding, matrix guards (§4.3 fixes)
│   │   ├── BranchScope/             #   one global-scope abstraction (replaces forms-only CawanganScope)
│   │   ├── Audit/                   #   Audit (field-level diffs, actor=id) + Auditable trait
│   │   ├── Notifications/           #   Notifier + queued mailers/Notifications (9 triggers)
│   │   ├── Reporting/               #   ReportKernel: StatusDefinitions, SlaDefinitions, Aggregations, RowSets
│   │   └── StatusTransition/        #   generic transition engine + per-workflow tables
│   ├── Advisory/                    # MODULE 1
│   │   ├── AdvisoryService.php
│   │   ├── AdvisoryProcessingService.php
│   │   ├── FeedbackService.php
│   │   ├── KhidmatBayaran.php       #   (pure fee calc, already exists)
│   │   └── Enums/StatusKn.php
│   ├── Appointment/                 # MODULE 2
│   │   ├── AppointmentService.php   #   book/release/reschedule (move out of KhidmatNasihatService)
│   │   ├── SlotAvailability.php     #   (shared service)
│   │   ├── SlotGenerator.php
│   │   ├── CutiNegeri.php
│   │   └── Enums/StatusTemuJanji.php
│   ├── Case/                        # MODULE 3
│   │   ├── CaseService.php          #   incl. openFromAdvisory (buka-kes seam)
│   │   ├── DecisionService.php      #   keputusan/jana/batal/tutup + 30-day rule + menteri override
│   │   ├── MediationService.php     #   pengantaraan write-path (lights up G-M6 columns)
│   │   ├── CourtService.php
│   │   ├── NoFailGenerator.php
│   │   └── Enums/StatusKes.php
│   ├── Assignment/                  # MODULE 4
│   │   ├── Assignment.php           #   SOLE writer of forms.status_agihan + sejarah_ppuu
│   │   ├── TarikDiriService.php
│   │   ├── LebihMasaService.php
│   │   └── Enums/StatusAgihan.php   #   ONE numeric encoding (StatusAgihan promoted from Support)
│   └── PanelLawyer/                 # MODULE 5
│       ├── PanelApplicationService.php   # semak/sokong/keputusan/promote
│       ├── PeguamLifecycleService.php
│       ├── PengkhususanService.php
│       ├── LawyerDocuments.php
│       └── Enums/{PermohonanStatus,CheckboxValueStatus}.php
├── Http/
│   ├── Controllers/                 # THIN — validate + dispatch only (≤50 lines)
│   │   ├── Awam/                    # MODULE 6 facade (PortalController, PermohonanController, PublicAuthController)
│   │   ├── Reference/               # MODULE 7 CRUD (Cawangan, RefKes, MahkamahRef, Cuti, Jawatan, Poster, Pegawai)
│   │   ├── Admin/                   # User/Role/RolePermission/Audit controllers
│   │   ├── Chat/                    # MODULE 8 ChatbotController (+ future ChatContextService binding)
│   │   └── … (Kes, Agihan, Khidmat, Slot, Peguam, TarikDiri, KemaskiniBidang, Laporan*, Statistik*)
│   ├── Requests/                    # FormRequests (validation only)
│   └── Middleware/                  # SecurityHeaders (+CSP/HSTS), ForcePasswordChange, spatie aliases
├── Models/                          # Eloquent + BranchScope + Auditable trait + enum casts
│   └── Scopes/BranchScope.php
├── Policies/                        # KhidmatNasihatPolicy (+ any new ownership policies)
└── Console/Commands/                # ImportLegacyData (ETL — fix F1/F2/F3), AgihanLebihMasa, BackfillUserRoles

database/
├── schema/legacy-domain.sql         # legacy DDL (do NOT edit post-run; ALTER via new migrations)
├── migrations/                      # reversible Blueprints; FK adds after widening legacy int PKs→bigint
└── seeders/RolePermissionSeeder.php # canonical roles+perms (ADD awam; collapse decorative perms)
```

> **Migration note (P7).** This is a **refactor of where logic lives**, not a rewrite. The 22 `app/Support/`
> classes move into `app/Domain/<context>/` largely as-is; the new code is the six Platform shared services'
> *seams* (transition engine, notifier, report kernel, branch-scope abstraction) plus the controller-to-service
> extraction. Schema changes follow Deliverable 6 §11 (archive + reversible migration + ETL re-test).

---

## 10. How this architecture closes the prior-deliverable findings (traceability)

| Prior finding (deliverable) | Resolved by |
|---|---|
| Dual `status_agihan` encoding; spine→lawyer broken; two front-ends (Gap G-C1/G-H1, WF-1/2, B-1..B-4) | P1 + §3 ownership ("Assignment is sole writer, ONE numeric encoding") + §2.1 `Assignment` service + §5. |
| STUCK-2 `9`, STUCK-3 `TIDAK_HADIR`, STUCK-4 `tolak`-orphan (governance §11) | P3 + §5 `StatusTransition` (define the missing branches) + §9 enums. |
| Email collapse 9→4; credential delivery; cancellation letter (G-H2/G-C2/G-C3) | §2.1 `Notifications` (Notifier) + §6 promote/notify seams. |
| 11 decorative permissions; 3 gating styles; admin-escalation; read leaks (roles §4.1–4.5) | P4 + §4.2 permission collapse + §4.3 hard fixes. |
| `CawanganScope` on `forms` only; OYD/KN PII leak (M8, roles §4.5) | P4 + §4.4 one `BranchScope` over all branch-owned tables. |
| Three schema sources; ETL reads missing table; 8 missing `forms` cols (F1/F2/F3, C1–C3) | P7 + §6 ETL fixes + §9 migration discipline. |
| Name-based case↔lawyer linkage; lost firm data; lawyer IC under 3 names (M9, C7, WF-3) | P1 + §3 ("link by `kp_peguam`/id"). |
| SLA vs KPI end-date; computed-vs-stored status (G-M7, A-3) | §8 Reporting kernel (one definitions registry). |
| Chatbot operational debt; "ask about my case" net-new (map 04, G-C* chat) | P6 + §7 controlled-interface model (microservice kept; hardening + future authorized read). |
| `forms` monolith; `mahkamah_*` split; charset drift; missing FKs/indexes (data review §3/§4/§6/§8) | §3 ownership table "fix" column + §9 migration discipline (deferred normalization epic, sequenced). |

---

## 11. Sequencing note (where Deliverable 5 should start)

The architecture is most cheaply reached in this order (each step unblocks the next):

1. **Extract the service layer (P2/§2)** — move `app/Support/*` → `app/Domain/*`, shrink controllers. No
   behaviour change; makes everything else testable and makes the chatbot-as-interface possible.
2. **Make `Assignment` the sole writer + ONE encoding (P1/§3/§5)** — fixes the single CRITICAL functional
   break (spine→lawyer) and the dual-encoding root cause in one move.
3. **Fix authorization (P4/§4.3)** — admin-escalation + matrix guards + `awam` protection + read leaks +
   one `BranchScope`. Security-blocking; do before any production exposure.
4. **Add the `StatusTransition` engine + enums (P3/§5)** — close the STUCK dead-ends; add DB enum/check.
5. **Stand up `Notifier` + `ReportKernel` (§2.1/§8)** — restore the 9 notification triggers; unify report defs.
6. **ETL + schema reconciliation (P7/§6/§9)** — fix F1/F2/F3 before any real `sistemspk` migration; then the
   deferred normalization epics (`forms` decompose, `mahkamah` merge, FK widening, charset) behind §11 of the
   data review.

> This document defines the **destination**. The per-change plan, tests, and rollout belong to Deliverable 5
> (remediation plan); the safe-removal mechanics belong to Deliverable 6 §11.
