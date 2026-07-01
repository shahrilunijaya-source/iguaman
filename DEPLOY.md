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

## Scheduled tasks & queue (REQUIRED — one-time hPanel setup)

Background business logic depends on the Laravel scheduler. Hostinger will **not** run it
automatically. Add this cron entry once in hPanel (every minute):

```
* * * * * cd ~/domains/<domain>/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Without it these silently never run:
- `agihan:lebih-masa` — re-assign panel-lawyer offers unanswered > 7 days (daily 07:00)
- `grab:tamat-luput` — expire unclaimed Khidmat Nasihat grabs > 7 days (daily 07:15)
- `lampiran:bersih-retensi` — 7-year attachment retention report (monthly, day 1, 02:00)

**Queue / bulk exports.** Shared hosting has no persistent queue worker. Bulk report exports
run **synchronously** (`ExportLaporanJob::dispatchSync`), so `QUEUE_CONNECTION` is irrelevant for
them and no `queue:work` process is needed. If a worker is ever added, the job already declares
`$tries` + a `failed()` handler. Verify an export completes end-to-end after deploy.

**Verify after deploy:**
```
php artisan schedule:list      # 3 commands listed
# generate a bulk export from the UI, then download it — file must appear
```

## Production `.env` values (safe defaults — set on the server)

The webhook copies `.env.example` (dev defaults) on first run. Before `deploy.sh` runs
`config:cache`, edit the server `.env` to these production values (deploy.sh now aborts if
`APP_DEBUG=true`):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iguaman.myappsonline.net

SESSION_SECURE_COOKIE=true      # cookie only over HTTPS (add this line; absent in example)

LOG_STACK=daily                 # rotate logs (single = unbounded file on shared host)
LOG_LEVEL=warning               # not debug — avoids leaking internals + disk bloat

MAIL_MAILER=smtp                # log = mail never sent; set real SMTP creds
MAIL_HOST=...  MAIL_PORT=...  MAIL_USERNAME=...  MAIL_PASSWORD=...  MAIL_ENCRYPTION=tls

# Rotate the JBG bot creds (do not reuse the dev values):
BOT_API_URL=https://<space>.hf.space
BOT_API_USER=...  BOT_API_PASS=...
```

DB creds come from hPanel (auto-prefixed `u<account>_`). `QUEUE_CONNECTION`/`CACHE_STORE`
stay `database` (correct for shared hosting; bulk exports run synchronously).
