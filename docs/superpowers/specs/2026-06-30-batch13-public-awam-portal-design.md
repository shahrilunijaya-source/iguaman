# Batch 13 — Public Awam Portal (Design Spec)

**Date:** 2026-06-30
**Branch target:** `batch-7-rbac` (current integration branch for the janji-temu port)
**Status:** Approved design — pending implementation plan.

Final batch of the iGuaman *Janji Temu / Khidmat Nasihat* port. Adds the public-facing
citizen (`awam`) portal: self-service legal-advisory application + appointment booking.
Closes the last `.NET`-source gap. Depends on, and feeds, the already-built staff side
(Batches 8–11).

> Renumbering note: the source port plan (`context/port-plan-iguaman-janjitemu.md`) calls
> this "Batch 12 — Public portal". After RBAC became its own Batch 7, the sequence shifted
> to 7–13; this is **Batch 13**. Same scope as port-plan §9 row "12 — Public portal" and the
> LOCKED decision §7.1 (FULL public portal).

---

## 1. Goal & scope

Citizens apply for legal advice (Khidmat Nasihat) and book an appointment slot themselves,
online, without staff data entry. Their submission lands in the **existing** officer
processing queue (Batch 11) unchanged — the portal is a new front door onto the same pipeline.

**In scope**
- Public registration + login (IC + password; email optional).
- Citizen KN application wizard (`DIRI_SENDIRI` only) with own eligibility screening (saringan).
- Self-service appointment slot booking (reuses existing slot engine).
- "My applications" dashboard: list own KN, status, appointment, reference number.
- Cancel own appointment.
- Reschedule own appointment.
- Submit satisfaction feedback (`maklumbalas`) after completion.
- Upload supporting documents with an application.
- Dedicated public-facing layout (distinct from the internal staff shell).

**Out of scope (flagged)**
- Batch 12 feedback **reports / statistik** — this spec builds only the feedback *submission*
  + storage. The 8 charted reports remain Batch 12.
- SMS/email appointment reminders.
- Online payment gateway — fee is computed and displayed (existing `KhidmatBayaran`), not collected.

---

## 2. Decisions (LOCKED — 2026-06-30)

| # | Decision | Rationale |
|---|---|---|
| D1 | **Auth = IC + password, email optional, no mandatory verification.** Captcha + rate-limit + honeypot on guest routes. | Matches legacy PELANGGAN IC login; lowest friction for citizens who lack reliable email. |
| D2 | **Same `users` table + same `web` guard** (Approach A). Add `awam` user_type + `awam` Spatie role; login by IC via a dedicated public auth controller. | Reuses sessions/Spatie/hashing; mirrors the existing lawyer (same table, different `user_type`) pattern. Port plan §2 says reuse `users` + auth. |
| D3 | **Portal scope = baseline + all 4 extras**: cancel, reschedule, feedback submit, document upload. | User decision 2026-06-30. |
| D4 | **Dedicated public layout** (`layouts/awam`), not the staff `system.css` shell. | A public portal must not read as an internal admin tool. |
| D5 | **Password reset is email-only.** Citizens without an email cannot self-reset → branch-assisted reset (documented), no self-serve path. | Email is optional (D1); no second channel (SMS/OTP) is in scope. |
| D6 | **Extract a shared `KhidmatNasihatService`** for KN creation + slot-booking, used by both the staff wizard and the public wizard. | DRY — prevents the two creation paths from drifting. Behavior-preserving refactor of the staff controller. |

### Alternatives considered (and rejected)
- **Separate `awam` auth guard (multi-guard):** hard session isolation, but adds multi-guard
  complexity and fights `CawanganScope` + Spatie, which assume the default guard. Rejected — overkill
  for a plain-Laravel codebase.
- **Anonymous booking (no account, IC + DOB):** lowest friction but no "my applications"
  dashboard and weak abuse control. Rejected by D1.

---

## 3. Current-state reuse surface (verified 2026-06-30)

| Asset | State | Use in Batch 13 |
|---|---|---|
| `users.user_type` enum `['staff','lawyer']` | exists | **add** `awam`. |
| `users.nokp` (IC), `users.email` nullable | exists | citizen credential = `nokp`; email optional. |
| `khidmat_nasihat.id_pengguna` → `users.id` (nullable) | exists, comment says *"citizen accounts arrive in batch 13"* | owner FK for citizen submissions. |
| `khidmat_nasihat.jenis_permohonan` enum incl. `DIRI_SENDIRI` | exists | citizen path = `DIRI_SENDIRI`. |
| `SlotAvailabilityService`, `KhidmatBayaran`, `KhidmatProsesService`, `SlotGenerator` | exist (`app/Support`) | reuse for booking, fee, officer pipeline. |
| `KhidmatProsesController` officer queue (Batch 11, branch-scoped) | exists | citizen `BAHARU` rows flow in automatically — **no officer-side change**. |
| Saringan (3-modal server-side gate, session-persisted, tamper-proof) | exists (slice 3, staff) | replicate the *pattern* for the public wizard. |
| `uploaded_files` private-disk pattern, `audit_trail`, number captcha, honeypot (peguam daftar) | exist | reuse. |
| KN creation logic | lives in `KhidmatNasihatController::store` (not yet a service) | **extract** to `KhidmatNasihatService` (D6). |
| `maklumbalas` table | **absent** | new migration. |
| `no_rujukan` on `khidmat_nasihat` | **absent** | new column (citizen-facing reference). |

