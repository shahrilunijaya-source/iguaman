# Conventions

> Project rules Claude must follow. This is a System (full production Laravel app) — full discipline applies.

## Code
- Read a file before editing it. Grep for callers before changing a function.
- Match surrounding style -- naming, comments, idioms.
- **NEVER use Filament** (panel/admin/dependency). Caused every Hostinger deploy headache. Laravel auth = plain custom controller + Blade. No Breeze/Jetstream/Filament.

## Laravel Systems
- Copy `Aril/ProjectAI/Prototype/jpn/inpres-a/` as the golden template. See `Aril/MyPA/references/laravel-system-template.md`.
- Auth: custom `SystemAuthController` + `Auth::attempt` + Blade. Login page = split-screen design (`resources/views/system/login.blade.php`), restyle CSS only.
- Stack (always): PHP 8.3 · Laravel 13 · MySQL · Blade + vanilla/Alpine · Vite. MySQL is standard — SQLite only for throwaway prototypes. Create DB+user in hPanel (auto-prefix `u<account>_`), creds in `.env`.

## Workflow
- Small atomic commits with clear messages.
- Don't add dependencies without flagging the cost/tradeoff.

## Build Modes
- **"plan it"** -- full flow (office-hours -> autoplan -> reviews -> execute). Big bets, uncertain scope.
- **"quick plan"** -- one brainstorm + one plan + confirm, then build. Features, known domain.
- **"just build"** -- max 3 clarifying Qs, then execute. Clear scope, CRUD, sites.

## Deploy
- GitHub org `shahrilunijaya-source`, repo `sys-iguaman-2in1`, branch `main`, HTTPS + Windows Credential Manager.
- Push to deploy: `git add . && git commit -m "..." && git push`. Hostinger webhook auto-pulls.
- Laravel apps: 3 deploy artifacts present — root `.htaccess` guard, `deploy.sh`, committed `public/build` (Hostinger has no node). Full guide: `Aril/MyPA/references/laravel-system-template.md`. Webhook = pull+composer only; migrate via SSH port 65002.

## Project-Specific
- Local DB: `iguaman_2in1` (MySQL via Laragon, user `root`, no password).
- Demo login: `demo@example.com` / `password`. Remove before production.
- Domain logic not scaffolded — confirm iGuaman 2in1 scope before building features.
