<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'awam.portal', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'awam', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
    }

    public function down(): void
    {
        Role::where('name', 'awam')->delete();
        Permission::where('name', 'awam.portal')->delete();
    }
};
