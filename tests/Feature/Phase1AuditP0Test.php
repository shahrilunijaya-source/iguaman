<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Models\UploadedFile;
use App\Models\User;
use App\Support\LejarTuntutanService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 1 (full audit) — P0 release-blocker regression locks.
 *
 *  DB-01/AUTH-03  seeders + demo login modal must not expose known-password accounts in prod
 *  AUTH-01        case/KN attachment download is branch-scoped (no cross-branch IDOR)
 *  AUTH-02        payment-claim read/mutations are branch-scoped
 *  AUTH-09        bulk-export download is bound to the generating user's directory
 *
 * Live mysql + idempotent seeds, per repo convention (Batch7ScopeTest / LejarTuntutanTest).
 * All fixtures carry the PHPUNITP0 tag and self-clean.
 */
class Phase1AuditP0Test extends TestCase
{
    private const TAG = 'PHPUNITP0';

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
        LejarTuntutanBayaran::where('keterangan', self::TAG)->orWhere('jenis_tuntutan', 'like', self::TAG.'%')->delete();
        UploadedFile::where('nama', 'like', self::TAG.'%')->delete();
        Form::where('cawangan', 'like', self::TAG.'%')->orWhere('nama', 'like', self::TAG.'%')->delete();
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', '%@p0.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makeUser(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => "P0 $role",
            'email' => $role.'-'.uniqid().'@p0.local',
            'password' => Hash::make('x'),
            'user_type' => User::TYPE_STAFF,
            'role' => $role,
            'cawangan' => $cawangan,
            'is_active' => true,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function withEnv(string $env, \Closure $fn): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $env;

        try {
            $fn();
        } finally {
            $this->app['env'] = $original;
        }
    }

    // ---- DB-01: seeders must not plant known-password accounts in production ----

    public function test_seeders_skip_known_password_accounts_in_production(): void
    {
        User::whereIn('email', ['demo@example.com', 'admin@test.local'])->delete();

        $this->withEnv('production', function () {
            (new DemoUserSeeder)->run();
            (new TestUsersSeeder)->run();
        });

        $this->assertDatabaseMissing('users', ['email' => 'demo@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@test.local']);

        // Restore the shared fixtures for any subsequent test in the run.
        (new DemoUserSeeder)->run();
        (new TestUsersSeeder)->run();
    }

    // ---- AUTH-03: demo login modal never rendered in production ----

    public function test_demo_login_modal_hidden_in_production(): void
    {
        // Local/testing still shows it (dev convenience).
        $this->get(route('system.login'))->assertOk()->assertSee('admin@test.local');

        $this->withEnv('production', function () {
            $res = $this->get(route('system.login'));
            $res->assertOk();
            $res->assertDontSee('admin@test.local');
            $res->assertDontSee('js-demo-login');
        });
    }

    // ---- AUTH-01: attachment download is branch-scoped ----

    public function test_attachment_download_denies_cross_branch(): void
    {
        Storage::fake(config('filesystems.lampiran_disk', 'repositori'));
        Storage::fake('local');

        $officerA = $this->makeUser('pegawai', self::TAG.'A');

        $caseB = Form::create(['nama' => self::TAG.' B', 'cawangan' => self::TAG.'B', 'diterima' => '', 'created_at' => now()]);
        $attB = UploadedFile::create([
            'nama' => self::TAG.' docB', 'file_name' => 'b.pdf', 'file_path' => 'lampiran/b.pdf',
            'file_type' => 'pdf', 'id_kes' => $caseB->id, 'uploaded_at' => now(),
        ]);

        // Officer in branch A cannot pull branch B's attachment (CawanganScope hides the case).
        $this->actingAs($officerA)
            ->get(route('lampiran.download', $attB))
            ->assertNotFound();
    }

    public function test_attachment_download_allows_own_branch(): void
    {
        $disk = config('filesystems.lampiran_disk', 'repositori');
        Storage::fake($disk);
        Storage::fake('local');

        $officerA = $this->makeUser('pegawai', self::TAG.'A');

        $caseA = Form::create(['nama' => self::TAG.' A', 'cawangan' => self::TAG.'A', 'diterima' => '', 'created_at' => now()]);
        Storage::disk($disk)->put('lampiran/a.pdf', 'PDFDATA');
        $attA = UploadedFile::create([
            'nama' => self::TAG.' docA', 'file_name' => 'a.pdf', 'file_path' => 'lampiran/a.pdf',
            'file_type' => 'pdf', 'id_kes' => $caseA->id, 'uploaded_at' => now(),
        ]);

        $this->actingAs($officerA)
            ->get(route('lampiran.download', $attA))
            ->assertOk();
    }

    // ---- AUTH-02: payment-claim access is branch-scoped ----

    public function test_claim_show_denies_cross_branch(): void
    {
        $cawA = Cawangan::create(['nama' => self::TAG.'CA']);
        $cawB = Cawangan::create(['nama' => self::TAG.'CB']);
        $officerA = $this->makeUser('pegawai', $cawA->nama);

        $claimB = app(LejarTuntutanService::class)->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_LAIN,
            'jenis_tuntutan' => self::TAG.' fi',
            'keterangan' => self::TAG,
            'jumlah_tuntutan' => 100.00,
            'cawangan_id' => $cawB->id,
        ], 'tester');

        $this->actingAs($officerA)
            ->get(route('tuntutan.show', $claimB))
            ->assertForbidden();
    }

    public function test_claim_show_allows_own_branch(): void
    {
        $cawA = Cawangan::create(['nama' => self::TAG.'CA']);
        $officerA = $this->makeUser('pegawai', $cawA->nama);

        $claimA = app(LejarTuntutanService::class)->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_LAIN,
            'jenis_tuntutan' => self::TAG.' fi',
            'keterangan' => self::TAG,
            'jumlah_tuntutan' => 100.00,
            'cawangan_id' => $cawA->id,
        ], 'tester');

        $this->actingAs($officerA)
            ->get(route('tuntutan.show', $claimA))
            ->assertOk();
    }

    // ---- AUTH-09: bulk-export download bound to the generating user ----

    public function test_export_download_scoped_to_generating_user(): void
    {
        Storage::fake('local');

        $me = $this->user('admin@test.local');
        $otherId = $this->user('pengarah@test.local')->id;

        Storage::disk('local')->put('exports/'.$otherId.'/report.xlsx', 'X');
        Storage::disk('local')->put('exports/'.$me->id.'/mine.xlsx', 'Y');

        // Another user's export is not reachable by filename (scoped to my own directory).
        $this->actingAs($me)
            ->get(route('laporan.muat-turun', 'report.xlsx'))
            ->assertNotFound();

        // My own export downloads fine.
        $this->actingAs($me)
            ->get(route('laporan.muat-turun', 'mine.xlsx'))
            ->assertOk();
    }
}
