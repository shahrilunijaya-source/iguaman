# Batch 7 RBAC — Production Deploy Runbook

> Webhook-safe rollout of the Spatie RBAC refactor (batch 7) to Hostinger.
> Branch `batch-7-rbac` → `main`. Repo `shahrilunijaya-source/sys-iguaman-2in1`.

## What this release changes

- Adds `spatie/laravel-permission` (^7) as the authoritative authorization layer.
- `RolePermissionSeeder` seeds **8 roles + 33 permissions** + the access matrix.
  (Original RBAC commit shipped 32; EPIC G cuti CRUD added a 33rd, `selenggara.cuti`.)
- `admin` is super-admin via `Gate::before` — passes every `can()` check; not enumerated per-permission.
- `rbac:backfill-roles` assigns Spatie roles to existing users from the legacy `users.role` column
  (unknown legacy values fall back to `pegawai`, or `peguam` for lawyer accounts).
- Routes/menus now gate on Spatie permissions (`@can`, `can:` middleware) instead of legacy `role:` / `EnsureRole`.
- `users.role` is **retained and dual-written** (display mirror + query + rollback fallback). Dropping it is a later cleanup batch.

---

## Pre-deploy (local / GitHub)

1. Confirm `composer.json` AND `composer.lock` are committed **together**, both carrying
   `spatie/laravel-permission ^7`. Hostinger runs `composer install` (lockfile-driven) — a stale
   lock means the package never installs on the host.
2. Confirm `public/build` is committed (Hostinger has no node).
3. Run the full test suite locally — must be green (`php artisan test`).
4. Merge `batch-7-rbac` → `main`. The push to `main` is what triggers the Hostinger webhook.

---

## The webhook reality + risk (READ BEFORE PUSHING)

The Hostinger Git webhook auto-pulls **and runs `composer install`** the moment the push lands.
This means the new permission-gated code and the Spatie package go **live immediately** — but the
Spatie DB tables do not exist yet, no roles/permissions are seeded, and no existing user has a Spatie
role assigned. The migrate/seed/backfill steps are **manual via SSH** and happen *after* the pull.

> **Lockout window:** between webhook-pull and the manual seed/backfill, every gated route fails the
> permission check. Even `admin` can be locked out until roles exist (Gate::before still needs the
> admin role assigned). **Mitigation: take a maintenance window** — `php artisan down` before/as the
> push lands, finish the SSH sequence, then `php artisan up`.

---

## Deploy sequence (SSH — Hostinger port 65002)

Open the maintenance window first, then run in order:

```bash
# 1. Maintenance window — do this BEFORE or right as the push lands.
php artisan down

# 2. Webhook normally already did git pull + composer install.
#    If it did not (check `git log -1`, check vendor/spatie/laravel-permission exists), run manually:
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Create the 5 Spatie tables.
php artisan migrate --force

# 4. Seed 8 roles + 33 permissions + the access matrix (idempotent — safe to re-run).
php artisan db:seed --class=RolePermissionSeeder --force

# 5. Reset the permission cache (prod CACHE_STORE=database → cache lives in the DB cache table).
php artisan permission:cache-reset

# 6. Assign Spatie roles to all existing users from the legacy users.role column.
php artisan rbac:backfill-roles

# 7. Reset the permission cache again (backfill mutated role assignments).
php artisan permission:cache-reset

# 8. Clear stale config/route/view caches.
php artisan config:clear
php artisan route:clear
php artisan view:clear
#    Only rebuild caches if this deploy uses cached config/routes:
#    php artisan config:cache && php artisan route:cache
#    NOTE: prod CACHE_STORE=database — the permission cache is in the DB cache table,
#    so `permission:cache-reset` (steps 5 & 7) is what actually clears RBAC caching,
#    not config:clear.

# 9. Lift maintenance.
php artisan up
```

---

## Verification (after `up`)

- [ ] Log in as an **admin** account → super-admin via `Gate::before`; every area loads.
- [ ] Confirm **staff** area (kes / agihan / selenggara) loads for a staff role.
- [ ] Confirm **lawyer** area loads for a `peguam` account.
- [ ] Confirm a **non-admin** role is correctly scoped/gated (sees only its permitted menus/routes,
      blocked from the rest — e.g. `pegawai` cannot reach `kes.keputusan`).
- [ ] Confirm `/peranan` (role-matrix UI) is reachable **only** by admin.
- [ ] Spot-check `selenggara.cuti` (the 33rd permission) gates the Cuti Umum CRUD correctly.

---

## Rollback

1. **Revert the release commit** on `main` and push — webhook re-pulls the previous release.
   Routes return to legacy `role:` / `EnsureRole`; the custom `hasRole` is restored.
2. Because `users.role` is **dual-written throughout this batch**, it remains a faithful fallback —
   legacy gating works **immediately** on revert with no data fix-up.
3. The 5 Spatie tables may be left in place (harmless, unused after revert) or rolled back:
   ```bash
   php artisan migrate:rollback --force   # drops the Spatie tables if you want a clean revert
   php artisan permission:cache-reset
   ```
4. Clear caches and lift maintenance:
   ```bash
   php artisan config:clear && php artisan route:clear && php artisan view:clear
   php artisan up
   ```

---

## Notes

- This batch **retained** `users.role` (display mirror + query + rollback safety). Dropping the column
  is a deliberate later cleanup batch — do not drop it as part of this deploy.
- `RolePermissionSeeder` and `rbac:backfill-roles` are both idempotent — re-running them is safe and is
  the standard recovery move if anything in the sequence is interrupted.
