# iGuaman 2in1

Two legacy raw-PHP systems — **sistem-peguam-panel** (lawyer panel) and **sistem-rekod-kes**
(case records) — unified into one Laravel 13 app over the shared `sistemspk` schema.
Malaysian legal aid domain (JBG / Bantuan Guaman).

## Stack

PHP 8.3 · Laravel 13 · MySQL 8 · Blade + vanilla JS · Vite + Tailwind 4 · plain Laravel auth (no Filament).

## Local setup

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_DATABASE=iguaman_2in1 (+ creds) in .env, create the DB
php artisan migrate
php artisan legacy:import --source=sistemspk --fresh   # one-time: import + unify legacy data
npm install && npm run build
php artisan serve
```

## Commands

| Task | Command |
|------|---------|
| Dev server | `php artisan serve` |
| Assets watch / build | `npm run dev` / `npm run build` |
| Migrate | `php artisan migrate` |
| Import legacy data | `php artisan legacy:import --source=sistemspk --fresh` |
| Tests | `php artisan test` |

## Structure

- **Staff area** (`/system`, roles admin/pengarah/koordinator/pegawai): dashboard, Senarai Kes,
  permohonan CRUD, pengantaraan, kes mahkamah, statistik + Excel/PDF, agihan peguam, beban tugas,
  permohonan peguam approval.
- **Lawyer area** (`/peguam`, role peguam): dashboard, Kes Saya, Profil.
- One login over a unified `users` table; `EnsureRole` gates areas; `ForcePasswordChange` pins
  migrated accounts to a password reset.

## Docs

- `context/2in1-merge-plan.md` — merge analysis + phased plan
- `context/schema-design.md` — unified data model (23 legacy tables → preserved + unified users)
- `context/domain.md` — domain glossary
- `context/security.md` — security posture + pre-prod checklist
- `decisions/log.md` — decision log
- `DEPLOY.md` — Hostinger deploy

## Tests

`php artisan test` — feature tests run against the live `iguaman_2in1` MySQL DB (the MySQL-specific
legacy baseline migration can't run on sqlite) and self-clean their rows.

## Security

Legacy passwords were plaintext → rehashed + every migrated account flagged `must_change_password`.
**Before go-live:** rotate the email password leaked in legacy `sistem-peguam-panel/config.php`.
See `context/security.md`.
