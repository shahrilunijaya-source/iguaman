<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Models\User;
use App\Support\PerakuanService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * W9 + W14 — Pembelaan Awam register on the litigation spine + interim/muktamad legal-aid
 * certificate. Live mysql per repo convention; TAG rows self-clean. Covers intake tagging +
 * PBA file-number + civil-list exclusion, the certificate guard (segera) + interim→muktamad
 * lifecycle, route permission gating, and the ledger-on-close seed (idempotent).
 */
class PembelaanAwamTest extends TestCase
{
    private const TAG = 'PHPUNITPB';

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
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = Form::where('cawangan', self::TAG)->pluck('id');
        LejarTuntutanBayaran::whereIn('id_kes', $ids)->delete();
        Form::whereIn('id', $ids)->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makePembelaan(array $attrs = []): Form
    {
        return Form::create(array_merge([
            'nama' => self::TAG.' Tertuduh',
            'cawangan' => self::TAG,
            'jenis_kes' => '085',
            'is_pembelaan_awam' => true,
            'diterima' => '',
            'created_at' => now(),
        ], $attrs));
    }

    // ---- W9 intake + tagging ----

    public function test_officer_registers_pembelaan_case_tagged_and_excluded_from_civil_list(): void
    {
        $this->actingAs($this->user('koordinator@test.local'))
            ->post(route('pembelaan.store'), [
                'nama' => self::TAG.' Ali',
                'cawangan' => self::TAG,
                'jenis_kes' => '085',
                'no_pertuduhan' => 'PT-1/2026',
                'is_segera' => '1',
            ])->assertRedirect();

        $kes = Form::where('cawangan', self::TAG)->where('nama', self::TAG.' Ali')->firstOrFail();

        $this->assertTrue((bool) $kes->is_pembelaan_awam);
        $this->assertTrue((bool) $kes->is_segera);
        $this->assertStringStartsWith('PBA.', (string) $kes->no_fail);

        // Appears in the pembelaan register, excluded from the civil litigasi list.
        $this->assertTrue(Form::query()->pembelaan()->whereKey($kes->id)->exists());
        $this->assertFalse(Form::query()->litigasi()->whereKey($kes->id)->exists());
    }

    // ---- W14 certificate lifecycle ----

    public function test_interim_certificate_requires_segera(): void
    {
        $kes = $this->makePembelaan(['is_segera' => false]);

        $this->expectException(HttpException::class);
        app(PerakuanService::class)->keluarInterim($kes, $this->user('koordinator@test.local'));
    }

    public function test_certificate_interim_then_muktamad(): void
    {
        $kes = $this->makePembelaan(['is_segera' => true]);
        $svc = app(PerakuanService::class);
        $actor = $this->user('koordinator@test.local');

        $svc->keluarInterim($kes, $actor);
        $kes->refresh();
        $this->assertSame(PerakuanService::STATUS_INTERIM, $kes->status_perakuan);
        $this->assertStringStartsWith('PRK-', (string) $kes->no_perakuan);
        $this->assertNotNull($kes->tarikh_perakuan_interim);

        $svc->muktamadkan($kes, $actor);
        $kes->refresh();
        $this->assertSame(PerakuanService::STATUS_MUKTAMAD, $kes->status_perakuan);
        $this->assertNotNull($kes->tarikh_perakuan_muktamad);
    }

    public function test_certificate_issue_route_is_permission_gated(): void
    {
        $kes = $this->makePembelaan(['is_segera' => true]);

        // pembantu_tadbir has pembelaan.view but NOT kes.perakuan — redirected, no change.
        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('pembelaan.perakuan.interim', $kes))->assertRedirect();
        $this->assertNull($kes->fresh()->status_perakuan);

        // koordinator holds kes.perakuan — interim is issued.
        $this->actingAs($this->user('koordinator@test.local'))
            ->post(route('pembelaan.perakuan.interim', $kes))->assertRedirect();
        $this->assertSame(PerakuanService::STATUS_INTERIM, $kes->fresh()->status_perakuan);
    }

    // ---- W9 ledger-on-close ----

    public function test_closing_pembelaan_case_seeds_ledger_row_idempotently(): void
    {
        $kes = $this->makePembelaan();

        $kp = $this->user('kp@test.local'); // ketua_pengarah holds kes.keputusan

        $this->actingAs($kp)->post(route('kes.tutupfail', $kes), ['sebab_tutup_fail' => 'Selesai'])->assertRedirect();

        $rows = LejarTuntutanBayaran::where('id_kes', $kes->id)
            ->where('sumber', LejarTuntutanBayaran::SUMBER_PEMBELAAN_AWAM);
        $this->assertSame(1, (clone $rows)->count());

        // PROC-12: re-closing a closed file is blocked (409), which guarantees the ledger row
        // is never re-seeded. (seedPembelaanLedger is also independently idempotent.)
        $this->actingAs($kp)->post(route('kes.tutupfail', $kes), ['sebab_tutup_fail' => 'Selesai'])->assertStatus(409);
        $this->assertSame(1, (clone $rows)->count());
    }
}
