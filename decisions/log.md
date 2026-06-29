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
