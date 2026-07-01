<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\LaporanKes;
use App\Models\SejarahSidang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 3c — pengantaraan + mahkamah actions over the real iguaman_2in1 DB.
 * Self-cleaning (rows tagged cawangan=PHPUNIT). Children deleted before parent (FK restrict).
 */
class Phase3cTest extends TestCase
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
            SejarahSidang::whereIn('id_kes', $ids)->delete();
            LaporanKes::whereIn('id_kes', $ids->map(fn ($i) => (string) $i))->delete();
            Form::whereIn('id', $ids)->delete();
        }
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    private function staff(): User
    {
        $user = User::create([
            'name' => 'PHPUnit Staff', 'email' => 'staff@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => 'pegawai', 'is_active' => true,
        ]);
        $user->syncRoles([$user->role]);

        return $user;
    }

    private function makeCase(): Form
    {
        return Form::create(['nama' => 'Kes Ujian', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now()]);
    }

    public function test_pengantaraan_update(): void
    {
        $kes = $this->makeCase();

        $this->actingAs($this->staff())
            ->put(route('pengantaraan.update', $kes), [
                'status_pengantaraan' => 'Selesai',
                'cara_selesai' => 'Setuju',
            ])
            ->assertRedirect(route('kes.show', $kes));

        $kes->refresh();
        $this->assertSame('Selesai', $kes->status_pengantaraan);
        $this->assertSame('Setuju', $kes->cara_selesai);
    }

    public function test_tangguh_sidang_logs_history_and_moves_date(): void
    {
        $kes = $this->makeCase();

        $this->actingAs($this->staff())
            ->post(route('sidang.tangguh', $kes), [
                'tarikh_sidang' => '2026-07-15',
                'alasan_tangguh' => 'Peguam tidak hadir',
            ])
            ->assertRedirect(route('pengantaraan.edit', $kes));

        $this->assertDatabaseHas('sejarah_sidang', [
            'id_kes' => $kes->id,
            'alasan_tangguh' => 'Peguam tidak hadir',
        ]);
        $this->assertSame('Tangguh', $kes->fresh()->status_sidang);
    }

    public function test_tangguh_sidang_requires_date(): void
    {
        $kes = $this->makeCase();

        $this->actingAs($this->staff())
            ->post(route('sidang.tangguh', $kes), ['alasan_tangguh' => 'x'])
            ->assertSessionHasErrors('tarikh_sidang');

        $this->assertSame(0, SejarahSidang::where('id_kes', $kes->id)->count());
    }

    public function test_mahkamah_update(): void
    {
        $kes = $this->makeCase();

        $this->actingAs($this->staff())
            ->put(route('mahkamah.update', $kes), [
                'nama_mahkamah' => 'Mahkamah Sesyen Shah Alam',
                'no_mahkamah' => 'BA-83-1/2026',
            ])
            ->assertRedirect(route('kes.show', $kes));

        $this->assertSame('Mahkamah Sesyen Shah Alam', $kes->fresh()->nama_mahkamah);
    }

    public function test_laporan_store_and_destroy(): void
    {
        $kes = $this->makeCase();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('laporan.store', $kes), [
                'no_kes' => 'KES-1', 'status_kes' => 'Aktif', 'isu' => 'Hak penjagaan',
            ])
            ->assertRedirect(route('mahkamah.edit', $kes));

        $this->assertDatabaseHas('laporan_kes', ['id_kes' => (string) $kes->id, 'no_kes' => 'KES-1']);

        $lap = LaporanKes::where('id_kes', (string) $kes->id)->first();
        $this->actingAs($staff)
            ->delete(route('laporan.destroy', [$kes, $lap]))
            ->assertRedirect(route('mahkamah.edit', $kes));

        $this->assertDatabaseMissing('laporan_kes', ['id' => $lap->id]);
    }
}
