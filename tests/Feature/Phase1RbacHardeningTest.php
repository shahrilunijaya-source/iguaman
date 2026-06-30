<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 1 (consolidation audit) — RBAC privilege-escalation hardening.
 *
 * Locks the BL-4 / BL-5 / awam-drift fixes:
 *   BL-4  /pengguna is held by pengarah/koordinator/ketua_pengarah (NOT admin-only),
 *         so a non-admin must not mint or edit an admin account.
 *   BL-5  the admin matrix is immutable, and urus.peranan cannot be granted to a
 *         non-admin role through the Akses screen.
 *   awam  the citizen role is a protected system role (no rename/delete) — losing it
 *         silently breaks the whole /awam portal gate (permission:awam.portal).
 *
 * Live mysql + idempotent seeds, per repo convention (Batch7AdminUiTest).
 */
class Phase1RbacHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
        (new TestUsersSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        User::where('email', 'like', 'p1rbac%')->delete();
        (new RolePermissionSeeder)->run(); // restore matrix after any toggle test
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'P1 RBAC User',
            'email' => 'p1rbac_user@test.local',
            'role' => User::ROLE_PEGAWAI,
            'user_type' => User::TYPE_STAFF,
            'password' => 'password123',
            'is_active' => 1,
        ], $overrides);
    }

    // ---- BL-4: admin assignment ----

    public function test_non_admin_cannot_create_admin_user(): void
    {
        $this->actingAs($this->user('pengarah@test.local'))
            ->post(route('pengguna.store'), $this->payload([
                'email' => 'p1rbac_newadmin@test.local',
                'role' => User::ROLE_ADMIN,
            ]))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'p1rbac_newadmin@test.local']);
    }

    public function test_admin_can_create_admin_user(): void
    {
        $this->actingAs($this->user('admin@test.local'))
            ->post(route('pengguna.store'), $this->payload([
                'email' => 'p1rbac_okadmin@test.local',
                'role' => User::ROLE_ADMIN,
            ]))
            ->assertRedirect(route('pengguna.index'));

        $u = User::where('email', 'p1rbac_okadmin@test.local')->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->hasRole(User::ROLE_ADMIN));
    }

    public function test_non_admin_cannot_edit_existing_admin(): void
    {
        $admin = $this->user('admin@test.local');

        $this->actingAs($this->user('koordinator@test.local'))
            ->put(route('pengguna.update', $admin->id), $this->payload([
                'email' => $admin->email,
                'role' => User::ROLE_PEGAWAI, // attempt to demote the admin
            ]))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole(User::ROLE_ADMIN));
    }

    public function test_role_and_user_type_must_agree(): void
    {
        $this->actingAs($this->user('admin@test.local'))
            ->post(route('pengguna.store'), $this->payload([
                'email' => 'p1rbac_mismatch@test.local',
                'role' => User::ROLE_PEGUAM,
                'user_type' => User::TYPE_STAFF, // peguam must be lawyer-type
            ]))
            ->assertSessionHasErrors('user_type');
    }

    // ---- BL-5: matrix hardening ----

    public function test_admin_matrix_cannot_be_edited(): void
    {
        $admin = Role::findByName('admin', 'web');

        $this->actingAs($this->user('admin@test.local'))
            ->put(route('peranan.akses.update', $admin->id), ['permissions' => ['kes.view']])
            ->assertSessionHasErrors('permissions');
    }

    public function test_urus_peranan_cannot_be_granted_to_non_admin(): void
    {
        $koor = Role::findByName('koordinator', 'web');
        $perms = $koor->permissions->pluck('name')->push('urus.peranan')->unique()->values()->all();

        $this->actingAs($this->user('admin@test.local'))
            ->put(route('peranan.akses.update', $koor->id), ['permissions' => $perms])
            ->assertSessionHasErrors('permissions');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(Role::findByName('koordinator', 'web')->hasPermissionTo('urus.peranan'));
    }

    public function test_non_protected_matrix_edit_still_works(): void
    {
        $koor = Role::findByName('koordinator', 'web');
        $perms = $koor->permissions->pluck('name')->reject(fn ($p) => $p === 'audit.view')->values()->all();

        $this->actingAs($this->user('admin@test.local'))
            ->put(route('peranan.akses.update', $koor->id), ['permissions' => $perms])
            ->assertRedirect(route('peranan.index'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(Role::findByName('koordinator', 'web')->hasPermissionTo('audit.view'));
    }

    // ---- awam drift ----

    public function test_awam_is_protected_system_role(): void
    {
        $awam = Role::findByName('awam', 'web');

        $this->actingAs($this->user('admin@test.local'))
            ->delete(route('peranan.destroy', $awam->id))
            ->assertSessionHasErrors();

        $this->actingAs($this->user('admin@test.local'))
            ->put(route('peranan.update', $awam->id), ['name' => 'awam_renamed'])
            ->assertSessionHasErrors();

        $still = Role::find($awam->id);
        $this->assertNotNull($still);
        $this->assertSame('awam', $still->name);
    }
}
