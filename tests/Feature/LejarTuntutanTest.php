<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\LejarTuntutanBayaran;
use App\Models\PeguamPanel;
use App\Models\User;
use App\Support\LejarTuntutanService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W15 — central claim ledger. Live mysql per repo convention; TAG rows self-clean.
 * Covers the officer lifecycle (create -> hantar -> semak -> lulus -> bayar), the
 * KN paid-advisory auto-create bridge (D4 / G-M3) + its idempotency, the receipt
 * step flipping the linked KN, lawyer self-service scoping, and permission gating.
 */
class LejarTuntutanTest extends TestCase
{
    private const TAG = 'PHPUNITLT';

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
        $knIds = KhidmatNasihat::where('nama_mangsa', 'like', self::TAG.'%')->pluck('id');
        LejarTuntutanBayaran::where('keterangan', self::TAG)
            ->orWhere('jenis_tuntutan', 'like', self::TAG.'%')
            ->orWhereIn('id_khidmat_nasihat', $knIds)
            ->delete();
        KhidmatNasihat::whereIn('id', $knIds)->delete();
        Form::where('cawangan', self::TAG)->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function makeClaim(array $attrs = []): LejarTuntutanBayaran
    {
        return app(LejarTuntutanService::class)->cipta(array_merge([
            'sumber' => LejarTuntutanBayaran::SUMBER_LAIN,
            'jenis_tuntutan' => self::TAG.' fi',
            'keterangan' => self::TAG,
            'jumlah_tuntutan' => 500.00,
        ], $attrs), 'tester');
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

    // ---- officer lifecycle ----

    public function test_officer_progresses_claim_through_to_paid(): void
    {
        $claim = $this->makeClaim();
        $this->assertSame(LejarTuntutanBayaran::STATUS_DRAF, $claim->status_tuntutan);

        $this->actingAs($this->user('koordinator@test.local'))
            ->post(route('tuntutan.hantar', $claim))->assertRedirect();
        $this->assertSame(LejarTuntutanBayaran::STATUS_DIHANTAR, $claim->fresh()->status_tuntutan);

        $this->actingAs($this->user('koordinator@test.local'))
            ->post(route('tuntutan.semak', $claim))->assertRedirect();
        $this->assertSame(LejarTuntutanBayaran::STATUS_SEMAKAN, $claim->fresh()->status_tuntutan);

        $this->actingAs($this->user('pengarah@test.local'))
            ->post(route('tuntutan.lulus', $claim), ['jumlah_diluluskan' => 450])->assertRedirect();
        $this->assertSame(LejarTuntutanBayaran::STATUS_DILULUS, $claim->fresh()->status_tuntutan);

        $this->actingAs($this->user('pengarah@test.local'))
            ->post(route('tuntutan.bayar', $claim), [
                'nombor_resit' => 'R-'.self::TAG, 'tarikh_resit' => now()->toDateString(),
                'kaedah_bayaran' => 'EFT', 'jumlah_bayaran' => 450,
            ])->assertRedirect();

        $fresh = $claim->fresh();
        $this->assertSame(LejarTuntutanBayaran::STATUS_DIBAYAR, $fresh->status_tuntutan);
        $this->assertTrue($fresh->status_bayaran);
        $this->assertSame('R-'.self::TAG, $fresh->nombor_resit);
    }

    public function test_invalid_transition_is_blocked(): void
    {
        $claim = $this->makeClaim(); // DRAF

        // Cannot pay a DRAF claim — the guard rejects and status is unchanged.
        $this->actingAs($this->user('pengarah@test.local'))
            ->post(route('tuntutan.bayar', $claim), [
                'nombor_resit' => 'X', 'tarikh_resit' => now()->toDateString(),
                'kaedah_bayaran' => 'EFT', 'jumlah_bayaran' => 1,
            ])->assertRedirect();

        $this->assertSame(LejarTuntutanBayaran::STATUS_DRAF, $claim->fresh()->status_tuntutan);
    }

    public function test_lulus_requires_permission(): void
    {
        $claim = $this->makeClaim(['status_tuntutan' => LejarTuntutanBayaran::STATUS_SEMAKAN]);

        // pembantu_tadbir has tuntutan.view but NOT tuntutan.lulus. Staff lacking a
        // permission are redirected to their home route (per bootstrap/app.php), not 403;
        // either way the claim must NOT advance.
        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('tuntutan.lulus', $claim))->assertRedirect();

        $this->assertSame(LejarTuntutanBayaran::STATUS_SEMAKAN, $claim->fresh()->status_tuntutan);
    }

