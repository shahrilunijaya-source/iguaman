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
 * P1 — SLA breach "senarai" CSV (perakuan >40 hari). Live mysql (iguaman_2in1);
 * rows tagged PHPUNIT, cleaned up. Proves: breach-only, kesilapan-excluded,
 * day-count rendered, statistik.view gating.
 */
class SlaListReportTest extends TestCase
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

    /** Perakuan breach: pemakluman 73 days after permohonan (> 40). */
    private function seedBreach(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'PERAKUAN LEWAT', 'nokp' => '770707071234',
            'no_fail' => 'JBG.PHPUNIT.LATE', 'kategori_kes' => 'Sivil',
            'tarikh_permohonan' => '2026-01-01', 'tarikh_pemakluman' => '2026-03-15',
            'kelulusan' => 'Tidak', 'sumbangan' => 'Tiada',
            'diterima' => '', 'created_at' => now(),
        ]);
    }

    /** Same shape but 19 days — within the 40-day SLA, must be excluded. */
    private function seedOnTime(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'PERAKUAN AWAL', 'nokp' => '880808081234',
            'no_fail' => 'JBG.PHPUNIT.OK', 'kategori_kes' => 'Sivil',
            'tarikh_permohonan' => '2026-01-01', 'tarikh_pemakluman' => '2026-01-20',
            'kelulusan' => 'Tidak', 'sumbangan' => 'Tiada',
            'diterima' => '', 'created_at' => now(),
        ]);
    }

    /** Breach dates but closed for a number-generation error — universally excluded. */
    private function seedKesilapanBreach(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'PERAKUAN KESILAPAN', 'nokp' => '990909091234',
            'no_fail' => 'JBG.PHPUNIT.ERR', 'kategori_kes' => 'Sivil',
            'tarikh_permohonan' => '2026-01-01', 'tarikh_pemakluman' => '2026-03-15',
            'kelulusan' => 'Tidak', 'sumbangan' => 'Tiada',
            'sebab_tutup_fail' => 'Kesilapan Menjana Nombor Fail',
            'diterima' => '', 'created_at' => now(),
        ]);
    }

    public function test_senarai_lists_only_breaches_with_day_count(): void
    {
        $this->seedBreach();
        $this->seedOnTime();
        $this->seedKesilapanBreach();

        $res = $this->actingAs($this->user('admin@test.local'))
            ->get(route('statistik-sla.senarai', 'perakuan'));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));

        $body = $res->streamedContent();
        $this->assertStringContainsString('SENARAI FAIL PERAKUAN MELEBIHI 40 HARI', $body);
        $this->assertStringContainsString('PERAKUAN LEWAT', $body);
        $this->assertStringContainsString('73 hari', $body);   // 2026-01-01 → 2026-03-15
        $this->assertStringContainsString('770707071234', $body); // NoKP digits (Excel text formula, CSV-escaped)
        $this->assertStringNotContainsString('PERAKUAN AWAL', $body);  // within SLA
        $this->assertStringNotContainsString('PERAKUAN KESILAPAN', $body); // kesilapan-excluded
    }

    public function test_lawyer_blocked(): void
    {
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('statistik-sla.senarai', 'perakuan'))
            ->assertStatus(302);
    }
}
