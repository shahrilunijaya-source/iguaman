# Map 06 — 2in1 REKOD KES domain (case records / mediation / court / statistics)

Source: the NEW consolidated Laravel app `2in1` (Laravel 13, MySQL 8.4, Blade + vanilla JS, plain auth + spatie/laravel-permission). READ-ONLY audit of the REKOD KES domain *as actually built*. Reviewed: controllers, models, `routes/web.php`, FormRequests, Support services, Exports, Blade views, and `database/schema/legacy-domain.sql` (the verbatim legacy DDL imported by migration `2026_06_29_000001_import_legacy_domain_tables.php`).

Branch isolation: `App\Models\Scopes\CawanganScope` is a global scope on `Form` — staff with a `cawangan` see only their branch unless they hold `cawangan.view-all`; lawyers / no-branch / view-all see everything. All-branch management dashboards (SlaMatrix, PengantaraanMatrix, KesilapanMatrix) deliberately bypass the scope by querying `DB::table('forms')` raw, and are route-gated to HQ via `permission:statistik.view`.

---

## 1. Data spine

### `forms` (model `App\Models\Form`, table `forms`) — the case spine
- **98 columns** (94 imported verbatim + 4 added by `2026_06_29_000004_add_drifted_forms_columns.php`: `justifikasi_rujuk_pp`, `justifikasi_lain_rujuk_pp`, `status_rekod`, `tarikh_mohon_khidmat_pp`).
- `$guarded = ['id']`, `$timestamps = false` (legacy has `created_at` only, no `updated_at`). `booted()` adds `CawanganScope`.
- Casts: `tarikh_permohonan`, `tarikh_khidmat_nasihat`, `tarikh_penugasan`, `tarikh_penugasan_peguam_panel`, `tarikh_sidang`, `tarikh_selesai`, `tarikh_tutup_fail` → date; `tarikh_mohon_khidmat_pp`, `created_at` → datetime; `is_duplicate` → boolean.
- `diterima varchar(10) NOT NULL` (legacy) — `KesController::store` writes `''` to satisfy this.
- Relations: `laporanKes` (hasMany `LaporanKes` on `id_kes`), `sejarahPegawai`, `sejarahPeguamPanel`, `sejarahSidang`, `lampiran` (hasMany `UploadedFile` on `id_kes`).
- This one table holds the entire case lifecycle: applicant (Pemohon), permohonan/pendaftaran, keputusan, agihan/peguam, pengantaraan, mahkamah, penutupan & kos. Documented intent: "Decompose into Case + detail tables in a later phase" — **not done** (single wide table is the built state).

### Child / detail tables
- **`laporan_kes`** (model `LaporanKes`) — court case report, child of `forms` via `id_kes` (stored as `varchar(20)`, app casts via string compare). Cols: `pihak_pihak`, `no_fail`, `no_kes`, `nama_pegawai`, `tarikh_sebutan` (date), `fakta_ringkas`, `isu`, `ringkasan`, `status_kes`, `id_kes`.
- **`sejarah_sidang`** (model `SejarahSidang`) — hearing postponement log, child via `id_kes int`. Cols: `tarikh_sidang` (date, NOT NULL), `alasan_tangguh varchar(50)`, `dikemaskini_oleh varchar(50) NOT NULL`.
- **`uploaded_files`** (model `UploadedFile`) — case attachments (lampiran), linked via `id_kes` (added by `2026_06_29_000005`). Stored on private `local` disk under `lampiran/`.
- **`butiran_oyd`** (model `Oyd`) — beneficiary master. PK `(id, kp_oyd)`, UNIQUE `kp_oyd`. `*_oyd` suffixed columns; audit columns `createdBy_oyd/createdDate_oyd/modifiedBy_oyd/modifiedDate_oyd`.

