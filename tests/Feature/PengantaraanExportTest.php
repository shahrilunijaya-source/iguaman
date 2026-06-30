<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P1 (rk-pengantaraan slice 1) — wide CSV exports for Penugasan ('Ya') and
 * Tidak Dirujuk ('Tidak'). Live mysql (iguaman_2in1); rows tagged PHPUNIT.
 */
class PengantaraanExportTest extends TestCase
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
        Form::withoutGlobalScope(CawanganScope::class)->where('cawangan', self::TAG)->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function seedYa(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'PENUGASAN YA', 'nokp' => '770707071234',
            'no_fail' => 'JBG.PHPUNIT.YA', 'kategori_kes' => 'Sivil', 'tarikh_perakuan' => '2026-02-10',
            'tarikh_penugasan' => '2026-03-01', 'nama_pegawai' => 'PEGAWAI UJIAN',
            'status_pengantaraan' => 'Ya', 'status' => 'Aktif', 'diterima' => '', 'created_at' => now(),
        ]);
    }

    private function seedTidak(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'TIDAK DIRUJUK ABC', 'nokp' => '880808081234',
            'no_fail' => 'JBG.PHPUNIT.TIDAK', 'kategori_kes' => 'Syariah', 'tarikh_perakuan' => '2026-02-12',
            'status_pengantaraan' => 'Tidak', 'status' => 'Aktif', 'diterima' => '', 'created_at' => now(),
        ]);
    }

    public function test_penugasan_export_lists_only_ya_rows(): void
    {
        $this->seedYa();
        $this->seedTidak();

        $res = $this->actingAs($this->user('admin@test.local'))
            ->get(route('laporan.penuh', 'penugasan-pengantaraan'));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));

        $body = $res->streamedContent();
        $this->assertStringContainsString('LAPORAN PENUGASAN PENGANTARAAN', $body);
        $this->assertStringContainsString('PENUGASAN YA', $body);
        $this->assertStringContainsString('PEGAWAI UJIAN', $body);
        $this->assertStringContainsString('770707071234', $body);
        $this->assertStringContainsString('NAMA PEGAWAI PENGANTARA', $body); // wide header present
        $this->assertStringNotContainsString('TIDAK DIRUJUK ABC', $body);    // 'Tidak' excluded
    }

    public function test_tidak_dirujuk_export_lists_only_tidak_rows(): void
    {
        $this->seedYa();
        $this->seedTidak();

        $res = $this->actingAs($this->user('admin@test.local'))
            ->get(route('laporan.penuh', 'tidak-dirujuk'));

        $res->assertOk();
        $body = $res->streamedContent();
        $this->assertStringContainsString('LAPORAN PENGANTARAAN TIDAK DIRUJUK', $body);
        $this->assertStringContainsString('TIDAK DIRUJUK ABC', $body);
        $this->assertStringContainsString('ALASAN TIDAK DIRUJUK PENGANTARAAN', $body);
        $this->assertStringNotContainsString('PENUGASAN YA', $body); // 'Ya' excluded
    }

    public function test_lawyer_blocked(): void
    {
        $peguam = $this->user('peguam@test.local');

        $this->actingAs($peguam)->get(route('laporan.penuh', 'penugasan-pengantaraan'))->assertStatus(302);
        $this->actingAs($peguam)->get(route('laporan.penuh', 'tidak-dirujuk'))->assertStatus(302);
    }
}
