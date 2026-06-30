<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch7AdminUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        (new TestUsersSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Role::where('name', 'like', 'ujian_%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function admin(): User { return User::where('email', 'admin@test.local')->firstOrFail(); }
    private function pegawai(): User { return User::where('email', 'pegawai@test.local')->firstOrFail(); }

    public function test_non_admin_cannot_reach_peranan(): void
    {
        $this->actingAs($this->pegawai())->get(route('peranan.index'))
            ->assertRedirect(route('system.utama'));
    }

    public function test_admin_sees_peranan(): void
    {
        $this->actingAs($this->admin())->get(route('peranan.index'))->assertOk();
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $id = Role::findByName('pegawai', 'web')->id;
        $this->actingAs($this->admin())->delete(route('peranan.destroy', $id))
            ->assertSessionHasErrors();
        $this->assertNotNull(Role::find($id));
    }

    public function test_matrix_update_changes_permissions(): void
    {
        $role = Role::findByName('koordinator', 'web');
        $perms = $role->permissions->pluck('name')->reject(fn ($p) => $p === 'audit.view')->values()->all();
        $this->actingAs($this->admin())->put(route('peranan.akses.update', $role->id), ['permissions' => $perms])
            ->assertRedirect();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(Role::findByName('koordinator', 'web')->hasPermissionTo('audit.view'));
        (new RolePermissionSeeder())->run(); // restore matrix
    }
}
