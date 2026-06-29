<?php

namespace Tests\Feature;

use App\Models\RefCuti;
use App\Models\User;
use App\Support\CutiNegeri;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * EPIC G — Cuti Umum CRUD smoke + idnegeri encoding + gating.
 * Live mysql (iguaman_2in1) per repo convention; rows tagged PHPUNIT, cleaned up.
 */
class CutiCrudTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
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
        RefCuti::where('nama_cuti', 'like', self::TAG.'%')->delete();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@test.local')->firstOrFail();
    }

    private function makeCuti(array $negeri = [1, 4, 16]): RefCuti
    {
        return RefCuti::create([
            'nama_cuti' => self::TAG.' Hari Raya',
            'tarikh_mula' => '2026-04-01',
            'tarikh_tamat' => '2026-04-02',
            'idnegeri' => CutiNegeri::encode($negeri),
            'created' => '2026-06-30',
        ]);
    }

    public function test_store_encodes_idnegeri(): void
    {
        $this->actingAs($this->admin())->post(route('cuti.store'), [
            'nama_cuti' => self::TAG.' Tahun Baru',
            'tarikh_mula' => '2026-01-01',
            'tarikh_tamat' => '2026-01-01',
            'negeri' => [1, 4, 16],
        ])->assertRedirect(route('cuti.index'));

        $row = RefCuti::where('nama_cuti', self::TAG.' Tahun Baru')->firstOrFail();
        $this->assertSame(CutiNegeri::encode([1, 4, 16]), $row->idnegeri);
        $this->assertSame([1, 4, 16], CutiNegeri::decode($row->idnegeri));
    }

    public function test_store_requires_at_least_one_state(): void
    {
        $this->actingAs($this->admin())
            ->from(route('cuti.create'))
            ->post(route('cuti.store'), [
                'nama_cuti' => self::TAG.' Tiada Negeri',
                'tarikh_mula' => '2026-01-01',
                'tarikh_tamat' => '2026-01-01',
            ])
            ->assertRedirect(route('cuti.create'))
            ->assertSessionHasErrors('negeri');

        $this->assertSame(0, RefCuti::where('nama_cuti', self::TAG.' Tiada Negeri')->count());
    }

    public function test_edit_loads_and_update_reencodes(): void
    {
        $cuti = $this->makeCuti([1, 4, 16]);

        $this->actingAs($this->admin())->get(route('cuti.edit', $cuti))
            ->assertOk()->assertSee('Hari Raya');

        $this->actingAs($this->admin())->put(route('cuti.update', $cuti), [
            'nama_cuti' => self::TAG.' Hari Raya',
            'tarikh_mula' => '2026-04-01',
            'tarikh_tamat' => '2026-04-02',
            'negeri' => [2, 3],
        ])->assertRedirect(route('cuti.index'));

        $this->assertSame(CutiNegeri::encode([2, 3]), $cuti->fresh()->idnegeri);
    }

    public function test_destroy_deletes(): void
    {
        $cuti = $this->makeCuti();

        $this->actingAs($this->admin())->delete(route('cuti.destroy', $cuti))
            ->assertRedirect(route('cuti.index'));

        $this->assertNull(RefCuti::find($cuti->id_cuti));
    }

    public function test_lawyer_blocked(): void
    {
        $peguam = User::where('email', 'peguam@test.local')->firstOrFail();
        $this->actingAs($peguam)->get(route('cuti.index'))->assertStatus(302);
    }
}
