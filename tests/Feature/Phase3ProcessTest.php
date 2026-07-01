<?php

namespace Tests\Feature;

use App\Models\ButiranPeguamPanel2;
use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Models\PeguamPanel;
use App\Models\User;
use App\Support\LejarTuntutanService;
use App\Support\PengantaraanService;
use App\Support\PerakuanService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 3 (full audit) — process-integrity locks.
 *
 *  PROC-03  mediator re-assignment is blocked (no silent displacement)
 *  PROC-04  a rejected claim can be reworked DITOLAK -> DRAF
 *  PROC-12  a decided case can't be re-decided; a closed file can't be re-closed
 *  PROC-16  finalising an INTERIM certificate with no number fails loudly
 *  PROC-20  a decided panel application can't be re-decided
 *  PROC-21  only an active offer can be accepted
 */
class Phase3ProcessTest extends TestCase
{
    private const TAG = 'PHPUNITP3';

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
        $formIds = Form::where('cawangan', 'like', self::TAG.'%')->orWhere('nama', 'like', self::TAG.'%')->pluck('id');
        LejarTuntutanBayaran::where('keterangan', self::TAG)->orWhereIn('id_kes', $formIds)->delete();
        Form::whereIn('id', $formIds)->delete();
        ButiranPeguamPanel2::where('namaPeguam', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', '%@p3.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makeUser(string $role): User
    {
        $u = User::create([
            'name' => "P3 $role", 'email' => $role.'-'.uniqid().'@p3.local',
            'password' => Hash::make('x'), 'user_type' => User::TYPE_STAFF,
            'role' => $role, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function makeButiran(string $status): ButiranPeguamPanel2
    {
        return ButiranPeguamPanel2::create([
            'namaPeguam' => self::TAG.' '.uniqid(), 'kpBaru' => substr(uniqid(), -12),
            'emelPeguam' => uniqid().'@p3.local', 'jantina' => 'L', 'bilanganKes' => 0,
            'kelulusanAkademik' => 'LLB', 'keteranganKes' => self::TAG, 'noTelBimbit' => '0123456789',
            'tahunPengalaman' => 1, 'tahunPengalamanSyarie' => 0,
            'semakan_ppuu' => '1', 'sokonganPengarah' => '1', 'permohonan_status' => $status,
        ]);
    }

    // ---- PROC-04: rejected claim rework ----

    public function test_rejected_claim_can_return_to_draft(): void
    {
        $svc = app(LejarTuntutanService::class);
        $claim = $svc->cipta(['sumber' => LejarTuntutanBayaran::SUMBER_LAIN, 'keterangan' => self::TAG, 'jumlah_tuntutan' => 100], 'tester');
        $svc->transition($claim, 'hantar', 'tester');
        $svc->transition($claim, 'tolak', 'tester', ['ulasan_pelulus' => 'x']);
        $this->assertSame(LejarTuntutanBayaran::STATUS_DITOLAK, $claim->fresh()->status_tuntutan);

        $this->actingAs($this->user('koordinator@test.local'))
            ->post(route('tuntutan.semula', $claim))->assertRedirect();

        $this->assertSame(LejarTuntutanBayaran::STATUS_DRAF, $claim->fresh()->status_tuntutan);
    }

    // ---- PROC-20: panel application can't be re-decided ----

    public function test_decided_application_cannot_be_redecided(): void
    {
        $butiran = $this->makeButiran('1'); // already Lulus

        $this->actingAs($this->user('admin@test.local'))
            ->post(route('permohonan-peguam.keputusan', $butiran), ['keputusan' => 'lulus'])
            ->assertSessionHasErrors('urutan');

        $this->assertSame('1', $butiran->fresh()->permohonan_status);
    }

    // ---- PROC-12: litigation case can't be re-decided ----

    public function test_decided_case_cannot_be_reapproved(): void
    {
        $kes = Form::create(['nama' => self::TAG.' Kes', 'cawangan' => self::TAG, 'diterima' => 'Ya', 'status' => 'Diterima', 'created_at' => now()]);

        $this->actingAs($this->user('admin@test.local'))
            ->post(route('kes.lulus', $kes))
            ->assertStatus(409);
    }

    // ---- PROC-21: only an active offer can be accepted ----

    public function test_cannot_accept_a_non_offered_case(): void
    {
        $panel = PeguamPanel::whereKey($this->user('peguam@test.local')->lawyerProfile?->id)->firstOrFail();
        $kes = Form::create([
            'nama' => self::TAG.' Tawaran', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now(),
            'nama_pegawai_yang_dapat_kes' => $panel->nama_peguam,
            'status_agihan' => '2', // DITERIMA — no longer an open offer
        ]);

        $this->actingAs($this->user('peguam@test.local'))
            ->post(route('peguam.terima', $kes))
            ->assertStatus(409);
    }

    // ---- PROC-16: interim-without-number fails loudly ----

    public function test_finalising_interim_without_number_fails(): void
    {
        $kes = Form::create([
            'nama' => self::TAG.' Perakuan', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now(),
            'status_perakuan' => PerakuanService::STATUS_INTERIM, 'no_perakuan' => null,
        ]);

        $this->expectException(HttpException::class);
        app(PerakuanService::class)->muktamadkan($kes, $this->user('admin@test.local'));
    }

    // ---- PROC-03: mediator can't be silently displaced ----

    public function test_mediator_cannot_be_reassigned_without_cancel(): void
    {
        $svc = app(PengantaraanService::class);
        $actor = $this->user('admin@test.local');
        $officerA = $this->makeUser('pegawai');
        $officerB = $this->makeUser('pegawai');
        $kes = Form::create(['nama' => self::TAG.' Mediasi', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now()]);

        $svc->agihPengantara($kes, $officerA->id, $actor);
        $this->assertSame($officerA->id, (int) $kes->fresh()->id_pegawai_pengantara);

        $this->expectException(HttpException::class);
        $svc->agihPengantara($kes->fresh(), $officerB->id, $actor);
    }
}
