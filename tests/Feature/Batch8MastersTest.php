<?php

namespace Tests\Feature;

use App\Models\Bilik;
use App\Models\Cawangan;
use App\Models\RefJawatan;
use App\Models\RefKategoriKesKn;
use App\Models\RefKategoriKn;
use App\Models\RefSubkategoriKn;
use App\Models\User;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 8 — foundations/masters: cawangan (+bilik), KN category tree, jawatan.
 * CRUD + permission gating + cascade + seeder + CawanganScope-not-applied regression.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch8MastersTest extends TestCase
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
        RefKategoriKn::where('jenis_kategori', 'like', self::TAG.'%')->delete(); // cascades kes + sub
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();               // cascades bilik
        RefJawatan::where('nama', 'like', self::TAG.'%')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function admin(): User
    {
        return $this->user('admin@test.local');
    }

    // ---- Seeder ----

    public function test_seeder_populates_masters(): void
    {
        $this->assertGreaterThanOrEqual(23, Cawangan::where('jenis', 'JBG')->count());
        $this->assertGreaterThanOrEqual(10, RefJawatan::count());
        $this->assertDatabaseHas('ref_jawatan', ['nama' => 'PENGARAH NEGERI']);
        foreach (['SIVIL', 'SYARIAH', 'PENDAMPING JENAYAH', 'PENDAMPING GUAMAN'] as $k) {
            $this->assertDatabaseHas('ref_kategori_kn', ['jenis_kategori' => $k]);
        }
    }

    public function test_seeded_cawangan_maps_to_negeri(): void
    {
        $putrajaya = Cawangan::where('nama', 'JBG PUTRAJAYA')->firstOrFail();
        $this->assertNotNull($putrajaya->negeri);
        $this->assertSame('WILAYAH PERSEKUTUAN PUTRAJAYA', $putrajaya->negeri->nama);
    }

    // ---- Cawangan CRUD ----

    public function test_cawangan_store_and_audit(): void
    {
        $this->actingAs($this->admin())->post(route('cawangan.store'), [
            'jenis' => 'JKM', 'nama' => self::TAG.' JKM Ujian', 'negeri_id' => 1, 'status_aktif' => '1',
        ])->assertRedirect();

        $row = Cawangan::where('nama', self::TAG.' JKM Ujian')->firstOrFail();
        $this->assertSame('JKM', $row->jenis);
        $this->assertDatabaseHas('audit_trail', ['table_name' => 'cawangan', 'record_id' => $row->id, 'action_type' => 'INSERT']);
    }

    public function test_cawangan_nama_unique(): void
    {
        Cawangan::create(['jenis' => 'JBG', 'nama' => self::TAG.' Dup', 'status_aktif' => true]);

        $this->actingAs($this->admin())
            ->from(route('cawangan.create'))
            ->post(route('cawangan.store'), ['jenis' => 'JBG', 'nama' => self::TAG.' Dup'])
            ->assertSessionHasErrors('nama');
    }

    public function test_cawangan_update_and_destroy(): void
    {
        $c = Cawangan::create(['jenis' => 'JBG', 'nama' => self::TAG.' Edit', 'status_aktif' => true]);

        $this->actingAs($this->admin())->put(route('cawangan.update', $c), [
            'jenis' => 'PENJARA', 'nama' => self::TAG.' Edited',
        ])->assertRedirect();
        $this->assertSame('PENJARA', $c->fresh()->jenis);

        $this->actingAs($this->admin())->delete(route('cawangan.destroy', $c))->assertRedirect(route('cawangan.index'));
        $this->assertNull(Cawangan::find($c->id));
    }

    // ---- Bilik (nested) + cascade ----

    public function test_bilik_store_and_cascade_on_cawangan_delete(): void
    {
        $c = Cawangan::create(['jenis' => 'JBG', 'nama' => self::TAG.' Bilik Br', 'status_aktif' => true]);

        $this->actingAs($this->admin())->post(route('cawangan.bilik.store', $c), ['nama_bilik' => 'Bilik A'])->assertRedirect();
        $bilik = Bilik::where('cawangan_id', $c->id)->firstOrFail();
        $this->assertSame('Bilik A', $bilik->nama_bilik);

        $c->delete();
        $this->assertNull(Bilik::find($bilik->id)); // cascade
    }

    // ---- Jawatan ----

    public function test_jawatan_crud(): void
    {
        $this->actingAs($this->admin())->post(route('jawatan.store'), ['nama' => self::TAG.' Pegawai X'])->assertRedirect(route('jawatan.index'));
        $j = RefJawatan::where('nama', self::TAG.' Pegawai X')->firstOrFail();

        $this->actingAs($this->admin())->put(route('jawatan.update', $j), ['nama' => self::TAG.' Pegawai Y', 'aktif' => '1'])->assertRedirect();
        $this->assertSame(self::TAG.' Pegawai Y', $j->fresh()->nama);

        $this->actingAs($this->admin())->delete(route('jawatan.destroy', $j))->assertRedirect();
        $this->assertNull(RefJawatan::find($j->id));
    }

    // ---- Category tree + cascade ----

    public function test_kategori_tree_cascade(): void
    {
        $kat = RefKategoriKn::create(['jenis_kategori' => self::TAG.' Cat', 'aktif' => true]);
        $kes = RefKategoriKesKn::create(['kategori_id' => $kat->id, 'nama' => 'Kes 1', 'aktif' => true]);
        $sub = RefSubkategoriKn::create(['kategori_kes_id' => $kes->id, 'nama' => 'Sub 1', 'aktif' => true]);

        // drill pages render
        $this->actingAs($this->admin())->get(route('kategori-kn.kes', $kat))->assertOk()->assertSee('Kes 1');
        $this->actingAs($this->admin())->get(route('kategori-kn.sub', $kes))->assertOk()->assertSee('Sub 1');

        // delete top → cascade
        $this->actingAs($this->admin())->delete(route('kategori-kn.destroy', $kat))->assertRedirect(route('kategori-kn.index'));
        $this->assertNull(RefKategoriKesKn::find($kes->id));
        $this->assertNull(RefSubkategoriKn::find($sub->id));
    }

    public function test_kategori_store_via_route(): void
    {
        $this->actingAs($this->admin())->post(route('kategori-kn.store'), ['jenis_kategori' => self::TAG.' New Cat'])->assertRedirect();
        $kat = RefKategoriKn::where('jenis_kategori', self::TAG.' New Cat')->firstOrFail();

        $this->actingAs($this->admin())->post(route('kategori-kn.kes.store', $kat), ['nama' => 'Kes Baru'])->assertRedirect();
        $this->assertDatabaseHas('ref_kategori_kes_kn', ['kategori_id' => $kat->id, 'nama' => 'Kes Baru']);
    }

    // ---- Permission gating ----

    public function test_gate_allows_koordinator_blocks_others(): void
    {
        $this->actingAs($this->user('koordinator@test.local'))->get(route('cawangan.index'))->assertOk();
        $this->actingAs($this->user('pegawai@test.local'))->get(route('cawangan.index'))->assertStatus(302);
        $this->actingAs($this->user('peguam@test.local'))->get(route('jawatan.index'))->assertStatus(302);
    }

    // ---- CawanganScope regression: masters are NOT branch-scoped ----

    public function test_cawangan_master_not_branch_scoped(): void
    {
        // A branch-limited staff user (no cawangan.view-all) must still see ALL cawangan
        // rows — the master is global reference, not a scoped record.
        $pegawai = $this->user('pegawai@test.local');
        $original = $pegawai->cawangan;
        $pegawai->update(['cawangan' => 'JBG KEDAH']);

        try {
            $this->actingAs($pegawai);
            $this->assertGreaterThanOrEqual(23, Cawangan::count());
        } finally {
            $pegawai->update(['cawangan' => $original]);
        }
    }
}
