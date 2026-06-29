# iGuaman 2in1

Laravel + MySQL production system. Officer/user workspace scaffolded from the `inpres-a` golden template (plain Laravel auth — NO Filament).

> Domain not yet specified ("guaman" = litigation/legal). Fill `context/domain.md` once scope is known. This file documents the scaffold as built.

## Stack

| Layer | Choice |
|-------|--------|
| Language | PHP 8.3 |
| Framework | Laravel 13 |
| DB | MySQL 8.4 (Laragon local), db `iguaman_2in1`, user `root` |
| Views | Blade + vanilla JS |
| Assets | Vite 8 + Tailwind v4 |
| Auth | Plain `SystemAuthController` + `Auth::attempt` (no Breeze/Jetstream/Filament) |

## Commands

| Task | Command |
|------|---------|
| Dev server | `php artisan serve` |
| Assets (watch) | `npm run dev` |
| Build assets | `npm run build` |
| Migrate | `php artisan migrate` |
| Seed demo user | `php artisan db:seed` |
| Routes | `php artisan route:list` |

## Auth flow (built + verified)

- `GET /system/login` → `system.login` — split-screen login (`resources/views/system/login.blade.php`)
- `POST /system/login` → `system.login.attempt`
- `GET /system` → `system.utama` — dashboard stub (auth-protected)
- `POST /logout` → `system.logout`
- Guests on protected routes redirect to `system.login` (wired in `bootstrap/app.php` via `redirectGuestsTo`).
- Demo account: `demo@example.com` / `password` (seeded by `DemoUserSeeder`). Strip before production → MFA.

## Design

- Inherited `inpres-a` system surface: `resources/css/theme.css` (brand tokens — teal `#00B8A9` / pine `#003D3A`) + `resources/css/system.css` (login split-screen + workspace shell). Both registered as Vite inputs.
- **Rebrand tokens in `theme.css` COMPANY BRAND block** once iGuaman brand direction is set. Keep the split-screen login skeleton.
- Utilitarian internal app — no separate marketing design profile.

## Layout (key dirs)

| Path | What |
|------|------|
| `app/Http/Controllers/SystemAuthController.php` | login / attempt / logout |
| `app/Http/Controllers/SystemController.php` | authenticated dashboard |
| `resources/views/system/` | `login.blade.php`, `utama.blade.php` |
| `resources/css/` | `theme.css`, `system.css` (+ default `app.css`) |
| `database/seeders/DemoUserSeeder.php` | demo login account |
| `routes/web.php` | public + system auth routes |
| `.htaccess` · `deploy.sh` | Hostinger deploy artifacts (root) |

## Deploy

- GitHub: `shahrilunijaya-source/sys-iguaman-2in1`, branch `main`, HTTPS + Windows Credential Manager.
- **Wire Hostinger Git + webhook BEFORE first push.** Create MySQL DB + user in hPanel (auto-prefix `u<account>_`), put creds in prod `.env`.
- Push to deploy: `git add . && git commit -m "..." && git push` — webhook auto-pulls + `composer install`.
- Laravel-in-public_html: root `.htaccess` guard routes into `public/`; `public/build` is committed (Hostinger has no node); migrations run manually via SSH (port 65002, use `ln -s` for storage link — `artisan storage:link` fails).
- Full guide: `Aril/MyPA/references/laravel-system-template.md`.

## Conventions

- Read a file before editing. Grep callers before changing a function. Match surrounding style.
- **NEVER Filament / Breeze / Jetstream** — plain Laravel auth only.
- Small atomic commits. Flag any new dependency's cost.

## Build modes

- **"plan it"** = full flow (brainstorm → plan → reviews → execute). **"quick plan"** = one brainstorm + one plan, confirm, build. **"just build"** = ≤3 clarifying Qs then go.
