<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 7 — route×role regression matrix (SAFETY NET).
 *
 * Must be GREEN against the CURRENT (pre-swap) authorization behavior: routes are
 * still gated by the legacy EnsureRole middleware + custom User::hasRole() over the
 * role string column. As later batch-7 tasks swap EnsureRole -> Spatie, this test
 * stays the contract the swap must not break.
 *
 * Runs against the LIVE mysql db (iguaman_2in1), per repo convention — phpunit.xml
 * forces sqlite but the legacy baseline migration is MySQL-specific. Seeds are
 * idempotent (updateOrCreate / findOrCreate / syncRoles), so no row cleanup needed.
 */
class Batch7RbacMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        (new TestUsersSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $map = [
            'admin' => 'admin@test.local', 'pengarah' => 'pengarah@test.local',
            'koordinator' => 'koordinator@test.local', 'pegawai' => 'pegawai@test.local',
            'ppuu' => 'ppuu@test.local', 'pembantu_tadbir' => 'pembantu@test.local',
            'ketua_pengarah' => 'kp@test.local', 'peguam' => 'peguam@test.local',
        ];
        return User::where('email', $map[$role])->firstOrFail();
    }

    public static function getMatrix(): array
    {
        // routeName => [routeName, [allowed roles]]
        return [
            'system.utama'    => ['system.utama', ['admin','pengarah','koordinator','pegawai','ppuu','pembantu_tadbir','ketua_pengarah']],
            'kes.index'       => ['kes.index',    ['admin','pengarah','koordinator','pegawai','ppuu','pembantu_tadbir','ketua_pengarah']],
            'pegawai.index'   => ['pegawai.index',['admin','pengarah','koordinator','ketua_pengarah']],
            'pengguna.index'  => ['pengguna.index',['admin','pengarah','koordinator','ketua_pengarah']],
            'audit.index'     => ['audit.index',  ['admin','pengarah','koordinator','ketua_pengarah']],
            // admin is super-admin (Gate::before) so it reaches every area, the lawyer
            // area included — consistent with every staff row above. Under the legacy
            // role:peguam gate admin was excluded; permission:lawyer.area + Gate::before
            // now (correctly) lets admin through.
            'peguam.dashboard'=> ['peguam.dashboard',['peguam','admin']],
        ];
    }

    #[DataProvider('getMatrix')]
    public function test_get_route_access(string $routeName, array $allowed): void
    {
        foreach (['admin','pengarah','koordinator','pegawai','ppuu','pembantu_tadbir','ketua_pengarah','peguam'] as $role) {
            $u = $this->user($role);
            $res = $this->actingAs($u)->get(route($routeName));
            if (in_array($role, $allowed, true)) {
                $this->assertNotEquals(302, $res->status(), "$role should access $routeName");
            } else {
                $res->assertRedirect(route($u->homeRoute()));
            }
        }
    }

    public function test_wrong_area_redirects_not_403(): void
    {
        $this->actingAs($this->user('peguam'))->get(route('kes.index'))
            ->assertRedirect(route('peguam.dashboard'));
        $this->actingAs($this->user('pegawai'))->get(route('peguam.dashboard'))
            ->assertRedirect(route('system.utama'));
    }

    /**
     * Spatie role/permission middleware delimits multiple values with a PIPE. A COMMA
     * makes the 2nd value a guard name (Auth guard [x] not defined -> HTTP 500), the
     * exact regression that broke the tarik-diri / kemaskini-bidang / peguam-panel
     * lifecycle routes. Guard against any comma-delimited Spatie middleware returning.
     */
    public function test_no_comma_delimited_spatie_middleware(): void
    {
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && preg_match('/^(role|permission|role_or_permission):(.+)$/', $mw, $m)) {
                    $this->assertStringNotContainsString(',', $m[2],
                        "Route [{$route->getName()}] middleware '{$mw}' uses a comma — Spatie needs '|' (comma = guard name).");
                }
            }
        }
    }
}
