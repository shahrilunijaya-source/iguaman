# ADR-0002 — ButiranPeguamPanel v1 vs v2–6 source of truth (CODE-03)

**Status:** Accepted · **Date:** 2026-07 · **Audit ref:** CODE-03

## Context

Two representations of a panel lawyer's detailed profile exist:

- **`butiran_peguam_panel` (v1)** — a single wide legacy table (`App\Models\ButiranPeguamPanel`).
- **`butiran_peguam_panel_2` … `_6` (v2–6)** — a legitimate normalized split
  (bio / qualifications / firm / bank / specialisation).

CODE-03 flagged the risk that v1 overlaps v2–6 facts with no visible sync between them, i.e.
two sources of truth that could diverge.

## Findings (verified in source)

- **v2–6 are the only tables written by the current app.** Registration
  (`PeguamController` / `LawyerDocuments`) and self-service profile edit
  (`PeguamProfilUpdateService`, extracted in CODE-05) write `_2`/`_3`/`_4`/`_5` (and `_6` for
  specialisation). **No code path writes v1** (`grep` for `ButiranPeguamPanel::create` /
  `new ButiranPeguamPanel` returns nothing).
- **v1 is read in exactly two places:** the `PeguamPanel::butiran()` `hasOne` relation and the
  `peguam-panel/_butiran.blade.php` detail partial.

So v1 cannot *diverge* from v2–6 through app activity — it is never written. It holds only
legacy rows that predate the v2–6 split.

## Decision

1. **v2–6 is the authoritative source of truth** for panel-lawyer profile data. All writes go
   to v2–6.
2. **v1 (`butiran_peguam_panel`) is read-only legacy display data.** Keep it for historical
   records that predate the normalized split; never write to it from application code.
3. **No sync layer is needed** (there is nothing to keep in sync — v1 has no writer).

## Consequences

- The `PeguamPanel::butiran()` relation + `_butiran.blade.php` partial remain as a legacy
  display fallback and are safe to leave.
- **Future cleanup (optional, out of scope here):** once every lawyer of record exists in
  v2–6, migrate the two v1 read sites onto v2–6 and drop the `butiran_peguam_panel` table.
  Tracked as a data-decision item, not a code defect.