### Reference masters
- **`ref_kes`** (model `RefKes`) — case-type master: `id_kes`, `jenis_kes`, `kategori_kes`, `deskripsi`, `aktif_kes`, `tarikh_kuatkuasa`. Joined into wide exports as `jenis_kes_text` via `forms.jenis_kes = ref_kes.id_kes`.
- **`mahkamah_sivil`** / **`mahkamah_syariah`** (models `MahkamahSivil`, `MahkamahSyariah`) — court registry: `nama_mahkamah`, `negeri_mahkamah`, `lokaliti_mahkamah`, `jenis_mahkamah`.
- **`ref_negeri`** (model `RefNegeri`), **`ref_lokasi_berguam`** (model `RefLokasiBerguam`) — bare models (`$guarded=['id']`, no relations/usage found in rekod-kes controllers; reference scaffolds).

---

## 2. Case lifecycle (peringkat 1–7) — as built

The legacy 7-stage flow is mapped onto `forms` columns + status strings. Stages are **not enforced as a formal state machine** — most transitions are free-text column writes via FormRequests; only peringkat 2 (keputusan) and 7 (tutup fail) are gated.

| Peringkat | Action | Controller / route | Status written | Notes |
|-----------|--------|--------------------|----------------|-------|
| 1. Permohonan | Intake / register | `KesController::store` (`POST /kes`, `kes.store`) | no status set on create (`status` nullable, defaults blank → shown as "baru"); `tarikh_daftar`, `didaftarkan_oleh`, `diterima=''` | Auto-generates `no_fail` via `NoFailGenerator` if blank. AJAX duplicate-IC guard `kes.semak-nokp` (`checkNokp`). |
| 2a. Keputusan — Lulus | Approve | `KeputusanController::lulus` (`POST /kes/{kes}/lulus`) | `keputusan='Diluluskan'`, `diterima='Ya'`, **`status='Diterima'`**; sets `tarikh_perakuan`, `tarikh_pemakluman`, `tarikh_pengarahKemaskini` | Gated `kes.keputusan` (Pengarah/KP) via `abort_unless($user->can('kes.keputusan'))`. Audited `Audit::APPROVE`. |
| 2b. Keputusan — Tolak | Reject | `KeputusanController::tolak` (`POST /kes/{kes}/tolak`) | `keputusan='Ditolak'`, `diterima='Tidak'`, **`status='Ditolak'`**, `reason`, `tarikh_pemakluman` | Same gate. Audited `Audit::REJECT`. **Terminal-ish** (no further enforced flow). |
| 3. Agihan | Assign panel lawyer | `AgihanController::store` (`POST /kes/{kes}/agih`, `agihan.store`, perm `agihan.manage`) | `nama_pegawai_yang_dapat_kes`, `agih_kepada`, `tarikh_penugasan_peguam_panel`, **`status_agihan='Ditawarkan'`** (legacy string) | Reassignment logs prior lawyer to `sejarah_peguam_panel`. Emails lawyer (`KesDitawarkanMail`, mail driver `log` in dev). **Parallel 3-tier spine** exists (`AgihanSpineController` + `StatusAgihan` numeric machine) — see §5 drift. |
| 4a. Pengantaraan | Mediation section edit | `PengantaraanController::update` (`PUT /kes/{kes}/pengantaraan`) | free-text writes to `status_pengantaraan`, `pengantaraan_kategori_kes`, `cara_selesai`, `setuju_pengantara`, dates | Validated by `PengantaraanRequest`. No status-string enforcement — `status_pengantaraan` is `'Ya'`/`'Tidak'` by convention (set by typing). |
| 4a′. Tangguh Sidang | Hearing postpone | `PengantaraanController::tangguhSidang` (`POST /kes/{kes}/sidang`) | inserts `sejarah_sidang` row; sets `forms.tarikh_sidang`, **`status_sidang='Tangguh'`** | Only hard-coded status in pengantaraan. |
| 4b. Mahkamah | Court section edit | `MahkamahController::update` (`PUT /kes/{kes}/mahkamah`) | free-text writes to `nama_mahkamah`, `no_mahkamah`, `tarikh_pemfailan_kes`, `tarikh_perintah`, `tarikh_serahan_perintah`, `kos*`, etc. | Validated by `MahkamahRequest`. |
| 4b′. Laporan Kes | Add/del court report | `MahkamahController::storeLaporan` / `destroyLaporan` (`POST`/`DELETE /kes/{kes}/laporan`) | `laporan_kes` child rows | No status change. |
| 7. Tutup Fail | Official closure | `KeputusanController::tutupFail` (`POST /kes/{kes}/tutup-fail`, `kes.tutupfail`) | `tarikh_tutup_fail`, **`status='Fail Tutup'`**, `sebab_tutup_fail`, `kos` | Gated `kes.keputusan`. Audited `Audit::UPDATE`. Closed files surfaced via `kes.tutup` (`GET /fail-tutup`, filters `whereNotNull('tarikh_tutup_fail')`). |

