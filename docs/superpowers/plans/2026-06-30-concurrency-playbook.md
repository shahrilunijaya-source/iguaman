# Concurrency Playbook — run parallel Claude sessions on iGuaman 2in1 without collision

> Why this exists: on 2026-06-30 a second autonomous session interleaved commits, clobbered
> files mid-edit ("modified since read"), and mislabeled commits — all because two agents
> shared one branch + one working tree + one DB. This playbook makes parallel work safe.

## The rule

**1 session = 1 worktree = 1 branch = 1 database = 1 port.** Never two sessions in the same
working tree or on the same branch.

## Collision sources (what broke) → fix

| Collision | Root cause | Fix |
|-----------|-----------|-----|
| Interleaved / mislabeled commits | shared branch | branch per track |
| "File modified since read" clobbers | shared working dir | git worktree per track |
| migrate / seed / test cross-contamination | shared MySQL DB | per-worktree `DB_DATABASE` |
| dev-server port conflict | shared :8777 | per-worktree serve port |
| merge churn on routes/sidebar/seeders | shared "hotspot" files | additive-only edit rules (below) |

## Isolation model

| Axis | Convention |
|------|-----------|
| Code | `git worktree` under `.worktrees/<branch>` (gitignored) |
| Branch | base off the integration branch HEAD (`batch-7-rbac`); one branch per track |
| DB | each worktree `.env` sets `DB_DATABASE=iguaman_2in1_<tag>` |
| Port | main `:8777`, track A `:8778`, track B `:8779`, … |
| Deps | `vendor/`, `node_modules/`, `public/build` junctioned from main (same composer.lock) |

Launch one with: `scripts/new-worktree.ps1 -Branch <name> -Db <dbname> -Port <port>`.

## Parallel track map (janjitemu batches)

Dependency DAG:

```
8 masters ✅ (on batch-7-rbac)
   ├── 9  KN wizard         ┐  9 ⟂ 10  (both need only 8) → RUN IN PARALLEL
   └── 10 slot/calendar     ┘
            └── 11 officer processing   (needs 9 + 10)
                   └── 12 feedback + reports (needs 9/11)
            9 ──── 13 public portal (awam) (needs 9)
```

**Parallelizable now: Batch 9 (KN wizard) ‖ Batch 10 (slot/calendar engine).** They touch
different tables (`khidmat_nasihat` vs `temu_janji`/`slot_temu_janji`/calendar) and different
controllers. 11/12/13 are sequential after the 9+10 merge.

**Integration point:** wizard **Step 3 (slot janji temu)** consumes the batch-10
`SlotAvailabilityService`. In Batch 9, hide Step 3 behind a thin `SlotProvider` interface with
a stub; swap to the real service when Batch 10 merges. No cross-branch coupling until merge.

## Hotspot files — additive-only rules

These get touched by multiple tracks; keep edits minimal + append-only so merges are trivial:

| File | Rule |
|------|------|
| `routes/web.php` | append your track's routes in one delimited block; never reorder. (Future: extract `routes/janjitemu.php` and `require` it.) |
| `resources/views/layouts/staff.blade.php` | add your own `@can(...)` sidebar block; don't move others |
| `database/seeders/DatabaseSeeder.php` | add exactly one `->call()` line |
| `database/seeders/RolePermissionSeeder.php` | append your perms to `MATRIX`; never reorder |

## Test-DB isolation (REQUIRED for concurrent test runs)

Today every test `setUp()` hardcodes `database => 'iguaman_2in1'`. Two suites running at once
collide. **In a worktree, change that line to read the env DB:**

```php
config(['database.default' => 'mysql',
        'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
```

Then each worktree's `.env` DB isolates its test run. (Long-term: lift this into `tests/TestCase.php`
so no per-file edit is needed.) Until then, if you can't isolate the DB, **run test suites serially** —
never two `php artisan test` at once against the same DB.

## Merge discipline

- Short-lived branches; `git rebase batch-7-rbac` (or merge) frequently to stay current.
- Merge **one branch at a time** into `batch-7-rbac` (the janjitemu integration branch).
- After each merge: run the FULL suite on the integration branch before the next merge.
- Suggested order: **10 → 9** (so 9 swaps its slot stub for the real service), or 9 → 10 then
  do the Step-3 swap. 11/12/13 follow.

## Cleanup

When a track is merged: `git worktree remove .worktrees/<branch>` and
`DROP DATABASE iguaman_2in1_<tag>`. See `finishing-a-development-branch` skill.

## Quick reference

| Do | Don't |
|----|-------|
| One worktree per session | Two sessions in `2in1/` root |
| Branch per track off `batch-7-rbac` | Two sessions committing to one branch |
| Per-worktree `.env` DB + port | Shared `iguaman_2in1` for migrate/test in parallel |
| Append-only edits to hotspots | Reorder/rewrite routes, seeders, sidebar |
| Merge + full-suite one at a time | Parallel merges |
