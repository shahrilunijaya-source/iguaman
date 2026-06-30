<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * EPIC F — SLA matrices + wide exports smoke + gating.
 *
 * Runs against the LIVE mysql db (iguaman_2in1) per repo convention (phpunit.xml
 * forces sqlite but the legacy baseline migration is MySQL-specific). RBAC seeds
 * are idempotent; case rows are tagged cawangan=PHPUNIT and cleaned up.
 */
class EpicFStatistikExportTest extends TestCase
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
        Form::where('cawangan', self::TAG)->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function seedCase(): Form
    {
        return Form::create([
            'cawangan' => self::TAG,
            'nama' => 'UJIAN EPIC F',
            'nokp' => '880808081234',
            'no_fail' => 'JBG.PHPUNIT.01/2026',
            'kategori_kes' => 'Sivil',
            'tarikh_permohonan' => '2026-01-10',
            'tarikh_perakuan' => '2026-02-10',
            'status' => 'Aktif',
            'diterima' => '',
            'created_at' => now(),
        ]);
    }

    public function test_sla_index_and_show_render_for_hq(): void
    {
        $admin = $this->user('admin@test.local');

        $this->actingAs($admin)->get(route('statistik-sla.index'))
            ->assertOk()->assertSee('Statistik SLA');

        $this->actingAs($admin)->get(route('statistik-sla.show', 'perakuan'))
            ->assertOk()
            ->assertSee('JBG WP PUTRAJAYA')
            ->assertSee('JUMLAH KESELURUHAN');
    }

    public function test_sla_show_accepts_year_and_month_filter(): void
    {
        $this->actingAs($this->user('admin@test.local'))
            ->get(route('statistik-sla.show', ['key' => 'perakuan', 'tahun' => 2026, 'bulan' => 6]))
            ->assertOk()
            ->assertSee('Jun 2026')
            ->assertSee('JUMLAH KESELURUHAN');
    }

    public function test_unknown_sla_key_404(): void
    {
        $this->actingAs($this->user('admin@test.local'))
            ->get(route('statistik-sla.show', 'tiada'))
            ->assertNotFound();
    }

    /** @return array<string,array{0:string}> */
    public static function wideTypes(): array
    {
        return [
            'permohonan' => ['permohonan'],
            'pendaftaran-fail' => ['pendaftaran-fail'],
            'status-fail' => ['status-fail'],
        ];
    }

    #[DataProvider('wideTypes')]
    public function test_wide_export_streams_csv_with_envelope_and_nokp(string $type): void
    {
        $this->seedCase();

        $res = $this->actingAs($this->user('admin@test.local'))
            ->get(route('laporan.penuh', $type));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));

        $body = $res->streamedContent();
        $this->assertStringContainsString('LAPORAN', $body);     // envelope title
        $this->assertStringContainsString('UJIAN EPIC F', $body); // our tagged row
        $this->assertStringContainsString('880808081234', $body); // NoKP digits preserved as text
    }

    public function test_lawyer_blocked_from_epic_f_routes(): void
    {
        $peguam = $this->user('peguam@test.local');

        $this->actingAs($peguam)->get(route('statistik-sla.index'))->assertStatus(302);
        $this->actingAs($peguam)->get(route('laporan.penuh', 'permohonan'))->assertStatus(302);
    }
}
