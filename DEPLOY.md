# Deploy — iGuaman 2in1 (Hostinger, Laravel-in-public_html + MySQL)

Plain Laravel 13. No node on the shared host → `public/build` is committed. Webhook does
`git pull` + `composer install`; `deploy.sh` does the rest. Run security checklist in
[context/security.md](context/security.md) **before** go-live.

## 1. GitHub repo (one-time)

```bash
# from the project root (already a git repo, branch main)
gh repo create shahrilunijaya-source/iguaman --private --source=. --remote=origin
git push -u origin main
```

## 2. Hostinger hPanel (one-time)

1. **MySQL** → create database + user (auto-prefixed `u<acct>_iguaman_2in1`); note creds.
2. **Git** → connect repo `iguaman`, branch `main`, deploy path = domain `public_html`.
3. Enable the auto-deploy **webhook** (pull + composer install on push).

## 3. Production `.env` (on server, edit once)

`deploy.sh` copies `.env.example` → `.env` on first run. Then set:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # deploy.sh runs key:generate if empty
APP_URL=https://<domain>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=u<acct>_iguaman_2in1
DB_USERNAME=u<acct>_<user>
DB_PASSWORD=<hPanel db pw>

SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp        # set real SMTP for password-reset mail
MAIL_PASSWORD=<ROTATED email pw — do NOT reuse the leaked legacy one>
```

## 4. First deploy (SSH)

```bash
ssh -p 65002 <user>@<host>
cd ~/domains/<domain>/public_html
bash deploy.sh          # env/key, migrate, storage symlink (ln), caches, build skip
```

`deploy.sh` runs **migrate only** — no demo seed in prod.

## 5. Data migration (one-time, real data)

The unified + hardened data already lives in local `iguaman_2in1` (legacy imported,
passwords bcrypt, all accounts flagged `must_change_password`). Carry it over:

```bash
# local
mysqldump -u root iguaman_2in1 > iguaman_2in1.sql
# upload iguaman_2in1.sql to server, then on server:
mysql -u u<acct>_<user> -p u<acct>_iguaman_2in1 < iguaman_2in1.sql
```

(Do NOT run `legacy:import` on the server — it reads the old `sistemspk` DB, which is
local-only. The dump above already contains the migrated result.)

## 6. Post-deploy checks

- `https://<domain>/system/login` loads (built CSS present).
- Log in as a migrated user → forced to `/password/change` (flag works).
- Response headers include `X-Frame-Options: DENY`.
- `storage/` + `bootstrap/cache` writable (deploy.sh chmod 775).

## Re-deploys

Push to `main` → webhook pulls + composer installs → SSH `bash deploy.sh` (re-caches,
runs new migrations). If assets changed, rebuild locally + `git add -f public/build` + push.
