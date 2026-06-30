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
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
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
        // 8 roles, 41 permissions. Original RBAC seeder shipped 32; EPIC G cuti CRUD
        // added a 33rd ('selenggara.cuti'); Batch 8 masters added 3 ('selenggara.cawangan',
        // '.kategori_kn', '.jawatan'); Batch 10 slot/calendar added 'slot.view'; Batch 9
        // Khidmat Nasihat added 'khidmat.view' + 'khidmat.manage'; Batch 10 slice 2 added
        // 'slot.manage' (slot generation + penutupan operasi); Batch 11 added 'khidmat.proses'
        // (officer processing: assign PKN + pengesahan janji temu); W15 added 5 claim-ledger
        // permissions ('tuntutan.view/manage/semak/lulus/bayar').
        // RolePermissionSeeder::MATRIX is the source of truth.
        // W10 added 2 roles (pengarah_pembelaan_awam, ketua_pembelaan_awam) + 2 perms
        // (peguam.sokong.jenayah, peguam.keputusan.jenayah). W5 added 'agihan.luar'
        // (external-lawyer assignment). W7 added 'kes.pindah' (branch transfer).
        $this->assertSame(11, Role::count());  // 9 + 2 pembelaan-awam approver roles (W10)
        $this->assertSame(51, Permission::count());  // 49 + agihan.luar (W5) + kes.pindah (W7)
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
