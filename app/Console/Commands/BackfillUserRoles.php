<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * One-time (idempotent) backfill: assign each user the Spatie role matching their
 * legacy `role` column. Unknown/empty role => safe fallback + loud log.
 */
class BackfillUserRoles extends Command
{
    protected $signature = 'rbac:backfill-roles {--dry}';
    protected $description = 'Assign Spatie roles to existing users from the legacy role column';

    public function handle(): int
    {
        $known = Role::pluck('name')->all();
        $assigned = 0; $fallback = 0;

        User::query()->chunkById(200, function ($users) use ($known, &$assigned, &$fallback) {
            foreach ($users as $user) {
                $role = $user->role;
                if (! $role || ! in_array($role, $known, true)) {
                    $safe = $user->user_type === User::TYPE_LAWYER ? User::ROLE_PEGUAM : User::ROLE_PEGAWAI;
                    $this->warn("User {$user->id} ({$user->email}) role='{$role}' unknown/empty -> fallback '{$safe}'");
                    $role = $safe; $fallback++;
                }
                if (! $this->option('dry')) {
                    $user->syncRoles([$role]);
                }
                $assigned++;
            }
        });

        $this->info("Backfill complete: {$assigned} users processed, {$fallback} fallbacks.".($this->option('dry') ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
