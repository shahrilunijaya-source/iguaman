# Decision Log

Append-only. When a meaningful decision is made, log it here.

Format: [YYYY-MM-DD] DECISION: ... | REASONING: ... | CONTEXT: ...

---

[2026-06-29] DECISION: Initialized Claude context folder for this project | REASONING: Give Claude persistent context, conventions, and memory across sessions | CONTEXT: Scaffolded via init-claude-folder skill

[2026-06-29] DECISION: Full Laravel 13 + MySQL bootstrap from inpres-a golden template | REASONING: System type — needs real app, not just context. Plain auth (no Filament) per standing rule | CONTEXT: composer create-project, MySQL db `iguaman_2in1`, auth overlay, split-screen login, deploy artifacts, committed public/build. Auth flow verified (guest redirect, login, dashboard).

[2026-06-29] DECISION: redirectGuestsTo(system.login) in bootstrap/app.php | REASONING: No default `login` route exists — auth middleware 500'd on guest access | CONTEXT: Auth routes named system.login, not login.

[2026-06-29] DECISION: 2in1 = unify sistem-peguam-panel + sistem-rekod-kes into one Laravel app | REASONING: Both legacy raw-PHP apps already share ONE DB (sistemspk) with overlapping tables — merge is natural, not a bolt-on | CONTEXT: Mapped both via Explore agents. Plan in context/2in1-merge-plan.md. Approach: schema-first migration → unified auth/RBAC → port rekod-kes domain → port peguam-panel domain → PDF/Excel → data ETL.

[2026-06-29] DECISION: Plain Laravel + Blade, reject Filament/Nova | REASONING: Both analysis agents suggested Filament; overridden by standing Shahril rule (Filament = Hostinger deploy pain) | CONTEXT: Scaffold already uses plain SystemAuthController + Blade.

[2026-06-29] DECISION: Phase 1 schema-first complete — preserve legacy table/column names, add Laravel-native unified users | REASONING: Brownfield + migrate-existing-data; verbatim names = 1:1 ETL + 1:1 later query porting. Only auth rebuilt (security) | CONTEXT: 20 domain tables imported from sistemspk via baseline SQL migration; 3 user tables unified into `users` (966 rows) with bcrypt; 21 Eloquent models; FKs + peguam_panel PK added. ETL = `php artisan legacy:import`. Verified: relationships work, bcrypt auth round-trips, emails unique.

[2026-06-29] DECISION: Confirmed staff role map from log_masuk.php | REASONING: peranan 1→admin, 2→pengarah, 0→pegawai (per legacy login redirect) | CONTEXT: Result admin=1, pengarah=26, pegawai=237, peguam=702. Lawyers (panel_2/_3) all → peguam.

[2026-06-29] DECISION: Phase 4a peguam-panel domain (agihan + lawyer area) complete | REASONING: agihan is the connective tissue of 2in1 — staff assigns lawyers to rekod-kes cases | CONTEXT: AgihanController (assign/reassign sets forms.nama_pegawai_yang_dapat_kes/agih_kepada/status_agihan, reassign logs sejarah_peguam_panel) + beban tugas workload. PeguamController kesSaya + profil; layouts/peguam.blade. NOTE: sejarah_peguam_panel.status_agihan is varchar(2) — use short code 'S' not 'semula' (strict mode rejects). Phase4Test 7 tests; full suite 23 pass / 66 assertions.

[2026-06-29] DECISION: Phase 3d statistik + exports complete | REASONING: reporting layer + reusable export pattern (legacy had ~35 statistik + ~35 cetakan files) | CONTEXT: Installed barryvdh/laravel-dompdf + maatwebsite/excel (both ^3.1, Laravel 13 OK). StatistikController aggregates (by cawangan/kategori/status/bulan + KPIs) shared by dashboard + PDF; KesExport (FromQuery/WithMapping) for xlsx. CSS-bar dashboard, no chart lib. Routes statistik.index/excel/pdf. Full suite 16 pass / 50 assertions.

[2026-06-29] DECISION: Phase 3c pengantaraan + mahkamah complete | REASONING: lifecycle actions on a case + child tables | CONTEXT: PengantaraanController (edit/update + tangguhSidang→sejarah_sidang + moves forms.tarikh_sidang), MahkamahController (edit/update + laporan_kes store/destroy). Nested routes /kes/{kes}/{pengantaraan|mahkamah|sidang|laporan}. Action buttons on case detail. NOTE: sejarah_sidang FK is restrictOnDelete → child rows must be deleted before parent forms. Phase3cTest 5 tests pass (total suite 10 pass / 38 assertions).

[2026-06-29] DECISION: Phase 3b permohonan CRUD complete | REASONING: intake/edit writes to forms; scoped editable form to Pemohon+Permohonan+Keputusan+Penutupan (pengantaraan/mahkamah deferred to 3c) | CONTEXT: PermohonanRequest validation, KesController create/store/edit/update, shared kes.form. store sets created_at + didaftarkan_oleh + diterima(NOT NULL). Routes ordered (create before {kes}, whereNumber). Feature tests (tests/Feature/PermohonanTest.php) run vs real iguaman_2in1 (phpunit sqlite can't run MySQL baseline migration) with self-cleanup — 5 passed.

[2026-06-29] DECISION: Phase 3a Kes (Case) backbone complete | REASONING: forms is the spine for all rekod-kes sub-domains; build list+detail first, everything hangs off it | CONTEXT: KesController (filter cawangan/kategori/status + search nama/nokp/no_fail + paginate 20), kes.index (tap-table) + kes.show (94 fields grouped into 7 sections + sejarah_* history rail + laporan_kes). Extracted layouts/staff.blade (topbar+sidebar) — utama refit to it. Verified list/filter/detail render over real data.

[2026-06-29] DECISION: Phase 2 auth + RBAC complete | REASONING: One login over unified users; role-gate two areas (staff vs peguam) | CONTEXT: EnsureRole middleware (alias 'role'), cross-area access redirects to user's own homeRoute (not 403). Login active-only (is_active in Auth::attempt), sets last_login_at. Staff area role:admin,pengarah,koordinator,pegawai → /system; lawyer area role:peguam → /peguam. Password reset wired (Password broker, log mail driver in dev). Removed demo-account prefill/modal from login. All flows verified via curl (login, landing, cross-area gating, guest redirect, forgot page).
