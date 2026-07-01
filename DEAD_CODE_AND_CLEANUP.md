# DEAD CODE & CLEANUP — iGuaman 2in1

Audit date: 2026-07-01. **Do not delete anything until usage is traced and confirmed.** Each item states confidence and the verification step required first. This is a legacy port with deliberate legacy-fidelity retention — much that *looks* dead is intentional. Items are conservative.

---

## 1. Dead / disconnected code (safe candidates after verification)

| Item | Location | Evidence | Confidence | Verify before removing |
|---|---|---|---|---|
| **OCR prefill spike** | `app/Http/Controllers/OcrPrefillController.php`, `app/Support/OcrPrefillService.php`, `config/ocr.php`, route + `resources/views/ocr/prefill.blade.php` | `config/ocr.php:17 enabled=false`; `OcrPrefillController::extract` returns 503; comment "SPIKE"; engine never wired | High (non-functional) | Confirm OCR is not on the near-term roadmap. If shelved >2 quarters, remove controller+service+config+route+view+`khidmat-nasihat/form.blade.php:446` fetch. |
| **Dead active-tab ternary** | `resources/views/layouts/peguam.blade.php:20` | `routeIs(...) ? '' : ''` — both branches empty; no effect | High | None — replace with real active-class logic or delete the ternary (`UX-10`). |
| **Unused `kes.view` permission** | `database/seeders/RolePermissionSeeder.php` (defines `kes.view`) vs `routes/web.php` (never references it) | Permission seeded but no route uses it | Medium | Rather than delete, **wire it** into the GET routes (`AUTH-05`). Only remove if per-resource gating is rejected. |
| **`inspire` console command** | `routes/console.php:7-9` | Laravel scaffold demo command | High | Cosmetic; remove if desired. |

---

## 2. Duplicate / overlapping logic (consolidate, don't blind-delete)

| Item | Location | Note | Action |
|---|---|---|---|
| `ButiranPeguamPanel` **v1** vs **v2-6** | `app/Models/ButiranPeguamPanel.php` (table `butiran_peguam_panel`) vs `ButiranPeguamPanel2..6` | v2-6 = legitimate normalized split (bio/quals/firm/bank/spec), **keep**. v1 overlaps v2-6 facts, read in only 1 place (`PeguamPanel::butiran()`) + `peguam-panel/_butiran.blade.php` | Confirm whether v1 is read-only historical data. If dead → deprecate + remove v1 model/view/table after data check (`CODE-03`). |
| `forms` report filter builder | `StatistikController`, `LaporanController`, `LaporanPenuhController`, `KpiController` | Same cawangan/kategori/date filter re-implemented 4× | Consolidate into `LaporanRegistry`/`FormsReportQuery` (`ARCH-03`), then delete the 3 duplicates. |
| `year()` helper | `StatistikPengantaraanController.php:89-94`, `StatistikSlaController.php:124-136` | Byte-identical | Extract to a trait; delete copies (`CODE-06`). |
| 23-branch list + `BULAN` | `StatistikSlaController`, `StatistikPemindahanController`, `PengantaraanMatrix`, vs `SlaMatrix::BRANCHES` | Constant duplication | Reuse one canonical source; delete the copies (`ARCH-04`). |

---

## 3. Unused / risky database columns (DO NOT DROP — flag only)

Government legacy schema. Column removal is high-risk (legacy reports, ETL, historical rows). **Flag, don't drop.**

| Column / table | Note | Action |
|---|---|---|
| `forms.nilai_sumbangan`, `kos_oyd`, `kos_pihak_lawan` | Wrong type (`int` for money), not unused | Migrate type, not drop (`DB-02`). |
| `butiran_peguam_panel_6.modifiedDate` | Wrong type (`string`) | Alter to `dateTime` (`DB-12`). |
| `mahkamah_sivil` / `mahkamah_syariah` | Duplicate identical tables | Merge into one with `jenis` enum only if a schema pass is green-lit; otherwise keep (`DB-08`). |
| `audit_trail.field_name/old_value/new_value` | Present but always NULL (unused) | Populate for sensitive updates rather than drop (`LOG-05`). |

---

## 4. Unused routes / endpoints

- **None confirmed dead.** Blade `route()` sweep is clean — 0 calls to undefined routes. All controllers referenced by `routes/web.php` exist. The OCR route (§1) is the only route backing a non-functional feature.
- **Over-exposed, not unused:** the list/report GET routes under `permission:system.view` (`AUTH-05`) are used but under-gated — fix authorization, don't remove.

---

## 5. Debugging / temporary code

- **Clean.** No `dd()`, `dump()`, `var_dump()`, `ray()`, `die()`, `console.log` debug residue, `eval`, `exec`, or `unserialize` in `app/`. No Telescope/Debugbar. No commented-out code blocks of note. No empty catch blocks.
- **Formatting debt (not dead code):** `./vendor/bin/pint --test` fails on 55 files — run `pint` to normalise (`CODE-08`).

---

## 6. Redundant dependencies

| Package | Note | Action |
|---|---|---|
| `laravel/pao` (`composer.json:19`, dev) | Low community footprint; verify it's intended (not a typo for `laravel/pail`, which is also present) | Confirm provenance; remove if unintended (`CFG-11`). |
| `laravel/pail`, `laravel/pint`, `nunomaduro/collision`, `fakerphp/faker`, `mockery` | Standard dev tooling | Keep. |
| Runtime deps (`barryvdh/laravel-dompdf`, `maatwebsite/excel`, `spatie/laravel-permission`, `laravel/tinker`) | All used | Keep. |

No abandoned runtime libraries detected. Run `composer audit` in CI to catch future advisories (`CFG-11`).

---

## 7. Obsolete configuration / artifacts

| Item | Note | Action |
|---|---|---|
| `.env.example` prod-unsafe defaults (`APP_DEBUG=true`, `MAIL=log`, `LOG=debug`) | Copied to prod on first deploy | Add a safe `.env.production` template; don't leave example as the prod source (`CFG-04`). |
| `.phpunit.result.cache` (repo root) | Test cache artifact | Add to `.gitignore` if tracked; harmless. |
| `config/ocr.php` | Backs the OCR spike | Remove with §1 if OCR dropped. |
| Superseded draft audit files (`AUD-xxx`) | Were present at audit start; replaced by the current cross-validated reports | Already superseded — no action. |

---

## 8. Unnecessary abstractions

- **Low.** The service layer (`app/Support`) is genuine, not speculative — every class is wired and most carry real transaction/lock logic. `LaporanRegistry` deliberately shares logic between controller and job to prevent drift (keep).
- **Minor:** `ChatbotController` inlines proxy logic instead of an adapter (convention drift, `ARCH-06`) — refactor, not remove.
- **Optional:** flat `app/Support` could be namespaced (`ARCH-08`); purely organizational.

---

## Removal safety checklist (apply per item)

1. `grep` the symbol/route/column across `app/`, `resources/`, `routes/`, `database/`, `config/`, `tests/`.
2. Check migrations + `legacy-domain.sql` for schema dependence and historical data.
3. Check whether legacy reports/exports read the column.
4. Confirm no scheduled command / job / event references it.
5. Remove behind a commit that is easy to revert; run the full test suite.
6. For DB columns: never drop in the same release that stops writing them — deprecate first, drop later after a data-retention review.