**Completion condition:** a case is "closed" purely by `tarikh_tutup_fail IS NOT NULL` + `status='Fail Tutup'`. There is no peringkat-5/6 enforced sub-state in rekod-kes — "Selesai" / "Pemfailan Selesai" / "Belum Difailkan" are **computed** (not stored) in `WideExport::statusPemfailan()` and `LaporanPenuhController::statusFilter()` from `status` + `tarikh_selesai` + `tarikh_pemfailan_kes`.

**Special closure — Kesilapan Menjana Nombor Fail:** files closed with `status='Fail Tutup'` AND `sebab_tutup_fail='Kesilapan Menjana Nombor Fail'` are a universal *exclusion* in every statistik/laporan query, and the *inverse* subject of the dedicated Kesilapan report (§4).

### Status values observed in code (`forms.status`)
`Diterima` (lulus), `Ditolak` (tolak), `Fail Tutup` (tutup) — written by controllers. Display fallback "baru" when blank. `KesController::index` builds its status filter list from `DISTINCT status` (data-driven, not an enum). No DB-level enum/check constraint.

### Hanging / non-terminal states (built gaps)
- **Approved-but-stuck:** after `status='Diterima'` there is no enforced progression to agihan/pengantaraan/mahkamah/tutup — every onward step is an optional free-text edit. A case can sit at `Diterima` indefinitely.
- **`status_agihan='Ditawarkan'`** has no enforced timeout in `AgihanController`; the `LebihMasaService` / `StatusAgihan::LEBIH_MASA ('7')` auto-reassign logic belongs to the parallel spine, not this simple path.
- **Pengantaraan `status_pengantaraan`** is convention-only; a typo (`'ya'` vs `'Ya'`) silently drops a case from `status_pengantaraan='Ya'` statistik gates.

---

## 3. CRUD + per-case feature controllers

| Controller | Routes (name) | Purpose | Status |
|------------|--------------|---------|--------|
| `KesController` | `kes.index`, `kes.tutup`, `kes.create`, `kes.semak-nokp`, `kes.store`, `kes.edit`, `kes.update`, `kes.show` | Case list/filter/search (cawangan/status/kategori/q, paginate 20), closed-files list, permohonan create/edit, AJAX dup-IC guard, detail page | built |
| `KeputusanController` | `kes.lulus`, `kes.tolak`, `kes.tutupfail` | Peringkat 2 + 7 gated decisions (`kes.keputusan`) | built |
| `PengantaraanController` | `pengantaraan.edit/update`, `sidang.tangguh` | Mediation section + hearing reschedule (`sejarah_sidang`) | built |
| `MahkamahController` | `mahkamah.edit/update`, `laporan.store/destroy` | Court section + `laporan_kes` child CRUD | built |
| `LampiranController` | `lampiran.store/download/destroy` | Case attachments on private `local` disk, auth-streamed; mimes pdf/jpg/png/doc/xls ≤10MB; audited | built |
| `CetakanController` | `cetak.ringkasan`, `cetak.penugasan`, `cetak.laporan` | Per-case PDFs via dompdf (inline stream). Penugasan blocks if `nama_pegawai_yang_dapat_kes` blank | built |
| `OydController` | `oyd.index/create/store/edit/update/show` | OYD beneficiary registry (`butiran_oyd`), unique `kp_oyd`, audited | built |
| `RefKesController` | `ref-kes.*` (perm `selenggara.ref_kes`) | `ref_kes` case-type master CRUD, audited | built |
| `MahkamahRefController` | `mahkamah-ref.*` `{jenis: sivil\|syariah}` (perm `selenggara.mahkamah_ref`) | One controller serving both court reference tables, audited | built |
| `AgihanController` | `agihan.form/store`, `agihan.beban` (perm `agihan.manage`) | Simple assign + workload count. (Adjacent to rekod-kes; parallel to the spine.) | built (drifts — §5) |

