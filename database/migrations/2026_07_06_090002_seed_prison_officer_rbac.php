<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * W1 — provision the `prison_officer` role on migrate-only deploys (mirrors
 * RolePermissionSeeder; same idempotent pattern as 130002 / 170002 / 180003).
 *
 * Prison/clinic officers file Khidmat Nasihat on behalf of inmates. They reach the
 * staff area + KN intake — no new permissions, only an existing-permission grant.
 */
return new class extends Migration
{
    private const ROLE = 'prison_officer';

    private const GRANTS = ['system.view', 'khidmat.view', 'khidmat.manage'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => self::ROLE, 'guard_name' => 'web']);
        foreach (self::GRANTS as $perm) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', self::ROLE)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
