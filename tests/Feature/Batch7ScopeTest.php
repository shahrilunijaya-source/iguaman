<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch7ScopeTest extends TestCase
{
    private const TAGA = 'PHPUNITA';
    private const TAGB = 'PHPUNITB';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        $this->cleanup();
        Form::create(['nama' => 'A', 'cawangan' => self::TAGA, 'diterima' => '', 'created_at' => now()]);
        Form::create(['nama' => 'B', 'cawangan' => self::TAGB, 'diterima' => '', 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        Form::whereIn('cawangan', [self::TAGA, self::TAGB])->delete();
        User::where('email', 'like', '%@scope.local')->delete();
    }

    private function makeUser(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => "Scope $role", 'email' => "$role@scope.local",
            'password' => Hash::make('x'), 'user_type' => 'staff',
            'role' => $role, 'cawangan' => $cawangan, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);
        return $u;
    }

    public function test_scoped_role_sees_only_own_branch(): void
    {
        $this->actingAs($this->makeUser('pegawai', self::TAGA));
        $rows = Form::whereIn('cawangan', [self::TAGA, self::TAGB])->get();
        $this->assertCount(1, $rows);
        $this->assertSame(self::TAGA, $rows->first()->cawangan);
    }

    public function test_view_all_role_sees_both(): void
    {
        $this->actingAs($this->makeUser('koordinator', self::TAGA));
        $rows = Form::whereIn('cawangan', [self::TAGA, self::TAGB])->get();
        $this->assertCount(2, $rows);
    }

    public function test_admin_super_sees_both(): void
    {
        $this->actingAs($this->makeUser('admin', self::TAGA));
        $this->assertCount(2, Form::whereIn('cawangan', [self::TAGA, self::TAGB])->get());
    }
}