---

## 4. Statistik + Laporan reports (with exports)

### `LaporanController` (`laporan.*`, perm `system.view`) — 6 narrow reports
Registry of 6 report keys, 2 groups. Each: table (`show`, paginate 30) + CSV (`csv`) + PDF (`pdf`, dompdf landscape, ≤2000 rows). Filters: `cawangan`, `dari`/`hingga` (date range on `tarikh_permohonan`). All respect `CawanganScope`.

| Key | Label / group | Row filter |
|-----|---------------|-----------|
| `permohonan` | Laporan Permohonan / Litigasi | none |
| `pendaftaran-fail` | Pendaftaran Fail / Litigasi | `no_fail` not null/blank |
| `status-fail` | Status Fail / Litigasi | none |
| `penugasan-pengantaraan` | Penugasan Pengantaraan / Pengantaraan | `status_pengantaraan` not null/blank |
| `pencapaian-pengantaraan` | Pencapaian Pengantaraan / Pengantaraan | `cara_selesai` not null/blank |
| `tidak-dirujuk` | Tidak Dirujuk Pengantaraan / Pengantaraan | `status_pengantaraan` null/blank |

CSV: `fputcsv` of label columns. PDF: `laporan.pdf` view. Date cells formatted d/m/Y.

### `LaporanPenuhController` (`laporan.penuh`, perm `laporan.view`) — wide-column CSV (EPIC F)
Legacy `export_*.php` parity. 5 types (`permohonan`, `pendaftaran-fail`, `status-fail`, `penugasan-pengantaraan`, `tidak-dirujuk`). Column lists/cell formatters live in `App\Support\WideExport` (pure, unit-testable). Features: UTF-8 BOM, title+filter "envelope" rows, `BIL.` index, derived BULAN/TAHUN columns, `ref_kes` join for JENIS KES, NoKP emitted as Excel text formula `="012345..."`, ALASAN-DITOLAK reason-code decode (1–6), computed STATUS PEMFAILAN. **Universal exclusion:** files closed for `'Kesilapan Menjana Nombor Fail'`. Branch-gated via `CawanganScope`, plus `orderByRaw` Putrajaya-first.
- **Stub/degraded columns** (flagged in code): `alasan_tidak_setuju_pengantara`, `alasan_gagal_pengantara`, `alasan_tangguh_sidang`, `alasan_tidak_rujuk_pengantaraan`, `kategori_kes2` and several pengantaraan/perjanjian columns degrade to `-Tiada Maklumat-` because the pengantaraan workflow that would populate them is not fully ported. `penugasanPengantaraan` reuses `tarikh_persetujuan` for "TARIKH PERJANJIAN PENYELESAIAN" (noted as a suspected legacy mismap, ported verbatim).

### `StatistikController` (`statistik.index/excel/pdf`, perm `system.view`) — dashboard
8 KPI tiles (jumlah/aktif/tutup/pengantaraan/diagih/belum_agih/oyd/peguam) + 7 group-count breakdowns (`byCawangan`, `byKategori`, `byJenis`, `byStatus`, `byKeputusan`, `byCaraSelesai`, top 12 each) + `byBulan` (last 12 months on `tarikh_permohonan`). Excel via `KesExport` (maatwebsite, 7-col case list). PDF via `statistik.pdf`. Filters cawangan/status/kategori/q. Respects `CawanganScope`.

