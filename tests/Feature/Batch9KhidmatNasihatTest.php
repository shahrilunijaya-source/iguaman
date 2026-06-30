<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\User;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 9 — Khidmat Nasihat foundation slice: schema/model + list/show + gating.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch9KhidmatNasihatTest extends TestCase
{
    private const TAG = 'PHPUNIT';

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
        KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makeKhidmat(array $attrs = []): KhidmatNasihat
    {
        return KhidmatNasihat::create(array_merge([
            'no_permohonan' => self::TAG.'-KN-'.uniqid(),
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Mangsa',
        ], $attrs));
    }

    // ---- Schema + model ----

    public function test_create_persists_with_casts_and_default_status(): void
    {
        $row = $this->makeKhidmat([
            'perakuan' => true,
            'status_bayaran' => 1,
            'is_percuma' => 0,
            'jumlah_bayaran' => '15.50',
            'tarikh_lahir_mangsa' => '1990-05-20',
        ]);

        $fresh = KhidmatNasihat::findOrFail($row->id);
        $this->assertSame(KhidmatNasihat::STATUS_DRAF, $fresh->status_kn); // enum default
        $this->assertTrue($fresh->perakuan);
        $this->assertTrue($fresh->status_bayaran);
        $this->assertFalse($fresh->is_percuma);
        $this->assertSame('15.50', $fresh->jumlah_bayaran);
        $this->assertSame('1990-05-20', $fresh->tarikh_lahir_mangsa->format('Y-m-d'));
    }

    public function test_relations_resolve(): void
    {
        $cawangan = Cawangan::where('nama', 'JBG PUTRAJAYA')->firstOrFail();
        $kategori = RefKategoriKn::firstOrFail();

        $row = $this->makeKhidmat([
            'cawangan_id' => $cawangan->id,
            'id_kategori' => $kategori->id,
        ]);

        $this->assertSame('JBG PUTRAJAYA', $row->cawangan->nama);
        $this->assertSame($kategori->id, $row->kategori->id);
    }

    // ---- Index (list + filters) ----

    public function test_index_renders_and_lists_row(): void
    {
        $row = $this->makeKhidmat();

        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('khidmat.index'))
            ->assertOk()
            ->assertSee($row->no_permohonan);
    }

    public function test_index_filters_by_status_and_query(): void
    {
        $draf = $this->makeKhidmat(['nama_mangsa' => self::TAG.' Ali']);
        $selesai = $this->makeKhidmat(['nama_mangsa' => self::TAG.' Bakar', 'status_kn' => KhidmatNasihat::STATUS_SELESAI]);

        $pegawai = $this->user('pegawai@test.local');

        // status filter: only SELESAI
        $this->actingAs($pegawai)
            ->get(route('khidmat.index', ['status_kn' => KhidmatNasihat::STATUS_SELESAI]))
            ->assertOk()
            ->assertSee($selesai->no_permohonan)
            ->assertDontSee($draf->no_permohonan);

        // q filter: matches nama_mangsa
        $this->actingAs($pegawai)
            ->get(route('khidmat.index', ['q' => 'Ali']))
            ->assertOk()
            ->assertSee($draf->no_permohonan)
            ->assertDontSee($selesai->no_permohonan);
    }

    // ---- Show ----

    public function test_show_renders_detail(): void
    {
        $row = $this->makeKhidmat(['nama_mangsa' => self::TAG.' Detail']);

        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('khidmat.show', $row))
            ->assertOk()
            ->assertSee(self::TAG.' Detail')
            ->assertSee($row->no_permohonan);
    }

    // ---- Permission gating ----

    public function test_gate_allows_pembantu_tadbir_blocks_peguam(): void
    {
        $this->actingAs($this->user('pembantu@test.local'))->get(route('khidmat.index'))->assertOk();
        $this->actingAs($this->user('peguam@test.local'))->get(route('khidmat.index'))->assertStatus(302);
    }
}
