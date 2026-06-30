<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * W7/W3 — provision the branch-transfer permission on migrate-only deploys
 * (mirrors RolePermissionSeeder::MATRIX; same idempotent pattern as 090002).
 * Branch managers only: pengarah / koordinator / ppuu.
 */
return new class extends Migration
{
    /** permission => roles. */
    private const MATRIX = [
        'kes.pindah' => ['pengarah', 'koordinator', 'ppuu'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MATRIX as $perm => $roles) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            foreach ($roles as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::MATRIX))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
