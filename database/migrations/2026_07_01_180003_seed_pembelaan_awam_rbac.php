<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * W10 — provision the Pembelaan Awam approver tier on migrate-only deploys
 * (mirrors RolePermissionSeeder; same idempotent pattern as 130002 / 170002).
 */
return new class extends Migration
{
    /** permission => roles. */
    private const MATRIX = [
        'peguam.sokong.jenayah' => ['pengarah_pembelaan_awam'],
        'peguam.keputusan.jenayah' => ['ketua_pembelaan_awam'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['pengarah_pembelaan_awam', 'ketua_pembelaan_awam'] as $role) {
            $r = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            // Reach the staff area + the panel-registration queue.
            foreach (['system.view', 'peguam.permohonan.view'] as $access) {
                $r->givePermissionTo(Permission::firstOrCreate(['name' => $access, 'guard_name' => 'web']));
            }
        }

        foreach (self::MATRIX as $perm => $roles) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            foreach ($roles as $role) {
                Role::findByName($role, 'web')->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::MATRIX))->delete();
        Role::whereIn('name', ['pengarah_pembelaan_awam', 'ketua_pembelaan_awam'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