### `StatistikSlaController` (`statistik-sla.*`, perm `statistik.view`) — 5 per-branch SLA matrices (EPIC F)
Backed by `App\Support\SlaMatrix`: fixed **23-branch** list × 4 kategori (Sivil/Syariah/Jenayah/Pendamping Guaman), each shown CAPAI / TIDAK CAPAI / PERATUS%, JUMLAH footer. Bypasses `CawanganScope` (raw `DB::table`). 5 dashboards keyed off `DATEDIFF(end,start) <= target`:

| Key | Target | start → end | extra filter |
|-----|--------|-------------|--------------|
| `perakuan` | 40 | `tarikh_permohonan → tarikh_pemakluman` | `kelulusan='Tidak'`, `sumbangan='Tiada'` |
| `fail-tiada` | 60 | `tarikh_perakuan → tarikh_pemfailan_kes` | `status_pengantaraan='Tidak'` |
| `fail-terlibat` | 120 | `tarikh_perakuan → tarikh_pemfailan_kes` | `status_pengantaraan='Ya'` |
| `serahan` | 7 | `tarikh_perintah_bersih → tarikh_serahan_perintah` | — |
| `khidmat` | 60 | `tarikh_persetujuan_pengantaraan → tarikh_persetujuan` | — |

Each matrix: `show` (HTML), `pdf` (dompdf landscape), and **`senarai`** breach-list CSV drill-down (`App\Support\SlaListExport`, the TIDAK CAPAI rows, `DATEDIFF > target`, "TEMPOH MELEBIHI N HARI" column, court vs mediation column layout, branch-gated via `CawanganScope`). Optional year/month + cawangan/kategori drill-down filters. **Two legacy bugs corrected** (documented in `SlaMatrix`): the `* 7.0` percentage typo and the missing/typo Putrajaya branch; one canonical 23-branch list used for all five. **Note:** `khidmat` end col `tarikh_persetujuan` looks suspect vs `tarikh_selesai` used by the KPI equivalent — likely a port discrepancy.

