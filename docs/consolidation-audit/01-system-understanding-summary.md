# Deliverable 1 — System Understanding Summary

> **System consolidation audit** — Malaysian legal aid (Jabatan Bantuan Guaman / JBG, under BHEUU — Bahagian Hal Ehwal Undang-Undang). **READ-ONLY analysis.** Written for both a non-technical sponsor and the engineering team.
>
> **Purpose of this document.** Before the gap analysis, the feature matrix, and the removal list make sense, the reader needs one shared mental model: *what each original system was for, where they overlapped, and what the single new "2in1" platform is meant to become.* This document provides that baseline. It is built from the nine system maps (`maps/01`–`09`) and stays consistent with the five companion analysis docs (feature matrix `02`, gap analysis `03`, redundancy/removal `06`, status & workflow governance, roles & access control, data & database review).
>
> **Snapshot point:** consolidated app at commit `735dd4f`, branch `main`.

---

## 1. The business goal in one paragraph (for the sponsor)

The Jabatan Bantuan Guaman runs Malaysia's **legal aid programme** — helping eligible citizens who cannot afford a lawyer. Over the years JBG accumulated **four separate, disconnected computer systems**, each built by a different team in a different technology, each holding a slice of the same citizens, the same cases, and the same staff. A person seeking help, a case file, and the officers handling it were scattered across systems that could not talk to each other, used **three different login books**, and stored passwords in plain text. The **2in1 platform** is a single, modern web application that **fuses all four systems into one** — one login model, one user directory, one permission system, one database, one set of reports — so that a citizen's journey from *"I need legal advice"* all the way to *"my court case is closed"* lives in one place, with proper security and a clear audit trail.

**"2in1" is the project's shorthand** for merging the two legacy operational systems (lawyer panel + case records) — but in practice the consolidation absorbs **four** origin systems plus a chatbot, as set out below.

---

## 2. The four original systems + the chatbot (what each one did)

Think of the legal aid lifecycle as a relay race. Each original system ran one leg of the race and handed off (badly, by hand, or not at all) to the next.

