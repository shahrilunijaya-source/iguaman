<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * W19 — provision the mediator-assignment permission on migrate-only deploys
 * (mirrors RolePermissionSeeder::MATRIX; same idempotent pattern as 170002/190004).
 * Intake + listing reuse pengantaraan.manage; only the assign action gets a new gate.
 */
return new class extends Migration
{
    /** permission => roles. */
    private const MATRIX = [
        'pengantaraan.agih' => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
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
