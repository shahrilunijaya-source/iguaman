<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use App\Support\KesilapanMatrix;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P1 — Kesilapan Nombor Fail report + CSV. Live mysql (iguaman_2in1);
 * rows tagged PHPUNIT, cleaned up.
 */
class KesilapanReportTest extends TestCase
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

    private function seedKesilapan(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'KESILAPAN UJIAN', 'nokp' => '770707071234',
            'no_fail' => 'JBG.PHPUNIT.ERR', 'kategori_kes' => 'Sivil',
            'tarikh_perakuan' => '2026-02-10', 'tarikh_tutup_fail' => '2026-03-01',
            'status' => KesilapanMatrix::MARKER_STATUS,
            'sebab_tutup_fail' => KesilapanMatrix::MARKER_SEBAB,
            'alasan_kesilapan_no_fail' => 'Nombor tersalah jana',
            'diterima' => '', 'created_at' => now(),
        ]);
    }

    private function seedNormal(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'NORMAL UJIAN', 'no_fail' => 'JBG.PHPUNIT.OK',
            'tarikh_perakuan' => '2026-02-10', 'status' => 'Aktif',
            'diterima' => '', 'created_at' => now(),
        ]);
    }

    public function test_index_renders_matrix(): void
    {
        $this->actingAs($this->user('admin@test.local'))
            ->get(route('statistik-kesilapan.index'))
            ->assertOk()
            ->assertSee('Kesilapan Penjanaan Nombor Fail')
            ->assertSee('JUMLAH KESELURUHAN')
            ->assertSee('JBG WP PUTRAJAYA');
    }

    public function test_csv_includes_only_kesilapan_records(): void
    {
        $this->seedKesilapan();
        $this->seedNormal();

        $res = $this->actingAs($this->user('admin@test.local'))
            ->get(route('statistik-kesilapan.csv'));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));

        $body = $res->streamedContent();
        $this->assertStringContainsString('LAPORAN KESILAPAN PENJANAAN NOMBOR FAIL', $body);
        $this->assertStringContainsString('KESILAPAN UJIAN', $body);
        $this->assertStringContainsString('770707071234', $body);
        $this->assertStringContainsString('Nombor tersalah jana', $body);
        $this->assertStringNotContainsString('NORMAL UJIAN', $body); // not a kesilapan record
    }

    public function test_lawyer_blocked(): void
    {
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('statistik-kesilapan.index'))
            ->assertStatus(302);
    }
}
