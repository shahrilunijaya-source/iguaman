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
 * Batch 10 slice 3 — Cuti Negeri CRUD (state-specific public holidays).
 *
 * Reuses ref_cuti + CutiNegeri bitmask + the selenggara.cuti permission; the
 * Negeri surface differs from Cuti Umum only in that it lists/edits the
 * non-nationwide rows (a subset of the 16 states). Round-trips the multi-state
 * selector through the idnegeri bitmask. Live mysql per repo convention; rows
 * tagged PHPUNIT and cleaned up.
 */
class CutiNegeriCrudTest extends TestCase
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

    private function makeNegeri(array $negeri = [3, 11]): RefCuti
    {
        return RefCuti::create([
            'nama_cuti' => self::TAG.' Hari Keputeraan',
            'tarikh_mula' => '2026-05-01',
            'tarikh_tamat' => '2026-05-01',
            'idnegeri' => CutiNegeri::encode($negeri),
            'created' => '2026-06-30',
        ]);
    }

    public function test_store_round_trips_multi_state_bitmask(): void
    {
        $this->actingAs($this->admin())->post(route('cuti-negeri.store'), [
            'nama_cuti' => self::TAG.' Cuti Negeri Johor Kelantan',
            'tarikh_mula' => '2026-09-01',
            'tarikh_tamat' => '2026-09-02',
            'negeri' => [1, 3],
        ])->assertRedirect(route('cuti-negeri.index'));

        $row = RefCuti::where('nama_cuti', self::TAG.' Cuti Negeri Johor Kelantan')->firstOrFail();
        $this->assertSame(CutiNegeri::encode([1, 3]), $row->idnegeri);
        $this->assertSame([1, 3], CutiNegeri::decode($row->idnegeri));
        // A 2-state holiday must NOT read back as nationwide.
        $this->assertFalse(CutiNegeri::isAll($row->idnegeri));
    }

    public function test_store_requires_at_least_one_state(): void
    {
        $this->actingAs($this->admin())
            ->from(route('cuti-negeri.create'))
            ->post(route('cuti-negeri.store'), [
                'nama_cuti' => self::TAG.' Tiada Negeri',
                'tarikh_mula' => '2026-09-01',
                'tarikh_tamat' => '2026-09-01',
            ])
            ->assertRedirect(route('cuti-negeri.create'))
            ->assertSessionHasErrors('negeri');

        $this->assertSame(0, RefCuti::where('nama_cuti', self::TAG.' Tiada Negeri')->count());
    }

    public function test_index_lists_negeri_specific_and_hides_nationwide(): void
    {
        $negeri = $this->makeNegeri([3, 11]);                       // Kelantan + Terengganu
        $nationwide = RefCuti::create([                             // all 16 states = Cuti Umum
            'nama_cuti' => self::TAG.' Hari Kebangsaan',
            'tarikh_mula' => '2026-08-31',
            'tarikh_tamat' => '2026-08-31',
            'idnegeri' => CutiNegeri::encode(range(1, 16)),
            'created' => '2026-06-30',
        ]);

        $this->actingAs($this->admin())->get(route('cuti-negeri.index'))
            ->assertOk()
            ->assertSee('Hari Keputeraan')
            ->assertDontSee('Hari Kebangsaan');
    }

    public function test_edit_loads_and_update_reencodes(): void
    {
        $cuti = $this->makeNegeri([3, 11]);

        $this->actingAs($this->admin())->get(route('cuti-negeri.edit', $cuti))
            ->assertOk()->assertSee('Hari Keputeraan');

        $this->actingAs($this->admin())->put(route('cuti-negeri.update', $cuti), [
            'nama_cuti' => self::TAG.' Hari Keputeraan',
            'tarikh_mula' => '2026-05-01',
            'tarikh_tamat' => '2026-05-01',
            'negeri' => [5, 8, 10],
        ])->assertRedirect(route('cuti-negeri.index'));

        $this->assertSame(CutiNegeri::encode([5, 8, 10]), $cuti->fresh()->idnegeri);
    }

    public function test_destroy_deletes(): void
    {
        $cuti = $this->makeNegeri();

        $this->actingAs($this->admin())->delete(route('cuti-negeri.destroy', $cuti))
            ->assertRedirect(route('cuti-negeri.index'));

        $this->assertNull(RefCuti::find($cuti->id_cuti));
    }

    public function test_lawyer_blocked(): void
    {
        $peguam = User::where('email', 'peguam@test.local')->firstOrFail();
        $this->actingAs($peguam)->get(route('cuti-negeri.index'))->assertStatus(302);
    }
}
