<?php

namespace Tests\Feature;

use App\Models\KhidmatNasihat;
use App\Models\LejarTuntutanBayaran;
use App\Models\UploadedFile as Lampiran;
use App\Models\User;
use App\Support\LejarTuntutanService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W2 — manual iPayment: record a counter payment of a KN intake fee into the KN
 * (status_bayaran + resit document) and the central ledger (DIBAYAR + receipt).
 */
class Phase4W2Test extends TestCase
{
    private const TAG = 'PHPUNITW2';

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
        $ids = KhidmatNasihat::where('nama_mangsa', 'like', self::TAG.'%')->pluck('id');
        LejarTuntutanBayaran::whereIn('id_khidmat_nasihat', $ids)->delete();
        Lampiran::whereIn('id_khidmat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makePaidKn(array $attrs = []): KhidmatNasihat
    {
        return KhidmatNasihat::create(array_merge([
            'no_permohonan' => self::TAG.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_SELESAI,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'is_percuma' => false,
            'jumlah_bayaran' => 260.00,
            'status_bayaran' => false,
        ], $attrs));
    }

    private function receipt(): array
    {
        return [
            'nombor_resit' => 'R-'.self::TAG,
            'tarikh_resit' => now()->toDateString(),
            'kaedah_bayaran' => 'TUNAI',
            'rujukan_bayaran' => null,
        ];
    }

    // ---- service ----

    public function test_rekod_bayaran_creates_paid_ledger_row_and_flips_kn(): void
    {
        $kn = $this->makePaidKn();

        $row = app(LejarTuntutanService::class)->rekodBayaranKn($kn, $this->receipt(), 'tester');

        $this->assertNotNull($row);
        $this->assertSame(LejarTuntutanBayaran::SUMBER_KN, $row->sumber);
        $this->assertSame(LejarTuntutanBayaran::STATUS_DIBAYAR, $row->status_tuntutan);
        $this->assertTrue($row->status_bayaran);
        $this->assertSame('R-'.self::TAG, $row->nombor_resit);
        $this->assertTrue($kn->fresh()->status_bayaran);
    }

    public function test_free_kn_records_no_payment(): void
    {
        $kn = $this->makePaidKn(['is_percuma' => true, 'jumlah_bayaran' => 0]);

        $this->assertNull(app(LejarTuntutanService::class)->rekodBayaranKn($kn, $this->receipt(), 'tester'));
        $this->assertSame(0, LejarTuntutanBayaran::where('id_khidmat_nasihat', $kn->id)->count());
    }

    public function test_rerecording_is_idempotent_one_ledger_row(): void
    {
        $kn = $this->makePaidKn();
        $svc = app(LejarTuntutanService::class);

        $svc->rekodBayaranKn($kn, $this->receipt(), 'tester');
        $svc->rekodBayaranKn($kn->fresh(), array_merge($this->receipt(), ['nombor_resit' => 'R2-'.self::TAG]), 'tester');

        $rows = LejarTuntutanBayaran::where('id_khidmat_nasihat', $kn->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('R2-'.self::TAG, $rows->first()->nombor_resit);
    }

    // ---- endpoint ----

    public function test_officer_endpoint_records_counter_payment_with_resit(): void
    {
        Storage::fake('repositori');
        $kn = $this->makePaidKn();

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.bayar', $kn), [
                'nombor_resit' => 'RC-'.self::TAG,
                'tarikh_resit' => now()->toDateString(),
                'kaedah_bayaran' => 'IPAYMENT',
                'rujukan_bayaran' => 'FPX123',
                'lampiran_resit' => HttpUploadedFile::fake()->create('resit.pdf', 80, 'application/pdf'),
            ])->assertRedirect();

        $kn = $kn->fresh();
        $this->assertTrue($kn->status_bayaran);
        $this->assertNotNull($kn->id_lampiran_resit);

        $row = LejarTuntutanBayaran::where('id_khidmat_nasihat', $kn->id)->firstOrFail();
        $this->assertSame(LejarTuntutanBayaran::STATUS_DIBAYAR, $row->status_tuntutan);
        $this->assertSame('IPAYMENT', $row->kaedah_bayaran);

        $lampiran = Lampiran::findOrFail($kn->id_lampiran_resit);
        Storage::disk('repositori')->assertExists($lampiran->file_path);
    }

    public function test_payment_endpoint_requires_khidmat_proses(): void
    {
        $kn = $this->makePaidKn();

        // peguam (lawyer) has no khidmat.proses -> permission deny = 302 redirect, no record.
        $this->actingAs($this->user('peguam@test.local'))
            ->post(route('khidmat.bayar', $kn), $this->receipt())
            ->assertStatus(302);

        $this->assertFalse($kn->fresh()->status_bayaran);
        $this->assertSame(0, LejarTuntutanBayaran::where('id_khidmat_nasihat', $kn->id)->count());
    }

    public function test_cannot_record_payment_for_free_kn_via_endpoint(): void
    {
        $kn = $this->makePaidKn(['is_percuma' => true, 'jumlah_bayaran' => 0]);

        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.bayar', $kn), $this->receipt())
            ->assertStatus(403);
    }
}
