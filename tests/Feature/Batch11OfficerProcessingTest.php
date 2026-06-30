<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\TemuJanji;
use App\Models\User;
use App\Support\KhidmatProsesService;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 11 slices A+B — officer processing of Khidmat Nasihat.
 *   A: branch-scoped officer list + filters + dashboard count tiles.
 *   B: assign PKN officer (BAHARU->DALAM_PROSES) + pengesahan janji temu
 *      (accept/reject/attendance/complete) with explicit transition guards.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch11OfficerProcessingTest extends TestCase
{
    private const TAG = 'PHPUNIT11';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
        (new Batch8MastersSeeder)->run();
        (new TestUsersSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        TemuJanji::where('cipta_oleh', 'like', self::TAG.'%')->delete();
        KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->delete();
        User::where('email', 'like', '%@b11.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** A branch-scoped officer with the `khidmat.proses` permission, pinned to a branch string. */
    private function officer(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => "B11 $role", 'email' => $role.'-'.uniqid().'@b11.local',
            'password' => bcrypt('x'), 'user_type' => 'staff',
            'role' => $role, 'cawangan' => $cawangan, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function branch(string $nama): Cawangan
    {
        return Cawangan::where('nama', $nama)->firstOrFail();
    }

    private function makeKhidmat(array $attrs = []): KhidmatNasihat
    {
        return KhidmatNasihat::create(array_merge([
            'no_permohonan' => self::TAG.'-KN-'.uniqid(),
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
        ], $attrs));
    }

    private function makeTemu(KhidmatNasihat $kn, string $status = 'MENUNGGU'): TemuJanji
    {
        // temu_janji.cawangan_id is NOT NULL — backfill the KN's branch if absent.
        if ($kn->cawangan_id === null) {
            $kn->update(['cawangan_id' => $this->branch('JBG PUTRAJAYA')->id]);
            $kn->refresh();
        }

        $temu = TemuJanji::create([
            'id_khidmat_nasihat' => $kn->id,
            'cawangan_id' => $kn->cawangan_id,
            'tarikh_temu_janji' => now()->addDay()->toDateString(),
            'masa_mula' => '09:00:00',
            'masa_akhir' => '09:30:00',
            'status' => $status,
            'cipta_oleh' => self::TAG,
        ]);
        $kn->update(['id_temu_janji' => $temu->id]);

        return $temu;
    }

    // ---- Slice A: permission ----

    public function test_proses_permission_exists_and_gates_roles(): void
    {
        $this->assertTrue(Permission::where('name', 'khidmat.proses')->exists());
        $this->assertTrue(Role::findByName('pegawai', 'web')->hasPermissionTo('khidmat.proses'));
        $this->assertTrue(Role::findByName('koordinator', 'web')->hasPermissionTo('khidmat.proses'));
        $this->assertTrue(Role::findByName('pengarah', 'web')->hasPermissionTo('khidmat.proses'));
        $this->assertFalse(Role::findByName('pembantu_tadbir', 'web')->hasPermissionTo('khidmat.proses'));
    }

    public function test_officer_list_gated_blocks_pembantu_tadbir(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))->get(route('khidmat.proses.index'))->assertOk();
        $this->actingAs($this->user('pembantu@test.local'))->get(route('khidmat.proses.index'))->assertStatus(302);
        $this->actingAs($this->user('peguam@test.local'))->get(route('khidmat.proses.index'))->assertStatus(302);
    }

    // ---- Slice A: list + filters ----

    public function test_officer_list_lists_rows(): void
    {
        $row = $this->makeKhidmat();

        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('khidmat.proses.index'))
            ->assertOk()
            ->assertSee($row->no_permohonan);
    }

    public function test_officer_list_filters_by_status_and_category_and_pkn(): void
    {
        $assignee = $this->officer('pegawai', null);
        $baharu = $this->makeKhidmat(['nama_mangsa' => self::TAG.' Baharu']);
        $proses = $this->makeKhidmat([
            'nama_mangsa' => self::TAG.' Proses',
            'status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES,
            'id_pegawai_kn' => $assignee->id,
        ]);

        $coord = $this->user('koordinator@test.local'); // view-all -> sees both branches

        // status_kn filter
        $this->actingAs($coord)
            ->get(route('khidmat.proses.index', ['status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]))
            ->assertOk()
            ->assertSee($proses->no_permohonan)
            ->assertDontSee($baharu->no_permohonan);

        // PKN-officer filter
        $this->actingAs($coord)
            ->get(route('khidmat.proses.index', ['id_pegawai_kn' => $assignee->id]))
            ->assertOk()
            ->assertSee($proses->no_permohonan)
            ->assertDontSee($baharu->no_permohonan);
    }

    public function test_officer_list_is_branch_scoped(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $selangor = $this->branch('JBG SELANGOR');

        $mine = $this->makeKhidmat(['nama_mangsa' => self::TAG.' Mine', 'cawangan_id' => $putrajaya->id]);
        $other = $this->makeKhidmat(['nama_mangsa' => self::TAG.' Other', 'cawangan_id' => $selangor->id]);

        // Branch-pinned officer (no view-all) sees only own branch.
        $this->actingAs($this->officer('pegawai', 'JBG PUTRAJAYA'))
            ->get(route('khidmat.proses.index'))
            ->assertOk()
            ->assertSee($mine->no_permohonan)
            ->assertDontSee($other->no_permohonan);

        // view-all role (koordinator) sees both.
        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('khidmat.proses.index'))
            ->assertOk()
            ->assertSee($mine->no_permohonan)
            ->assertSee($other->no_permohonan);
    }

    // ---- Slice A: dashboard counts ----

    public function test_dashboard_counts_by_status_for_branch(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $selangor = $this->branch('JBG SELANGOR');

        $this->makeKhidmat(['cawangan_id' => $putrajaya->id, 'status_kn' => KhidmatNasihat::STATUS_BAHARU]);
        $this->makeKhidmat(['cawangan_id' => $putrajaya->id, 'status_kn' => KhidmatNasihat::STATUS_BAHARU]);
        $this->makeKhidmat(['cawangan_id' => $putrajaya->id, 'status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]);
        $this->makeKhidmat(['cawangan_id' => $selangor->id, 'status_kn' => KhidmatNasihat::STATUS_BAHARU]);

        $svc = app(KhidmatProsesService::class);
        $counts = $svc->dashboardCounts($putrajaya->id);

        $this->assertSame(2, $counts[KhidmatNasihat::STATUS_BAHARU]);
        $this->assertSame(1, $counts[KhidmatNasihat::STATUS_DALAM_PROSES]);
        $this->assertSame(0, $counts[KhidmatNasihat::STATUS_SELESAI]);
    }

    // ---- Slice B: assign PKN ----

    public function test_assign_pkn_moves_baharu_to_dalam_proses(): void
    {
        $kn = $this->makeKhidmat(['status_kn' => KhidmatNasihat::STATUS_BAHARU]);
        $assignee = $this->officer('pegawai', null);

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.assign', $kn), ['id_pegawai_kn' => $assignee->id])
            ->assertRedirect();

        $fresh = $kn->fresh();
        $this->assertSame($assignee->id, $fresh->id_pegawai_kn);
        $this->assertSame(KhidmatNasihat::STATUS_DALAM_PROSES, $fresh->status_kn);
        $this->assertNotNull($fresh->tarikh_proses);
    }

    public function test_assign_pkn_rejected_when_not_baharu(): void
    {
        $kn = $this->makeKhidmat(['status_kn' => KhidmatNasihat::STATUS_SELESAI]);
        $assignee = $this->officer('pegawai', null);

        $this->actingAs($this->user('pegawai@test.local'))
            ->from(route('khidmat.proses.index'))
            ->post(route('khidmat.proses.assign', $kn), ['id_pegawai_kn' => $assignee->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($kn->fresh()->id_pegawai_kn);
        $this->assertSame(KhidmatNasihat::STATUS_SELESAI, $kn->fresh()->status_kn);
    }

    public function test_assign_pkn_forbidden_for_pembantu_tadbir(): void
    {
        $kn = $this->makeKhidmat();
        $assignee = $this->officer('pegawai', null);

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('khidmat.proses.assign', $kn), ['id_pegawai_kn' => $assignee->id])
            ->assertStatus(302); // permission middleware redirects
    }

    // ---- Slice B: pengesahan janji temu transitions ----

    public function test_accept_appointment_menunggu_to_disahkan(): void
    {
        $kn = $this->makeKhidmat();
        $temu = $this->makeTemu($kn, 'MENUNGGU');

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.temu.terima', $kn))
            ->assertRedirect();

        $this->assertSame('DISAHKAN', $temu->fresh()->status);
    }

    public function test_reject_appointment_menunggu_to_batal_with_reason(): void
    {
        $kn = $this->makeKhidmat(['status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]);
        $temu = $this->makeTemu($kn, 'MENUNGGU');

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.temu.tolak', $kn), ['ulasan_pegawai' => self::TAG.' tidak layak'])
            ->assertRedirect();

        $this->assertSame('BATAL', $temu->fresh()->status);
        $this->assertStringContainsString('tidak layak', (string) $kn->fresh()->ulasan_pegawai);
        // BL-3: rejecting the appointment cancels the advisory request — no orphan.
        $this->assertSame(KhidmatNasihat::STATUS_BATAL, $kn->fresh()->status_kn);
    }

    public function test_attendance_disahkan_to_hadir(): void
    {
        $kn = $this->makeKhidmat();
        $temu = $this->makeTemu($kn, 'DISAHKAN');

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.temu.kehadiran', $kn), ['hadir' => '1'])
            ->assertRedirect();

        $this->assertSame('HADIR', $temu->fresh()->status);
    }

    public function test_attendance_disahkan_to_tidak_hadir_closes_kn(): void
    {
        $kn = $this->makeKhidmat(['status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]);
        $temu = $this->makeTemu($kn, 'DISAHKAN');

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.temu.kehadiran', $kn), ['hadir' => '0'])
            ->assertRedirect();

        $this->assertSame('TIDAK_HADIR', $temu->fresh()->status);
        // BL-3: a no-show is terminal (Selesai Tanpa Kehadiran) — never hangs in DALAM_PROSES.
        $this->assertSame(KhidmatNasihat::STATUS_SELESAI, $kn->fresh()->status_kn);
    }

    public function test_complete_hadir_to_selesai_sets_kn_selesai(): void
    {
        $kn = $this->makeKhidmat(['status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]);
        $temu = $this->makeTemu($kn, 'HADIR');

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.temu.selesai', $kn))
            ->assertRedirect();

        $this->assertSame('SELESAI', $temu->fresh()->status);
        $this->assertSame(KhidmatNasihat::STATUS_SELESAI, $kn->fresh()->status_kn);
    }

    public function test_invalid_transition_accept_when_already_disahkan_is_rejected(): void
    {
        $kn = $this->makeKhidmat();
        $temu = $this->makeTemu($kn, 'DISAHKAN');

        $this->actingAs($this->user('pegawai@test.local'))
            ->from(route('khidmat.proses.index'))
            ->post(route('khidmat.proses.temu.terima', $kn))
            ->assertRedirect()
            ->assertSessionHas('error');

        // unchanged
        $this->assertSame('DISAHKAN', $temu->fresh()->status);
    }

    public function test_invalid_transition_complete_when_not_hadir_is_rejected(): void
    {
        $kn = $this->makeKhidmat(['status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]);
        $temu = $this->makeTemu($kn, 'MENUNGGU');

        $this->actingAs($this->user('pegawai@test.local'))
            ->from(route('khidmat.proses.index'))
            ->post(route('khidmat.proses.temu.selesai', $kn))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('MENUNGGU', $temu->fresh()->status);
        $this->assertSame(KhidmatNasihat::STATUS_DALAM_PROSES, $kn->fresh()->status_kn);
    }
}