---

## 4. Data model changes

### 4.1 Migrations
1. **Alter `users.user_type`** — add `awam` to the enum.
2. **Alter `khidmat_nasihat`** — add `no_rujukan` (string, unique, nullable; format `KN-{YYYY}-{zero-padded id}`),
   backfilled/derived on submission. Citizen-facing reference for status lookup.
3. **Create `maklumbalas`** —
   - `id` PK
   - `id_khidmat` → `khidmat_nasihat.id`, **unique** (one feedback per KN), `cascadeOnDelete`
   - rating columns (e.g. `kepuasan` tinyint 1–5; optional sub-ratings to match source survey)
   - `komen` text nullable
   - `created_at` / `updated_at`
4. **Document attachments** — reuse the existing `uploaded_files` pattern (polymorphic owner =
   `khidmat_nasihat`); add only if the existing table is not already polymorphic-capable (verify in planning).

### 4.2 RBAC
- New Spatie role **`awam`**.
- New permission **`awam.portal`** assigned only to `awam`. Citizens receive **no** staff/khidmat
  permission. `Gate::before` admin bypass does not grant citizen-owned rows (admins use the staff side).
- `User::TYPE_AWAM = 'awam'` const + `isAwam()` + `homeRoute()` returns the awam dashboard.

---

## 5. Routes & controllers

All citizen routes under the `/awam` prefix, rendered in the dedicated `layouts/awam` shell.

### 5.1 Guest (public, unauthenticated)
| Route | Controller | Protection |
|---|---|---|
| `GET /awam/daftar` · `POST /awam/daftar` | `Awam\PublicAuthController@create/store` | captcha + `throttle` (low) + honeypot |
| `GET /awam/login` · `POST /awam/login` | `Awam\PublicAuthController@show/attempt` | captcha + `throttle:10,1`; resolve by `nokp`; generic error |
| `POST /awam/logout` | `Awam\PublicAuthController@logout` | auth |
| password reset (request/email/reset/update) | reuse/extend `PasswordResetController` | email-only (D5) |

### 5.2 Citizen (auth + `role:awam`, every row owner-scoped via policy)
| Route | Controller | Notes |
|---|---|---|
| `GET /awam` | `Awam\PortalController@index` | dashboard: my applications + status |
| `GET /awam/permohonan/saringan` · `POST …/saringan` | `Awam\PermohonanController@saringan/saringanSemak` | server-side eligibility gate, session-persisted, tamper-proof |
| `GET /awam/permohonan/baharu` · `POST /awam/permohonan` | `Awam\PermohonanController@create/store` | 4-step wizard, `DIRI_SENDIRI`; calls `KhidmatNasihatService` |
| `GET /awam/permohonan/{kn}` | `Awam\PermohonanController@show` | owner policy; status + appointment + `no_rujukan` |
| `POST /awam/permohonan/{kn}/batal` | `Awam\PermohonanController@cancel` | future-dated & not-yet-attended only |
| `POST /awam/permohonan/{kn}/jadual-semula` | `Awam\PermohonanController@reschedule` | release old + book new in one txn |
| `POST /awam/permohonan/{kn}/lampiran` · `GET …/lampiran/{file}` | `Awam\PermohonanController@upload/download` | strict validation; owner-gated download |
| `GET /awam/permohonan/{kn}/maklumbalas` · `POST` | `Awam\MaklumBalasController@create/store` | only after `SELESAI`; one per KN |

`{kn}` constrained `whereNumber`. Authorization via `KhidmatNasihatPolicy` (owner = `id_pengguna === auth id`
**and** `user_type === awam`).

---

## 6. Citizen flows

### 6.1 Register / login
IC + password (+ optional email). Captcha + rate-limit + honeypot. New account: `user_type=awam`,
role `awam`, `nokp`=IC. Login resolves the user by `nokp`; failure returns a **generic** message
(no account-existence enumeration). Redirect-by-type guards keep citizens off `/system` and staff off `/awam`.

### 6.2 Apply (KN wizard) — `DIRI_SENDIRI` only
1. **Saringan** — own 3-modal eligibility gate; pass flag persisted in session (server-side, tamper-proof);
   `store()` re-checks it. Citizens can never be `SEBAGAI_WAKIL`.
