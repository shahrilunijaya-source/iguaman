<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch7SeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        User::where('email', 'like', '%@seedtest.local')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_all_roles_and_permissions_exist(): void
    {
        // 8 roles, 33 permissions. The original RBAC seeder (commit 16e1c19) shipped 32;
        // EPIC G cuti CRUD (commit 2d4683a, same batch) added a 33rd permission
        // 'selenggara.cuti'. RolePermissionSeeder::MATRIX is the source of truth.
        $this->assertSame(8, Role::count());
        $this->assertSame(33, Permission::count());
    }

    public function test_admin_can_everything_via_gate_before(): void
    {
        $admin = User::create([
            'name' => 'A', 'email' => 'admin@seedtest.local', 'password' => Hash::make('x'),
            'user_type' => 'staff', 'role' => 'admin', 'is_active' => true,
        ]);
        $admin->syncRoles(['admin']);
        foreach (Permission::pluck('name') as $perm) {
            $this->assertTrue($admin->can($perm), "admin should pass $perm via Gate::before");
        }
    }

    public function test_approver_permissions_match_matrix(): void
    {
        $this->assertTrue(Role::findByName('pengarah', 'web')->hasPermissionTo('kes.keputusan'));
        $this->assertTrue(Role::findByName('ketua_pengarah', 'web')->hasPermissionTo('peguam.keputusan'));
        $this->assertFalse(Role::findByName('pegawai', 'web')->hasPermissionTo('kes.keputusan'));
        $this->assertFalse(Role::findByName('pembantu_tadbir', 'web')->hasPermissionTo('selenggara.pegawai'));
    }

    public function test_backfill_assigns_and_falls_back(): void
    {
        $known = User::create([
            'name' => 'K', 'email' => 'pengarah@seedtest.local', 'password' => Hash::make('x'),
            'user_type' => 'staff', 'role' => 'pengarah', 'is_active' => true,
        ]);
        $unknown = User::create([
            'name' => 'U', 'email' => 'weird@seedtest.local', 'password' => Hash::make('x'),
            'user_type' => 'staff', 'role' => 'legacy_unknown', 'is_active' => true,
        ]);

        $this->artisan('rbac:backfill-roles')->assertSuccessful();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($known->fresh()->hasRole('pengarah'));
        $this->assertTrue($unknown->fresh()->hasRole('pegawai')); // safe fallback
    }
}
