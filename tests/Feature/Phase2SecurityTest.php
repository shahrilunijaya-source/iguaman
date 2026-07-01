<?php

namespace Tests\Feature;

use App\Models\ButiranPeguamPanel2;
use App\Models\KhidmatNasihat;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 2 (full audit) — security & data-integrity locks.
 *
 *  AUTH-04  CawanganScope fails CLOSED when a staff branch can't resolve (no cross-branch leak)
 *  AUTH-07  panel-application withdrawal needs decision permission + a still-pending status
 *  AUTH-08  login is rate-limited per identifier; password policy rejects weak passwords
 *
 * Live mysql + idempotent seeds, per repo convention. Fixtures carry the PHPUNITP2 tag.
 */
class Phase2SecurityTest extends TestCase
{
    private const TAG = 'PHPUNITP2';

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
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        ButiranPeguamPanel2::where('namaPeguam', 'like', self::TAG.'%')->orWhere('kpBaru', 'like', self::TAG.'%')->delete();
        KhidmatNasihat::where('nama_mangsa', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', '%@p2.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makeUser(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => "P2 $role", 'email' => $role.'-'.uniqid().'@p2.local',
            'password' => Hash::make('x'), 'user_type' => User::TYPE_STAFF,
            'role' => $role, 'cawangan' => $cawangan, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function makeButiran(string $status, string $suffix): ButiranPeguamPanel2
    {
        return ButiranPeguamPanel2::create([
            'namaPeguam' => self::TAG.' '.$suffix,
            'kpBaru' => self::TAG.$suffix,
            'emelPeguam' => strtolower($suffix).'@p2.local',
            'jantina' => 'L',
            'bilanganKes' => 0,
            'kelulusanAkademik' => 'LLB',
            'keteranganKes' => self::TAG,
            'noTelBimbit' => '0123456789',
            'tahunPengalaman' => 1,
            'tahunPengalamanSyarie' => 0,
            'permohonan_status' => $status,
        ]);
    }

    // ---- AUTH-04: fail closed on unresolved branch ----

    public function test_scope_fails_closed_when_branch_unresolved(): void
    {
        KhidmatNasihat::create([
            'no_permohonan' => self::TAG.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_SELESAI,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'is_percuma' => true,
        ]);

        // Row exists (bypassing the scope) ...
        $this->assertGreaterThanOrEqual(1, KhidmatNasihat::withoutGlobalScopes()->where('nama_mangsa', 'like', self::TAG.'%')->count());

        // ... but a staff user whose branch name matches no cawangan row sees NOTHING (deny), not all.
        $this->actingAs($this->makeUser('pegawai', self::TAG.'_NOBRANCH'));
        $this->assertSame(0, KhidmatNasihat::where('nama_mangsa', 'like', self::TAG.'%')->count());
    }

    // ---- AUTH-07: withdrawal permission + from-guard ----

    public function test_tarik_diri_requires_permission(): void
    {
        $butiran = $this->makeButiran('0', 'A1');

        // pegawai lacks peguam.keputusan → cannot withdraw.
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('permohonan-peguam.tarik', $butiran))
            ->assertSessionHasErrors('akses');

        $this->assertSame('0', $butiran->fresh()->permohonan_status);
    }

    public function test_tarik_diri_blocked_on_decided_application(): void
    {
        $butiran = $this->makeButiran('1', 'A2');

        // admin has permission, but an already-approved application cannot be withdrawn.
        $this->actingAs($this->user('admin@test.local'))
            ->post(route('permohonan-peguam.tarik', $butiran))
            ->assertSessionHasErrors('urutan');

        $this->assertSame('1', $butiran->fresh()->permohonan_status);
    }

    public function test_tarik_diri_succeeds_on_pending_application(): void
    {
        $butiran = $this->makeButiran('0', 'A3');

        $this->actingAs($this->user('admin@test.local'))
            ->post(route('permohonan-peguam.tarik', $butiran), ['sebabBatal' => 'ujian'])
            ->assertRedirect();

        $this->assertSame('3', $butiran->fresh()->permohonan_status);
    }

    // ---- AUTH-08: login rate limit + password policy ----

    public function test_login_is_rate_limited_per_identifier(): void
    {
        $email = 'ratelimit-'.uniqid().'@p2.local';

        // 5 per minute per (email|ip); the 6th attempt is throttled (429) before the controller runs.
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('system.login.attempt'), ['email' => $email, 'password' => 'wrong', 'captcha' => 0])
                ->assertStatus(302);
        }

        $this->post(route('system.login.attempt'), ['email' => $email, 'password' => 'wrong', 'captcha' => 0])
            ->assertStatus(429);
    }

    public function test_change_password_rejects_weak_password(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('password.change.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }
}