2. **Maklumat Permohonan** — identity (prefilled from account), case category (`ref_kategori_kn` tree), address.
3. **Bayaran** — `KhidmatBayaran` computes the citizen fee path (RM10, or RM260 sumbangan). The
   wakil RM0 path is unreachable from the public portal.
4. **Slot** — pick branch → available date → time via `SlotAvailabilityService`; booked in
   `DB::transaction` + row lock (double-book safe).
5. **Perakuan** — declaration → `statusKN = BAHARU`, `id_pengguna = auth id`, `no_rujukan` assigned.

Submission then appears in the branch-scoped officer queue (Batch 11) with no further wiring.

### 6.3 My applications / status
List own KN with status, appointment date/time, reference number, fee, and available actions
(cancel / reschedule / feedback) gated by current status.

### 6.4 Cancel
Allowed only while the appointment is **future-dated and not yet attended**. Releases the slot
(restores capacity) and sets the KN/appointment to `BATAL`. Audit-logged.

### 6.5 Reschedule
Release the old slot and book a newly chosen available slot in a **single transaction** (the
edge-case-heavy path: lock both, fail closed if the new slot fills concurrently). Same future-dated /
not-attended guard.

### 6.6 Feedback
After `SELESAI`, citizen submits one `maklumbalas` (rating + optional comment). Re-submission blocked
by the unique `id_khidmat`. Report consumption is Batch 12.

### 6.7 Document upload
Reuse `uploaded_files` + private disk. Validation: mime allowlist (`pdf/jpg/png`), per-file size cap,
max file count. Download is owner-gated and streamed (never a public URL).

---

## 7. Targeted refactor (D6)

Extract `app/Support/KhidmatNasihatService` with a `create(...)`/`book(...)` API that performs the
KN-row creation + payment computation + slot-booking transaction. Refactor
`KhidmatNasihatController::store` (staff) to delegate to it — **behavior-preserving**, covered by the
existing Batch 9 tests staying green. The public `Awam\PermohonanController@store` calls the same
service with awam-specific constraints (`DIRI_SENDIRI`, citizen fee path, `id_pengguna`). No
unrelated refactoring beyond this shared path.

---

## 8. Security (public attack surface — primary concern)

| Control | Application |
|---|---|
| Captcha | register + login (reuse existing number captcha). |
| Rate-limit | register (low), login `throttle:10,1`, booking submit, feedback, upload. |
| Honeypot | register form (existing peguam-daftar pattern). |
| Authorization | `KhidmatNasihatPolicy` — every citizen read/write scoped to owner; cross-citizen access returns 403/404. |
| Mass assignment | explicit FormRequest + fillable whitelist; never trust `id_pengguna`/status/fee from the client. |
| Upload safety | mime allowlist, size + count caps, private disk, owner-gated streamed download. |
| Enumeration | generic auth errors; no "IC not found" leak. |
| CSRF | default Laravel web middleware on all state-changing routes. |
| Saringan integrity | eligibility pass persisted server-side in session, re-checked in `store()` — not a client field. |
| Separation | `user_type`/role redirect guards; citizens cannot reach staff routes nor vice-versa. |

Source bugs explicitly **not** copied (port plan §8): unenforced `[Authorize]`, dropped FKs on create,
UTC+8 double-offset, missing slot/double-book logic — all already handled on the staff side and reused.

---

## 9. Testing

Feature tests (PHPUnit, matching existing suite discipline):
- Register: success, captcha-fail, throttle, honeypot trip, duplicate-IC.
- Login: success by IC, generic failure, throttle; redirect-by-type (citizen → `/awam`, blocked from `/system`).
- Owner isolation: citizen A cannot view/cancel/feedback citizen B's KN (403/404).
- Wizard: happy path → `BAHARU` + `no_rujukan` + `id_pengguna` set; **saringan-bypass blocked** at `store()`.
- Fee correctness: citizen path = RM10 / RM260; wakil RM0 path unreachable.
- Slot: concurrent double-book prevented; reschedule releases old + books new atomically; failure rolls back.
- Cancel: allowed only future-dated/not-attended; releases slot.
- Feedback: only after `SELESAI`; second submission rejected.
- Upload: mime/size/count validation; download owner-gated.
- **Regression:** existing Batch 9 staff-wizard tests stay green after the `KhidmatNasihatService` extraction.

---

## 10. Open items to confirm during planning
- Whether `uploaded_files` is already polymorphic-capable or needs a small migration for `khidmat_nasihat` ownership.
- Exact `maklumbalas` rating columns to mirror the source satisfaction survey fields (FE `tahap-kepuasan`).
- Final `no_rujukan` format + backfill for any pre-existing staff-created KN rows (likely none in prod yet).
