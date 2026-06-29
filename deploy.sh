#!/usr/bin/env bash
# Server-side deploy (Hostinger, Laravel-in-public_html + MySQL).
# Run via SSH after a git pull / webhook deploy:
#   ssh -p 65002 <user>@<host>
#   cd ~/domains/<domain>/public_html && bash deploy.sh
#
# Webhook auto-runs `git pull` + `composer install` only. This script does the rest:
# env/key, MySQL migrate+seed, storage symlink, caches, and (if node present) front-end build.
# MySQL DB + user must be created in hPanel first; creds go in .env.
set -euo pipefail

echo "==> deploy starting"

# 1. .env — create from example on first run (then edit prod DB creds + values once).
if [ ! -f .env ]; then
  cp .env.example .env
  echo "==> .env created from .env.example — EDIT prod DB creds + values then re-run"
fi

# 2. APP_KEY — generate if missing.
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# 3. Composer deps (idempotent; webhook may have done this already).
if [ ! -d vendor ]; then
  composer install --no-dev --optimize-autoloader
fi

# 4. Migrate + seed (MySQL DB must exist + creds set in .env).
php artisan migrate --force
php artisan db:seed --force

# 5. storage symlink (Hostinger php exec() disabled → artisan storage:link fails; use ln).
if [ ! -e public/storage ]; then
  ln -s "$(pwd)/storage/app/public" public/storage || true
fi

# 6. Permissions.
chmod -R 775 storage bootstrap/cache || true

# 7. Caches.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Front-end build (public/build is committed for Hostinger — no node on shared host).
#    Skip if node absent; fallback = build locally + 'git add -f public/build' + push.
if command -v npm >/dev/null 2>&1; then
  npm install --no-audit --no-fund
  npm run build
else
  echo "==> npm not found — build public/build locally and 'git add -f public/build', then push"
fi

echo "==> deploy done"
