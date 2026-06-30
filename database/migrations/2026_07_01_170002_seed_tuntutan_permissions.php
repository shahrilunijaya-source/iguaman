<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * W15 — provision the claim-ledger permissions on migrate-only deploys (mirrors
 * RolePermissionSeeder::MATRIX; same idempotent pattern as 130002).
 */
return new class extends Migration
{
    /** permission => roles. */
    private const MATRIX = [
        'tuntutan.view' => ['pembantu_tadbir', 'pegawai', 'koordinator', 'pengarah', 'ketua_pengarah', 'ppuu'],
        'tuntutan.manage' => ['koordinator', 'pegawai', 'pembantu_tadbir', 'pengarah'],
        'tuntutan.semak' => ['ppuu', 'koordinator', 'pembantu_tadbir'],
        'tuntutan.lulus' => ['pengarah', 'ketua_pengarah'],
        'tuntutan.bayar' => ['koordinator', 'pengarah', 'ketua_pengarah'],
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
