<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * W9 — register Pembelaan Awam permissions for migrate-only deploys (Hostinger webhook runs
 * composer install + migrations, not db:seed). Mirrors the RolePermissionSeeder MATRIX slice;
 * additive grant (firstOrCreate + givePermissionTo), never syncs away a role's other perms.
 */
return new class extends Migration
{
    private const MATRIX = [
        'pembelaan.view' => ['pembantu_tadbir', 'pegawai', 'koordinator', 'pengarah', 'ketua_pengarah', 'pengarah_pembelaan_awam', 'ketua_pembelaan_awam'],
        'pembelaan.manage' => ['pegawai', 'koordinator', 'pengarah', 'ketua_pengarah', 'pengarah_pembelaan_awam', 'ketua_pembelaan_awam'],
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