| # | Original system | Plain-language role | Technology | Owner | Map |
|---|---|---|---|---|---|
| 1 | **Khidmat Nasihat / Janji Temu** (iGuaman advisory + appointment) | *The front door.* A citizen describes a problem, the system screens eligibility, books an appointment at the right branch, and an officer gives legal advice. | ASP.NET Core 8 (C#) backend + Nuxt 2 (Vue) frontend, PostgreSQL | JBG / BHEUU | `03` |
| 2 | **sistem-peguam-panel** (Lawyer Panel) | *The lawyer supply chain.* Private lawyers apply to join JBG's panel, get vetted and approved, and JBG distributes cases to them; handles a lawyer withdrawing from a case and a lawyer dying mid-case. | Raw procedural PHP, MySQL/MariaDB (`sistemspk`) | JBG / BHEUU | `01` |
| 3 | **sistem-rekod-kes** (Case Records) | *The case backbone.* The full legal-aid case file: application intake, the director's accept/reject decision, file-number generation, mediation, court tracking, closure, and all the statistics/KPI/SLA reporting. | Raw procedural PHP, **same MySQL DB** (`sistemspk`) | JBG / BHEUU | `02` |
| 4 | **cbjbg** (AI@JBG chatbot) | *The public information desk.* A standalone AI assistant that answers general questions about legal aid (eligibility, procedures, fees, staff directory). Public-information only — it never touches case data. | Python / FastAPI, OpenAI GPT-4o + FAISS retrieval | JBG / BHEUU | `04` |

> Systems **2 and 3 already shared one physical database** (`sistemspk`) — they were two separate PHP front-ends bolted onto one schema, joined through a single giant `forms` table (one row = one citizen's case). Systems **1 and 4 were fully independent** with their own databases.

### 2.1 System 1 — Khidmat Nasihat / Janji Temu (the advisory front door)

A citizen registers (by IC number), describes their matter, and the system:
- **Screens eligibility** (a "saringan" means-test — annual income threshold **RM 50,000**, with a contribution path and a criminal-companion bypass);
- **Classifies** the request against a 3-level case-category tree (Sivil / Syariah / Pendamping Jenayah / Pendamping Guaman);
- **Books an appointment** ("Janji Temu") in an available slot at the right branch (≥ 4 working days ahead, skipping weekends/holidays/closures);
- Routes it to an officer (**PEGAWAI KHIDMAT NASIHAT / PKN**) who accepts, conducts the session, marks attendance, and completes it;
- Collects **feedback** (Maklum Balas) and produces advisory reports.

**A critical caveat (from map `03`):** in the legacy product the **frontend was the real, complete application and the backend was a thin scaffold** that never implemented most of what the frontend called (no category tree, no slot engine, no feedback, no holidays, no reports, no authorization). So the 2in1 rebuild had to treat the **frontend behaviour as the specification**, not the backend code. The status lifecycle to replicate: `DRAF → BAHARU → DALAM PROSES → SELESAI` (+ `BATAL`).

### 2.2 System 2 — sistem-peguam-panel (the lawyer panel)

Manages the **panel-lawyer lifecycle**:
- **Public registration** (a 7-section form, ~70 fields, 18 PDF document uploads);
- **3-tier approval** of new lawyers: clerk → Director (Pengarah) → Director-General (Ketua Pengarah);
- **Case assignment ("Agihan")** through a multi-tier chain: Pengarah accepts a new case → **PPUU** (Penolong Pegawai Undang-Undang, the case distributor) picks a lawyer → Pengarah endorses → Ketua Pengarah approves → the lawyer accepts/rejects the offer;
- **Withdrawal ("Tarik Diri Mewakili OYD")** — a lawyer formally steps away from representing an assisted person (OYD = *Orang Yang Dibantu*), reviewed up the same chain;
- **Specialisation changes** (Bidang Pengkhususan add/drop) and the **lawyer lifecycle** including the safety-critical **death-redistribution** (when an active lawyer dies or is deactivated, all their live cases are returned to the pool so no assisted person is left unrepresented).

Drove a numeric `forms.status_agihan` state machine (legacy codes `0–20`).

### 2.3 System 3 — sistem-rekod-kes (the case records backbone)

The end-to-end **case file**, organised as 7 stages ("Peringkat"):
1. **Permohonan** — citizen application intake (auto-derives age from IC);
2. **Keputusan + Jana No. Fail** — the director's accept/reject decision (with a 30-day perakuan rule and a Minister-override path) and official **file-number generation** (`JBG.<state>(<jenis_kes>)<seq>/<MMYY>`);
3. **Pengantaraan** — mediation (assign mediator, schedule hearings/sidang, reschedule history);
4. **Pengendalian / Agihan** — assign the case to a JBG officer **or** a panel lawyer (the seam into System 2);
5. **Kes Mahkamah** — court tracking + per-mention progress reports (Laporan Kes);
6. **Status Fail / Penyelesaian** — orders, costs, notifications;
7. **Tutup Fail** — official closure.

Plus the heavy **reporting layer**: dashboards, KPI/SLA matrices (40/60/120/7-day thresholds), ~13 CSV exports, ~15 print views, and an e-Poster board. Everything revolves around the single wide `forms` table.

### 2.4 System 4 — cbjbg (the AI chatbot)

A **decoupled public-information assistant**. It answers general JBG/legal-aid questions using a retrieval index over legal-aid PDFs and a staff directory, plus some live web/portal scraping. It has **no access to case records, users, or permissions** and is correctly the easiest piece to keep as-is.

---

## 3. How the four systems overlapped (and why merging them was necessary)

The overlaps are exactly why a consolidation was justified — the same real-world things were modelled multiple times, inconsistently.

| Overlap | What was duplicated across systems | Consequence in the old world |
|---|---|---|
| **The case file (`forms`)** | Systems 2 and 3 literally **shared the `forms` table** — the lawyer panel *wrote* `status_agihan`/assignment history that case-records only *read* and displayed. One system's writes silently drove the other's screens (and rekod-kes even linked to an `maklumat-agihan-semula.php` screen that **did not exist** — a dead admin link). | Cross-system coupling with no clear ownership of the assignment workflow. |
| **Users / identity** | **Three separate user tables** across the systems — `users` (staff, ~264), `users_peguam_panel_2` (lawyers, ~586), `users_peguam_panel_3` (~116) — plus iGuaman's own ASP.NET Identity store. The same person could exist (or not) in several. | Three login books, three password regimes (all plaintext in the PHP systems), no single directory. |
| **Roles** | Each system had its own role scheme — peguam-panel `peranan 0–5`, rekod-kes `peranan 0–2` with a "Putrajaya = HQ" rule, iGuaman string roles (SUPERADMIN, PEMBANTU TADBIR, PKN, PELANGGAN, PENGURUSAN). | No unified authorization; iGuaman enforced roles **only in the browser** (server-side `[Authorize]` was commented out). |
| **Case-type taxonomies** | Litigation `ref_kes` (case records) vs the advisory 3-level category tree (Khidmat Nasihat). | Two legitimately different taxonomies — but easy to confuse and wrongly "dedupe". |
| **Branches (cawangan)** | Stored as a free-text string in the PHP systems, as reference tables in iGuaman. | No single branch master; branch-scoping logic re-implemented per system. |
| **Mediation, court reference, audit, attachments, PDF printing, email** | Each PHP system bundled its own FPDF/Dompdf prints, its own PHPMailer setup (with **hardcoded Gmail credentials**), its own captcha. | Repeated infrastructure, repeated security debt. |
| **Citizen vs case identity** | The same citizen could be an iGuaman advisory applicant (System 1) AND a rekod-kes case subject (System 3) with no link between the two. | The natural "advice → case" journey was a manual hand-off across disconnected systems. |

**The single most important integration seam:** an advisory session that concludes someone *does* need representation should become a litigation case. In the old world that was a manual re-entry across two systems. The new platform builds this bridge explicitly (see §4, "Buka Kes").

---

## 4. The intended role of the unified 2in1 platform

The 2in1 platform is **one Laravel 13 application** (PHP 8.3, MySQL 8.4, Blade + vanilla JS) that replaces all four origin front-ends and unifies their data. Its intended role, in plain terms:

**4.1 One front door, three audiences.** A single application with three landing experiences off one user directory:
- **Citizens (Awam)** — register/login by IC, lodge an advisory request, book and manage their own appointment, upload documents, give feedback.
- **Panel lawyers (Peguam)** — their own portal to receive case offers, accept/reject, file reports, request withdrawal, and manage their profile/specialisations.
- **Staff (JBG officers, clerks, directors, PPUU, Ketua Pengarah)** — the operational workspace covering advisory processing, case records, the full assignment chain, reference-data maintenance, and reporting.

**4.2 One end-to-end journey.** The platform stitches the four relay legs into one continuous flow:

```
CITIZEN asks for advice (Khidmat Nasihat)
   → eligibility screening (saringan, RM50k means-test)
   → book appointment (Janji Temu slot engine)
   → officer processes the advisory session → SELESAI
        → "Buka Kes": completed advice spawns a litigation case (forms row)   ← NEW BRIDGE
             → director's Keputusan (accept/reject) + file-number generation
                  → mediation / court tracking
                  → assign to JBG officer OR panel lawyer (Agihan, 3-tier spine)
                       → lawyer accepts, handles, reports, possibly withdraws (Tarik Diri)
                            → file closure (Tutup Fail)
   → feedback (Maklum Balas) + statistics/KPI/SLA reporting throughout
+ a public AI chatbot answering general legal-aid questions on the landing page
```

The **"Buka Kes"** step (completed advisory → new `forms` litigation case) is genuinely new value — it did not exist in *any* legacy system and is the connective tissue the consolidation was meant to create.

**4.3 One security and governance model.** This is where the platform most clearly improves on the past:
- **Single `users` table + bcrypt** via `Auth::attempt` (replacing three plaintext password stores — a CRITICAL legacy fix);
- **One RBAC layer** — `spatie/laravel-permission` with 9 roles (`admin`, `ketua_pengarah`, `pengarah`, `koordinator`, `pegawai`, `ppuu`, `pembantu_tadbir`, `peguam`, `awam`) and an admin-editable permission matrix;
- **Server-side authorization** (replacing iGuaman's browser-only gating);
- **Forced password change**, active-only login, security headers, throttling, captcha + honeypot on public forms, private-disk attachments with ownership checks, and a single **audit trail**;
- **Branch isolation** via a `cawangan` master and a `CawanganScope` so officers see only their branch's data;
- **No hardcoded secrets / no SQL injection** (Eloquent throughout, secrets in `.env`).

**4.4 One reporting and reference spine.** A single `forms` case spine, a single set of statistik/KPI/SLA/laporan modules (CSV + Excel + PDF), one set of reference masters (branches, courts, holidays, case-types, the advisory category tree), and one audit log.

**4.5 The chatbot stays a microservice.** Per a locked decision, cbjbg is **kept as the Python service** behind a thin Laravel proxy + a Blade widget on the public landing page — not rebuilt. It remains read-only and has no access to platform records.

**Design constraints (locked):** plain custom Laravel auth + Blade only — **never Filament/Breeze/Jetstream** (a hard project rule born from Hostinger deployment problems); brownfield schema (keep legacy table/column names verbatim for the ~20 imported domain tables, rebuild only the auth layer); deploy to Hostinger via GitHub webhook.

---

## 5. Current state in one honest sentence (bridge to the other deliverables)

The 2in1 platform has reached **broad structural parity** — the case spine, the 3-tier assignment chain, withdrawal, lawyer lifecycle/death-redistribution, the advisory + slot engine, the citizen portal, feedback, and most of the reporting are **built and largely working**, and the security posture is a major upgrade over all four legacy systems. The remaining work clusters in a handful of well-understood areas that the companion deliverables detail:

| Theme | One-line description | Detailed in |
|---|---|---|
| **Assignment hand-off is broken** | The 3-tier spine writes a *numeric* offer status (`'1'`), but the lawyer's offer list reads the *string* `'Ditawarkan'`, so spine-approved offers never reach the lawyer. Two assignment front-ends write two encodings to one column. | `03` gap analysis (G-C1, G-H1); status & workflow governance (B-1); feature matrix §J |
| **Notifications collapsed** | Email triggers fell from ~9 (legacy) to 4; lawyer credentials, approval decisions, and the withdrawal cancellation letter are no longer delivered. | `03` (G-C2, G-C3, G-H2) |
| **Lifecycle dead-ends** | Several states have no exit screen: Pengarah-rejected case (`9`), advisory no-show (`TIDAK_HADIR`), payment never confirmed, rejected appointment. | status & workflow governance (STUCK register) |
| **Access-control gaps** | A non-admin can mint an admin; ~11 seeded permissions are decorative (declared but unenforced); the `awam` citizen role is renamable/deletable; branch isolation covers only `forms`. | roles & access control (§4) |
| **Data/schema reconciliation** | Three competing schema sources for the lawyer tables; the ETL reads a `users_peguam_panel_3` table that does not exist in the dump; `forms` is short ~8 columns; many soft-links have no foreign keys. | data & database review (F1–F7) |
| **Redundancy to retire** | Dead `items` table, superseded `butiran_peguam_panel` v1, the parallel single-step assignment path — all flagged for careful removal. | `06` redundancy & removal |

None of these undo the core achievement: **four siloed systems and a chatbot are now one platform with one identity model and one case journey.** The audit's job is to finish hardening that platform — fix the broken hand-off, restore the missing notifications and letters, close the lifecycle dead-ends, tighten access control, and reconcile the schema — before production.

---

## 6. Glossary (for the sponsor)

| Term | Meaning |
|---|---|
| **JBG / BHEUU** | Jabatan Bantuan Guaman (Legal Aid Department), under Bahagian Hal Ehwal Undang-Undang. |
| **Khidmat Nasihat (KN)** | Legal advisory service — the "front door" advice request. |
| **Janji Temu** | The appointment / slot booking for an advisory session. |
| **Peguam Panel** | A private lawyer on JBG's approved panel who takes assigned legal-aid cases. |
| **OYD (Orang Yang Dibantu)** | The assisted person — the citizen receiving legal aid. |
| **Agihan** | Assignment / distribution of a case to a lawyer or officer. |
| **Tarik Diri** | A lawyer's formal withdrawal from representing an assisted person. |
| **PPUU** | Penolong Pegawai Undang-Undang — the officer who distributes cases to lawyers. |
| **PKN** | Pegawai Khidmat Nasihat — the advisory officer who conducts a KN session. |
| **Pengarah / Ketua Pengarah** | Director / Director-General — the approval tiers. |
| **Cawangan** | A JBG branch. |
| **No. Fail** | The official case file number generated on approval. |
| **Saringan** | Eligibility screening / means-test. |
| **Buka Kes** | "Open case" — the new bridge turning a completed advisory into a litigation case. |
| **`forms`** | The single wide database table that is the case spine across the whole platform. |
| **`status_agihan` / `status_kn`** | The assignment-state and advisory-state machines. |

---

*Sources: maps `01`–`09` under `docs/consolidation-audit/maps/`; companion analysis docs `02-feature-comparison-matrix.md`, `03-gap-analysis.md`, `06-redundancy-and-removal-list.md`, `status-and-workflow-governance.md`, `roles-and-access-control.md`, `data-and-database-review.md`. Read-only audit — no source code modified.*
