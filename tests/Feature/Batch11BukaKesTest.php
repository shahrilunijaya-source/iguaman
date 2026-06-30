<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\Scopes\CawanganScope;
use App\Models\TemuJanji;
use App\Models\User;
use App\Support\KhidmatProsesService;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 11 slice C — KN -> forms case bridge ("Buka Kes").
 *
 * An officer opens a litigation case (a `forms` row) from a completed Khidmat
 * Nasihat. Prefill from the KN, set the appointment date into
 * forms.tarikh_khidmat_nasihat, back-link via khidmat_nasihat.id_forms, and guard
 * against double-create + against opening a non-SELESAI KN.
 *
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up. forms has a
 * CawanganScope global scope keyed on the `cawangan` string column, so reads in
 * assertions strip that scope explicitly.
 */
class Batch11BukaKesTest extends TestCase
{
    private const TAG = 'PHPUNIT11C';

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
        // forms is CawanganScope-guarded — strip it so cleanup spans all branches.
        Form::withoutGlobalScope(CawanganScope::class)
            ->where('nama', 'like', self::TAG.'%')
            ->orWhere('didaftarkan_oleh', 'like', self::TAG.'%')
            ->delete();
        TemuJanji::where('cipta_oleh', 'like', self::TAG.'%')->delete();
        KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->delete();
        User::where('email', 'like', '%@b11c.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** A staff actor (any role) pinned to a branch string. */
    private function actor(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => self::TAG.' '.$role, 'email' => $role.'-'.Str::random(8).'@b11c.local',
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

    /** A SELESAI KN with a category, victim details, a branch, and a linked appointment. */
    private function makeSelesaiKn(string $branchNama, array $attrs = []): KhidmatNasihat
    {
        $branch = $this->branch($branchNama);
        $kategori = RefKategoriKn::firstOrFail();

        $kn = KhidmatNasihat::create(array_merge([
            'no_permohonan' => self::TAG.'-KN-'.Str::random(8),
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Mangsa',
            'id_pengenalan_mangsa' => '900101145523', // forms.nokp is varchar(12) — store IC without dashes
            'jenis_kes' => '085',
            'id_kategori' => $kategori->id,
            'cawangan_id' => $branch->id,
            'status_kn' => KhidmatNasihat::STATUS_SELESAI,
        ], $attrs));

        $temu = TemuJanji::create([
            'id_khidmat_nasihat' => $kn->id,
            'cawangan_id' => $branch->id,
            'tarikh_temu_janji' => '2026-03-15',
            'masa_mula' => '09:00:00',
            'masa_akhir' => '09:30:00',
            'status' => 'SELESAI',
            'cipta_oleh' => self::TAG,
        ]);
        $kn->update(['id_temu_janji' => $temu->id]);
        $kn->refresh();

        return $kn;
    }

    private function svc(): KhidmatProsesService
    {
        return app(KhidmatProsesService::class);
    }

    /** Read a forms row past the CawanganScope. */
    private function findForm(int $id): ?Form
    {
        return Form::withoutGlobalScope(CawanganScope::class)->find($id);
    }

    // ---- happy path: one linked, prefilled forms row ----

    public function test_buka_kes_creates_one_linked_prefilled_forms_row(): void
    {
        $kn = $this->makeSelesaiKn('JBG PUTRAJAYA');
        $actor = $this->actor('pegawai', 'JBG PUTRAJAYA');
        $kategoriName = RefKategoriKn::find($kn->id_kategori)->jenis_kategori;

        $before = Form::withoutGlobalScope(CawanganScope::class)->count();

        $form = $this->svc()->bukaKes($kn, $actor);

        $after = Form::withoutGlobalScope(CawanganScope::class)->count();
        $this->assertSame($before + 1, $after, 'exactly one forms row created');

        $fresh = $this->findForm($form->id);
        $this->assertNotNull($fresh);

        // prefill from KN
        $this->assertSame($kn->nama_mangsa, $fresh->nama);
        $this->assertSame($kn->id_pengenalan_mangsa, $fresh->nokp);
        $this->assertSame($kn->jenis_kes, $fresh->jenis_kes);
        $this->assertSame($kategoriName, $fresh->kategori_kes);

        // appointment date -> tarikh_khidmat_nasihat (cast to date)
        $this->assertSame('2026-03-15', $fresh->tarikh_khidmat_nasihat->toDateString());

        // branch string lines up with CawanganScope
        $this->assertSame('JBG PUTRAJAYA', $fresh->cawangan);

        // file number generated
        $this->assertNotEmpty($fresh->no_fail);

        // back-link
        $this->assertSame($form->id, $kn->fresh()->id_forms);
    }

    public function test_buka_kes_falls_back_to_actor_branch_when_kn_branch_null(): void
    {
        $kn = $this->makeSelesaiKn('JBG PUTRAJAYA', ['cawangan_id' => null]);
        $actor = $this->actor('pegawai', 'JBG SELANGOR');

        $form = $this->svc()->bukaKes($kn, $actor);

        $fresh = $this->findForm($form->id);
        $this->assertSame('JBG SELANGOR', $fresh->cawangan);
    }

    // ---- guard: double-create ----

    public function test_buka_kes_does_not_create_second_row_when_already_opened(): void
    {
        $kn = $this->makeSelesaiKn('JBG PUTRAJAYA');
        $actor = $this->actor('pegawai', 'JBG PUTRAJAYA');

        $first = $this->svc()->bukaKes($kn, $actor);

        $before = Form::withoutGlobalScope(CawanganScope::class)->count();

        try {
            $this->svc()->bukaKes($kn->fresh(), $actor);
            $this->fail('expected RuntimeException on second buka kes');
        } catch (RuntimeException $e) {
            $this->assertStringContainsStringIgnoringCase('telah dibuka', $e->getMessage());
        }

        $after = Form::withoutGlobalScope(CawanganScope::class)->count();
        $this->assertSame($before, $after, 'no second forms row');
        $this->assertSame($first->id, $kn->fresh()->id_forms);
    }

    // ---- guard: KN not SELESAI ----

    public function test_buka_kes_rejected_when_kn_not_selesai(): void
    {
        $kn = $this->makeSelesaiKn('JBG PUTRAJAYA', ['status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES]);
        $actor = $this->actor('pegawai', 'JBG PUTRAJAYA');

        $before = Form::withoutGlobalScope(CawanganScope::class)->count();

        try {
            $this->svc()->bukaKes($kn, $actor);
            $this->fail('expected RuntimeException for non-SELESAI KN');
        } catch (RuntimeException $e) {
            $this->assertStringContainsStringIgnoringCase('belum selesai', $e->getMessage());
        }

        $after = Form::withoutGlobalScope(CawanganScope::class)->count();
        $this->assertSame($before, $after, 'no forms row created');
        $this->assertNull($kn->fresh()->id_forms);
    }

    // ---- controller: redirect to kes.show on success ----

    public function test_buka_kes_route_redirects_to_kes_show(): void
    {
        $kn = $this->makeSelesaiKn('JBG PUTRAJAYA');

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.proses.buka-kes', $kn))
            ->assertRedirect();

        $this->assertNotNull($kn->fresh()->id_forms);
    }

    // ---- permission: pembantu_tadbir lacks khidmat.proses -> 403/redirect ----

    public function test_buka_kes_forbidden_for_pembantu_tadbir(): void
    {
        $kn = $this->makeSelesaiKn('JBG PUTRAJAYA');

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('khidmat.proses.buka-kes', $kn))
            ->assertStatus(302); // permission middleware redirects

        $this->assertNull($kn->fresh()->id_forms);
    }
}
