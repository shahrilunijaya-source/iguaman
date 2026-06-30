<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use App\Support\PengantaraanMatrix;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P1 (rk-pengantaraan slice 2) — penugasan assignment matrices. Live mysql.
 * Rows seeded on a real branch (JBG WP PUTRAJAYA, the matrix axis only counts
 * the fixed 23) and asserted by DELTA so live data does not make it flaky;
 * cleaned up by a unique no_fail prefix.
 */
class PengantaraanMatrixTest extends TestCase
{
    private const BRANCH = 'JBG WP PUTRAJAYA';
    private const TAG = 'JBG.PHPUNIT.PG';

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
        Form::withoutGlobalScope(CawanganScope::class)->where('no_fail', 'like', self::TAG.'%')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function seedRow(string $kategoriPengantaraan, string $tarikhPerakuan, string $suffix, string $status = 'Ya'): void
    {
        Form::create([
            'cawangan' => self::BRANCH, 'nama' => 'PG UJIAN '.$suffix, 'nokp' => '770707071234',
            'no_fail' => self::TAG.'.'.$suffix, 'kategori_kes' => 'Sivil',
            'pengantaraan_kategori_kes' => $kategoriPengantaraan, 'status_pengantaraan' => $status,
            'tarikh_perakuan' => $tarikhPerakuan, 'status' => 'Aktif', 'diterima' => '', 'created_at' => now(),
        ]);
    }

    public function test_kategori_matrix_counts_seeded_rows_by_delta(): void
    {
        $before = PengantaraanMatrix::kategori(2026)['matrix'][self::BRANCH];

        $this->seedRow('sivil', '2026-03-10', 'S1');
        $this->seedRow('syariah', '2026-05-10', 'Y1');
        $this->seedRow('sivil', '2026-04-10', 'TIDAK', 'Tidak');       // wrong status → excluded
        // Kesilapan-Menjana → excluded
        Form::create([
            'cawangan' => self::BRANCH, 'nama' => 'PG KESILAPAN', 'no_fail' => self::TAG.'.ERR',
            'pengantaraan_kategori_kes' => 'sivil', 'status_pengantaraan' => 'Ya', 'status' => 'Fail Tutup',
            'sebab_tutup_fail' => 'Kesilapan Menjana Nombor Fail', 'tarikh_perakuan' => '2026-06-10',
            'diterima' => '', 'created_at' => now(),
        ]);

        $after = PengantaraanMatrix::kategori(2026)['matrix'][self::BRANCH];

        $this->assertSame($before['sivil'] + 1, $after['sivil']);     // only the 'Ya' sivil
        $this->assertSame($before['syariah'] + 1, $after['syariah']);
        $this->assertSame($before['jumlah'] + 2, $after['jumlah']);   // Tidak + kesilapan excluded
    }

    public function test_bulanan_matrix_buckets_by_perakuan_month_and_kategori_filter(): void
    {
        $beforeAll = PengantaraanMatrix::bulanan(2026)['matrix'][self::BRANCH];

        $this->seedRow('sivil', '2026-03-10', 'S1');
        $this->seedRow('syariah', '2026-05-10', 'Y1');

        $afterAll = PengantaraanMatrix::bulanan(2026)['matrix'][self::BRANCH];
        $this->assertSame($beforeAll[3] + 1, $afterAll[3]);
        $this->assertSame($beforeAll[5] + 1, $afterAll[5]);
        $this->assertSame($beforeAll['jumlah'] + 2, $afterAll['jumlah']);

        // kategori=Sivil narrows to the sivil row only (March 2026).
        $sivilOnly = PengantaraanMatrix::bulanan(2026, 'Sivil')['matrix'][self::BRANCH];
        $this->assertGreaterThanOrEqual(1, $sivilOnly[3]);
    }

    public function test_pencapaian_funnel_counts_by_delta(): void
    {
        $before = PengantaraanMatrix::pencapaian(2026)['matrix'][self::BRANCH];

        // One row that walks the full funnel: perakuan → penugasan → sidang → selesai.
        Form::create([
            'cawangan' => self::BRANCH, 'nama' => 'PG FUNNEL', 'no_fail' => self::TAG.'.FUN',
            'kategori_kes' => 'Sivil', 'pengantaraan_kategori_kes' => 'sivil', 'status_pengantaraan' => 'Ya',
            'setuju_pengantara' => 'Ya', 'cara_selesai' => 'Selesai dengan Perjanjian Penyelesaian',
            'tarikh_perakuan' => '2026-03-10', 'status' => 'Aktif', 'diterima' => '', 'created_at' => now(),
        ]);
        // Kesilapan-Menjana → excluded from the gate (perakuan must NOT count it).
        Form::create([
            'cawangan' => self::BRANCH, 'nama' => 'PG KESILAPAN', 'no_fail' => self::TAG.'.ERR2',
            'status' => 'Fail Tutup', 'sebab_tutup_fail' => 'Kesilapan Menjana Nombor Fail',
            'tarikh_perakuan' => '2026-04-10', 'diterima' => '', 'created_at' => now(),
        ]);

        $after = PengantaraanMatrix::pencapaian(2026)['matrix'][self::BRANCH];

        $this->assertSame($before['perakuan'] + 1, $after['perakuan']);     // kesilapan excluded
        $this->assertSame($before['penugasan'] + 1, $after['penugasan']);
        $this->assertSame($before['rujuk_minta'] + 1, $after['rujuk_minta']);
        $this->assertSame($before['selesai'] + 1, $after['selesai']);
    }

    public function test_routes_render_for_hq(): void
    {
        $admin = $this->user('admin@test.local');

        $this->actingAs($admin)->get(route('statistik-pengantaraan.index'))
            ->assertOk()->assertSee('Statistik Penugasan Pengantaraan');
        $this->actingAs($admin)->get(route('statistik-pengantaraan.kategori'))
            ->assertOk()->assertSee('JBG WP PUTRAJAYA')->assertSee('JUMLAH');
        $this->actingAs($admin)->get(route('statistik-pengantaraan.bulanan'))
            ->assertOk()->assertSee('JUMLAH KESELURUHAN');
        $this->actingAs($admin)->get(route('statistik-pengantaraan.pencapaian'))
            ->assertOk()->assertSee('PERATUS (%)')->assertSee('JUMLAH KESELURUHAN');
    }

    public function test_lawyer_blocked(): void
    {
        $peguam = $this->user('peguam@test.local');

        $this->actingAs($peguam)->get(route('statistik-pengantaraan.index'))->assertStatus(302);
        $this->actingAs($peguam)->get(route('statistik-pengantaraan.kategori'))->assertStatus(302);
    }
}
