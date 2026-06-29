# Decision Log

Append-only. When a meaningful decision is made, log it here.

Format: [YYYY-MM-DD] DECISION: ... | REASONING: ... | CONTEXT: ...

---

[2026-06-29] DECISION: Initialized Claude context folder for this project | REASONING: Give Claude persistent context, conventions, and memory across sessions | CONTEXT: Scaffolded via init-claude-folder skill

[2026-06-29] DECISION: Full Laravel 13 + MySQL bootstrap from inpres-a golden template | REASONING: System type — needs real app, not just context. Plain auth (no Filament) per standing rule | CONTEXT: composer create-project, MySQL db `iguaman_2in1`, auth overlay, split-screen login, deploy artifacts, committed public/build. Auth flow verified (guest redirect, login, dashboard).

[2026-06-29] DECISION: redirectGuestsTo(system.login) in bootstrap/app.php | REASONING: No default `login` route exists — auth middleware 500'd on guest access | CONTEXT: Auth routes named system.login, not login.
