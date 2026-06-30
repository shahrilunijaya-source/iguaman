# iGuaman 2in1 — Consolidation Audit (Index)

> Senior-analyst audit of the 4-system consolidation. **Read-only — no code changed.**
> Snapshot: commit `735dd4f`, branch `main`. Scope: Phases 1–8 of the consolidation brief (understand → inventory → gap → redundancy → architecture → status → roles → data). Phases 9–12 (cleanup / execution / testing) are **planned, not executed** — gated on the decisions in §6.

## 1. What was consolidated

| # | Source | On disk | Stack | Became (2in1 domain) |
|---|--------|---------|-------|----------------------|
| 1 | Lawyer panel | `sistem-peguam-panel` | raw PHP, MySQLi, FPDF | Peguam Panel + Agihan spine + Tarik Diri |
| 2 | Case records | `sistem-rekod-kes` | raw PHP, same `sistemspk` DB | Rekod Kes (7 peringkat) + Pengantaraan + Mahkamah + Statistik/Laporan |
| 3 | Advisory + appointment | `be_iguaman-master` (.NET8/EF/PgSQL) + `fe-iguaman-master` (Nuxt2) | C# + Vue | Khidmat Nasihat + Janji Temu + Awam citizen portal |
| 4 | Chat | `cbjbg` | Python FastAPI, GPT-4o, FAISS RAG | Chatbot proxy + landing widget |

`sistem-rekod-kes-laravel/` and `spk-laravel/` are **earlier partial Laravel rewrites** — intermediate artifacts, not sources. Archive; keep `sistem-rekod-kes-laravel` migrations as the best `forms`-schema reference.

## 2. Verdict

**The consolidation is structurally sound and ~85% feature-complete, but NOT releasable today.** Five CRITICAL blockers break core journeys end-to-end. Security baseline is good (bcrypt everywhere, no backdoor, RBAC enforced) — the real security hole is privilege escalation in user/role admin, not the legacy plaintext passwords (already fixed).

## 3. Deliverables (this folder)

| File | Deliverable |
|------|-------------|
| `01-system-understanding-summary.md` | **D1** business goal + role of each system |
| `02-feature-comparison-matrix.md` | **D2** ~95 features × 4 sources × status × action |
| `03-gap-analysis.md` | **D3** 3 CRITICAL / 7 HIGH / 10 MED / 8 LOW gaps |
| `04-recommended-architecture.md` | **D4** 8 modules + Platform kernel + 6 service extractions |
| `05-end-to-end-process-flows.md` | **D5** 5 journeys × 12 elements + dead-end register |
| `06-redundancy-and-removal-list.md` | **D6** removals, grep-verified, archive-gated |
| `07-implementation-plan.md` | **D7** 11 phases (security → ETL → blockers → polish → gated removals) |
| `08-final-validation-checklist.md` | **D8** 9 acceptance gates + 55 e2e test scenarios |
| `status-and-workflow-governance.md` | Phase 6 — 9 status fields, transition tables, stuck-record register |
| `roles-and-access-control.md` | Phase 7 — 9 roles, 40 perms, escalation paths |
| `data-and-database-review.md` | Phase 8 — FKs, indexes, source-of-truth conflicts |
| `maps/01..09-*.md` | Raw per-system maps (evidence base) |

## 4. CRITICAL blocker register (must close before go-live)

| ID | Blocker | Evidence | Fix |
|----|---------|----------|-----|
| **BL-1** | 3-tier agihan spine never reaches lawyer | `AgihanService` writes numeric `status_agihan='1'`; `PeguamController::tawaran/dashboard/terima` filter literal string `'Ditawarkan'` (`:45,46,70`). Spine offers invisible; lebih-masa re-loops forever | Filter `StatusAgihan::bucketValues([DITAWARKAN])`; write numeric on accept |
| **BL-2** | Pengarah-rejected case dead-ends at `status_agihan='9'` | In no list bucket; `stage()` returns null | Add recovery bucket + 9→re-review/close transition + screen |
| **BL-3** | KN no-show hangs forever | `kehadiran(false)` → `temu=TIDAK_HADIR` but `status_kn` stays `DALAM_PROSES`; `selesai()` needs `HADIR` | Define `TIDAK_HADIR→SELESAI` (or →rebook) |
| **BL-4** | Non-admin can mint `admin` | `UserRequest::authorize()` hardcoded `true`; `UserController::ROLES` includes admin; `/pengguna` gated `urus.pengguna` held by pengarah/koordinator/ketua_pengarah | Block admin assignment by non-admin; role↔user_type guard |
| **BL-5** | Akses matrix self-escalation | `RolePermissionController::update` blind `syncPermissions` on any role | Editable-permission allowlist; protect sensitive perms |

Plus HIGH: dual `status_agihan` encoding (root cause of BL-1), `awam` role renamable/deletable (breaks citizen gate), collapsed notifications (9 legacy triggers → 4), no public application-status lookup, read-side branch-isolation leaks (OYD PII cross-branch), `legacy:import` fatals on missing `users_peguam_panel_3`.

## 5. Verified-good (do NOT "fix" — anti-regression list)

- Passwords are **already bcrypt** (`SystemAuthController:74`, `PublicAuthController:35`, `PasswordResetController:50`) — the classic legacy must-fix is done.
- `app/` and `config/` are **clean of hardcoded secrets** (only the `cbjbg` microservice + JBG Gmail app-password need operational rotation).
- 7-day **Lebih Masa auto-reassignment IS implemented** + scheduled daily 07:00 (`AgihanLebihMasa` command + `LebihMasaService` + test) — planning docs/maps wrongly called it missing.
- No backdoor auth (legacy `log_masuk_backdoor.php` not ported); RBAC enforced at routes; `CawanganScope` on `forms`; Buka-Kes advisory→litigation bridge present; `maklum_balas` idempotent.

## 6. Open decisions (gate execution — see §questions to product owner)

1. **Agihan path** — retire single-step `AgihanController` (spine canonical) or keep as officer override? Determines the dual-encoding fix.
2. **Production mail** — is `MAIL_*` SMTP provisioned? (today `MAIL_MAILER=log`). Gates credential delivery, decision emails, cancellation letters.
3. **Data migration** — migrate `sistemspk` production data or fresh start? + which dump is authoritative for `_3..6`/`sejarah_ppuu` (import fatals today).
4. **KN payment** — is `status_bayaran` a real v1 payment gate, or fee informational?
5. **Scope** — single vs multi-branch · BM-only vs bilingual · mediator leave/elaun in scope · prison/JKM officer roles needed · which official PDFs are legally required.

## 7. Recommended next step

Execute `07-implementation-plan.md` in order. **Phase 1 (RBAC escalation BL-4/BL-5) is safe, no-schema, high-value — start there regardless of the §6 decisions.** Phases 2+ (ETL, functional blockers) need the decisions above first.