    // ---- KN auto-create bridge (D4 / G-M3) ----

    public function test_paid_kn_auto_creates_ledger_row_idempotently(): void
    {
        $kn = $this->makePaidKn();
        $svc = app(LejarTuntutanService::class);

        $row = $svc->fromKhidmatNasihat($kn, 'tester');
        $this->assertNotNull($row);
        $this->assertSame(LejarTuntutanBayaran::SUMBER_KN, $row->sumber);
        $this->assertSame($kn->id, (int) $row->id_khidmat_nasihat);

        // Second call returns the same row (unique sumber+id_khidmat_nasihat).
        $again = $svc->fromKhidmatNasihat($kn, 'tester');
        $this->assertSame($row->id, $again->id);
        $this->assertSame(1, LejarTuntutanBayaran::where('id_khidmat_nasihat', $kn->id)->count());
    }

    public function test_free_kn_creates_no_ledger_row(): void
    {
        $kn = $this->makePaidKn(['is_percuma' => true, 'jumlah_bayaran' => 0]);

        $this->assertNull(app(LejarTuntutanService::class)->fromKhidmatNasihat($kn, 'tester'));
    }

    public function test_bayar_flips_linked_kn_payment_flag(): void
    {
        $kn = $this->makePaidKn();
        $svc = app(LejarTuntutanService::class);
        $claim = $svc->fromKhidmatNasihat($kn, 'tester'); // DIHANTAR

        $svc->transition($claim, 'semak', 'tester');
        $svc->transition($claim, 'lulus', 'tester');
        $svc->transition($claim, 'bayar', 'tester', [
            'nombor_resit' => 'R1', 'tarikh_resit' => now(), 'kaedah_bayaran' => 'Tunai',
            'jumlah_bayaran' => 260, 'status_bayaran' => true, 'tarikh_bayar' => now(),
        ]);

        $this->assertTrue($kn->fresh()->status_bayaran);
    }

    // ---- lawyer self-service ----

    public function test_lawyer_files_claim_against_assigned_case_and_sees_only_own(): void
    {
        $panel = PeguamPanel::whereKey($this->user('peguam@test.local')->lawyerProfile?->id)->firstOrFail();
        $kes = Form::create([
            'nama' => self::TAG.' OYD', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now(),
            'nama_pegawai_yang_dapat_kes' => $panel->nama_peguam,
        ]);

        $this->actingAs($this->user('peguam@test.local'))
            ->post(route('peguam.tuntutan.store', $kes), [
                'jenis_tuntutan' => self::TAG.' yuran', 'jumlah_tuntutan' => 1200,
            ])->assertRedirect();

        $row = LejarTuntutanBayaran::where('id_kes', $kes->id)->firstOrFail();
        $this->assertSame(LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR, $row->sumber);
        $this->assertSame($panel->kp_peguam, $row->kp_peguam);

        // A claim owned by another lawyer is not visible to this one.
        $other = $this->makeClaim(['kp_peguam' => 'OTHER-IC', 'sumber' => LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR]);
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('peguam.tuntutan.show', $other))->assertForbidden();
    }

    public function test_lawyer_cannot_file_on_unassigned_case(): void
    {
        $kes = Form::create([
            'nama' => self::TAG.' Lain', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now(),
            'nama_pegawai_yang_dapat_kes' => 'Peguam Lain',
        ]);

        $this->actingAs($this->user('peguam@test.local'))
            ->post(route('peguam.tuntutan.store', $kes), [
                'jenis_tuntutan' => self::TAG, 'jumlah_tuntutan' => 100,
            ])->assertForbidden();
    }
}
