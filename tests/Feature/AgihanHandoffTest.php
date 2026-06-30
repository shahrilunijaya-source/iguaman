<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Models\User;
use App\Support\StatusAgihan;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * BL-1 (spine -> lawyer offer hand-off + retired single-step entry) and
 * BL-2 (status-9 recovery). Live mysql per repo convention; TAG rows self-clean.
 *
 * The core BL-1 regression: a case offered through the 3-tier spine writes numeric
 * status_agihan '1', but the lawyer area used to filter the literal string
 * 'Ditawarkan' — so spine offers never surfaced. These tests lock the bucketValues
 * fix (both '1' and 'Ditawarkan' surface) and the numeric accept/reject writes.
 */
class AgihanHandoffTest extends TestCase
{
    private const TAG = 'PHPUNITAH';

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
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = Form::where('cawangan', self::TAG)->pluck('id');
        if ($ids->isNotEmpty()) {
            SejarahPeguamPanel::whereIn('id_kes', $ids)->delete();
            Form::whereIn('id', $ids)->delete();
        }
        PeguamPanel::where('nama_peguam', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', '%@ah.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makeCase(array $attrs = []): Form
    {
        return Form::create(array_merge([
            'nama' => self::TAG.' OYD',
            'cawangan' => self::TAG,
            'diterima' => '',
            'created_at' => now(),
        ], $attrs));
    }

    private function makePeguam(string $nama, string $kp): PeguamPanel
    {
        return PeguamPanel::create([
            'nama_peguam' => $nama, 'kp_peguam' => $kp, 'tel_peguam' => '0123456789',
            'emel_peguam' => strtolower($kp).'@firma.my', 'nama_firma' => 'Tetuan AH',
            'alamat_firma_1' => 'A1', 'alamat_firma_2' => 'A2', 'poskod_firma' => '40000',
            'negeri_firma' => 'Selangor', 'tel_firma' => '0312345678',
        ]);
    }

    private function lawyer(string $kp): User
    {
        $u = User::create([
            'name' => self::TAG.' Ali', 'email' => 'ali@ah.local', 'password' => Hash::make('x'),
            'user_type' => 'lawyer', 'role' => 'peguam', 'id_peguam_panel' => $kp, 'is_active' => true,
        ]);
        $u->syncRoles(['peguam']);

        return $u;
    }

    // ---- BL-1: spine -> lawyer offer hand-off ----

    public function test_spine_offered_case_reaches_lawyer_tawaran(): void
    {
        $this->makePeguam(self::TAG.' Ali', 'AHKP1');
        $lawyer = $this->lawyer('AHKP1');
        $this->makeCase([
            'nama' => self::TAG.' Tawaran',
            'nama_pegawai_yang_dapat_kes' => self::TAG.' Ali',
            'status_agihan' => StatusAgihan::DITAWARKAN, // numeric '1', as the spine writes
            'tarikh_penugasan_peguam_panel' => now()->toDateString(),
        ]);

        $this->actingAs($lawyer)->get(route('peguam.tawaran'))
            ->assertOk()
            ->assertSee(self::TAG.' Tawaran');
    }

    public function test_legacy_string_offer_also_reaches_lawyer(): void
    {
        $this->makePeguam(self::TAG.' Ali', 'AHKP1');
        $lawyer = $this->lawyer('AHKP1');
        $this->makeCase([
            'nama' => self::TAG.' Legacy',
            'nama_pegawai_yang_dapat_kes' => self::TAG.' Ali',
            'status_agihan' => 'Ditawarkan', // legacy string alias still resolved via bucketValues
            'tarikh_penugasan_peguam_panel' => now()->toDateString(),
        ]);

        $this->actingAs($lawyer)->get(route('peguam.tawaran'))
            ->assertOk()
            ->assertSee(self::TAG.' Legacy');
    }

    public function test_lawyer_accept_writes_numeric_diterima(): void
    {
        $this->makePeguam(self::TAG.' Ali', 'AHKP1');
        $lawyer = $this->lawyer('AHKP1');
        $kes = $this->makeCase([
            'nama_pegawai_yang_dapat_kes' => self::TAG.' Ali',
            'status_agihan' => StatusAgihan::DITAWARKAN,
        ]);

        $this->actingAs($lawyer)->post(route('peguam.terima', $kes))->assertRedirect();

        $this->assertSame(StatusAgihan::DITERIMA, $kes->fresh()->status_agihan);
    }

    public function test_lawyer_reject_returns_case_to_semula(): void
    {
        $this->makePeguam(self::TAG.' Ali', 'AHKP1');
        $lawyer = $this->lawyer('AHKP1');
        $kes = $this->makeCase([
            'nama_pegawai_yang_dapat_kes' => self::TAG.' Ali',
            'status_agihan' => StatusAgihan::DITAWARKAN,
        ]);

        $this->actingAs($lawyer)->post(route('peguam.tolak', $kes), ['alasan' => 'Konflik'])->assertRedirect();

        $fresh = $kes->fresh();
        $this->assertSame(StatusAgihan::PPUU_AGIH_SEMULA, $fresh->status_agihan);
        $this->assertNull($fresh->nama_pegawai_yang_dapat_kes);
    }

    // ---- BL-1: spine entry (single-step path retired) ----

    public function test_masuk_enters_unassigned_case_into_spine(): void
    {
        $kes = $this->makeCase(); // status_agihan NULL

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('agihan.masuk', $kes))
            ->assertRedirect(route('agihan.maklumat', $kes));

        $this->assertSame(StatusAgihan::BARU_PENGARAH, $kes->fresh()->status_agihan);
    }

    public function test_masuk_rejected_when_already_in_spine(): void
    {
        $kes = $this->makeCase(['status_agihan' => StatusAgihan::BARU_PENGARAH]);

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('agihan.masuk', $kes))
            ->assertStatus(422);
    }

    public function test_single_step_route_is_retired(): void
    {
        $this->assertFalse(Route::has('agihan.form'));
        $this->assertFalse(Route::has('agihan.store'));
    }

    // ---- BL-2: status-9 recovery (no more dead-end) ----

    public function test_rejected_case_appears_in_ditolak_bucket(): void
    {
        $this->makeCase(['nama' => self::TAG.' Ditolak', 'status_agihan' => StatusAgihan::DITOLAK_PENGARAH]);

        $this->actingAs($this->user('pengarah@test.local'))
            ->get(route('agihan.senarai', 'ditolak'))
            ->assertOk()
            ->assertSee(self::TAG.' Ditolak');
    }

    public function test_buka_semula_reopens_rejected_case(): void
    {
        $kes = $this->makeCase(['status_agihan' => StatusAgihan::DITOLAK_PENGARAH]);

        $this->actingAs($this->user('pengarah@test.local'))
            ->post(route('agihan.buka-semula', $kes), ['ulasan' => 'Semak semula'])
            ->assertRedirect();

        $this->assertSame(StatusAgihan::BARU_PENGARAH, $kes->fresh()->status_agihan);
    }

    public function test_batal_agihan_clears_rejected_case(): void
    {
        $kes = $this->makeCase([
            'status_agihan' => StatusAgihan::DITOLAK_PENGARAH,
            'nama_pegawai_yang_dapat_kes' => self::TAG.' Ali',
        ]);

        $this->actingAs($this->user('pengarah@test.local'))
            ->post(route('agihan.batal', $kes), ['sebab' => 'Tiada peguam sesuai'])
            ->assertRedirect();

        $fresh = $kes->fresh();
        $this->assertNull($fresh->status_agihan);
        $this->assertNull($fresh->nama_pegawai_yang_dapat_kes);
    }
}