### `StatistikPengantaraanController` (`statistik-pengantaraan.*`, perm `statistik.view`) — 3 assignment matrices (P1)
Backed by `App\Support\PengantaraanMatrix` (23-branch axis, bypasses `CawanganScope`). Index + 3 views + PDF (`{jenis: kategori\|bulanan\|pencapaian}`):
- `kategori()` — branch × [Sivil, Syariah, Jumlah] assignment counts (`status_pengantaraan='Ya'` + `pengantaraan_kategori_kes` set + hygiene gate, `tarikh_perakuan` year).
- `bulanan()` — branch × 12 months + Jumlah; optional sivil/syariah narrow.
- `pencapaian()` — branch × 4-stage funnel (perakuan→penugasan→rujuk_minta→selesai) with F1/F2/F3 consecutive-stage %; broader hygiene-only gate.
- **Two legacy inconsistencies corrected** (documented): bulanan kategori filter targets `pengantaraan_kategori_kes` (not `kategori_kes`); JUMLAH = sum of 12 months. F2 numerator uses on-screen `setuju_pengantara='Ya'` (cetakan PDF's `status_sidang='Selesai'` predicate **not ported**).

### `KesilapanController` (`statistik-kesilapan.*`, perm `statistik.view`) — file-number error report (P1)
Backed by `App\Support\KesilapanMatrix`. Inverse of EPIC F's universal exclusion: counts `status='Fail Tutup'` + `sebab_tutup_fail='Kesilapan Menjana Nombor Fail'` files, branch × `MONTH(tarikh_perakuan)` (23-branch, bypasses scope) + JUMLAH/footer/grand. Wide CSV via `WideExport::kesilapanColumns()` (36 cols + BIL), UTF-8 BOM, envelope, `ref_kes` join, `CawanganScope`-gated rows + extra `alasan_kesilapan_no_fail` column.

### `KpiController` (`kpi.index`, perm `system.view`) — yearly KPI dashboard
5 KPI definitions computed via `DATEDIFF` over `forms` date pairs, per kategori_kes × month, met/missed + achieved %:
- `perakuan` (40d, `tarikh_permohonan→tarikh_pemakluman`, excl. `keputusan_menteri`),
- `fail_tanpa` (60d, no mediation), `fail_dengan` (120d, with mediation),
- `serahan` (7d, `tarikh_perintah_bersih→tarikh_serahan_perintah`),
- `khidmat` (60d, `tarikh_persetujuan_pengantaraan→tarikh_selesai`).

View only (no export). Column names come from trusted `$def` arrays (SQL-injection-safe).

---

## 5. Drift / risks / open issues found

1. **Two parallel agihan paths.** `AgihanController` writes legacy *string* `status_agihan='Ditawarkan'`; `AgihanSpineController` + `App\Support\StatusAgihan` implement the canonical *numeric* state machine (0→8→10→13→1→2, withdrawal, lebih-masa). `StatusAgihan::LEGACY_STRING_MAP` exists precisely to reconcile the two — but `AgihanController::store` still writes the string, so the data column is mixed (numeric codes + `'Ditawarkan'`/`'Diterima'`). The codebase explicitly flags the simple controller as the diverged path. Bucket queries must use `StatusAgihan::bucketValues()` to catch both.
2. **No formal case state machine.** Peringkat 1/3/4 transitions are free-text FormRequest writes; only 2 + 7 are gated. `forms.status` has no enum/check; allowed values are convention.
3. **Computed (not stored) sub-statuses.** "Selesai / Pemfailan Selesai / Belum Difailkan" exist only in `WideExport::statusPemfailan` + `LaporanPenuhController::statusFilter`. The status-fail report and the on-screen status can disagree.
4. **Stub export columns.** Several pengantaraan reason columns degrade to `-Tiada Maklumat-` (workflow not ported) — `LaporanPenuh` penugasan/tidak-dirujuk, and the verbatim `tarikh_persetujuan` re-use mismap.
5. **SLA `khidmat` end-date discrepancy.** `SlaMatrix` uses `tarikh_persetujuan` as the end column; `KpiController` uses `tarikh_selesai` for the equivalent 60-day mediation KPI. Same business rule, two different end columns — a port inconsistency to reconcile.
6. **`laporan_kes.id_kes` is `varchar(20)`** while `forms.id` is int — joins/relations rely on string comparison (`destroyLaporan` casts both to string).
7. **Decompose-later TODO unfulfilled.** `Form` docblock states the intent to split the 98-col table into Case + detail tables; not done.
8. **`ref_negeri` / `ref_lokasi_berguam`** models exist but are unused by rekod-kes controllers (reference scaffolds, no CRUD surfaced in this domain).

---

## 6. Permissions / gating summary (rekod-kes)

| Surface | Gate |
|---------|------|
| Kes CRUD, pengantaraan, mahkamah, lampiran, cetakan, OYD, KPI, Laporan (narrow), Statistik (dashboard) | `permission:system.view` (group middleware) |
| Lulus / Tolak / Tutup Fail | in-controller `abort_unless($user->can('kes.keputusan'))` |
| Laporan Penuh (wide CSV) | `permission:laporan.view` |
| Statistik SLA / Pengantaraan / Kesilapan | `permission:statistik.view` |
| Agihan (simple) | `permission:agihan.manage` |
| Agihan spine actions | per-role `permission:agihan.ppuu` / `.pengarah` / `.kp` |
| ref_kes / mahkamah_ref maintenance | `permission:selenggara.ref_kes` / `selenggara.mahkamah_ref` |
| Branch row isolation (all `Form` Eloquent queries) | `CawanganScope` (auto) unless `cawangan.view-all` |
