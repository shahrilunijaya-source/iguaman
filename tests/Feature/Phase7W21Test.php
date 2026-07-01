<?php

namespace Tests\Feature;

use App\Events\PemindahanCawanganDimulakan;
use App\Listeners\MaklumkanPemindahanMasuk;
use App\Mail\PemindahanMasukMail;
use App\Models\Cawangan;
use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\PemindahanCawangan;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use App\Support\TransferCawanganService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W21 — real-time integration: a branch transfer dispatches an event whose queued
 * listener notifies the destination branch; and CawanganScope now isolates
 * KhidmatNasihat by branch (extended beyond Form).
 */
class Phase7W21Test extends TestCase
{
    private const TAG = 'PHPUNITW21';

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
        KhidmatNasihat::withoutGlobalScopes()->where('nama_mangsa', 'like', self::TAG.'%')->delete();
        Form::withoutGlobalScopes()->where('cawangan', 'like', self::TAG.'%')
            ->orWhere('cawangan_asal', 'like', self::TAG.'%')->delete();
        PemindahanCawangan::where('cawangan_tujuan', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', self::TAG.'%')->delete();
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function branch(string $suffix, string $kod): Cawangan
    {
        return Cawangan::create([
            'jenis' => 'JBG', 'kod' => $kod, 'nama' => self::TAG.' '.$suffix,
            'negeri_id' => 16, 'status_aktif' => true,
        ]);
    }

    private function pinned(string $role, Cawangan $branch, string $tag): User
    {
        $u = User::create([
            'name' => self::TAG.' '.$tag, 'email' => self::TAG.$tag.'@test.local',
            'password' => Hash::make('password'), 'user_type' => User::TYPE_STAFF,
            'role' => $role, 'cawangan' => $branch->nama, 'is_active' => true, 'must_change_password' => false,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function makeKn(int $cawanganId, array $attrs = []): KhidmatNasihat
    {
        return KhidmatNasihat::create(array_merge([
            'no_permohonan' => self::TAG.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'cawangan_id' => $cawanganId,
        ], $attrs));
    }

    // ---- event + queued listener ----

    public function test_initiating_a_case_transfer_dispatches_the_event(): void
    {
        Event::fake([PemindahanCawanganDimulakan::class]);

        $asal = $this->branch('Asal', 'WA1');
        $tujuan = $this->branch('Tujuan', 'WA2');
        $kes = Form::create(['nama' => self::TAG.' OYD', 'cawangan' => $asal->nama, 'diterima' => '', 'created_at' => now()]);

        app(TransferCawanganService::class)->pindahKes($kes, $tujuan->id, 'Atas permintaan', $this->user('koordinator@test.local'));

        Event::assertDispatched(PemindahanCawanganDimulakan::class,
            fn (PemindahanCawanganDimulakan $e) => (int) $e->pindah->id_rekod === (int) $kes->id);
    }

    public function test_listener_emails_destination_branch_supervisors(): void
    {
        Mail::fake();

        $tujuan = $this->branch('Tujuan', 'WB2');
        $pengarah = $this->pinned(User::ROLE_PENGARAH, $tujuan, 'Pengarah');

        $pindah = PemindahanCawangan::create([
            'jenis_rekod' => PemindahanCawangan::JENIS_KES, 'id_rekod' => 1,
            'cawangan_asal' => self::TAG.' Asal', 'cawangan_tujuan' => $tujuan->nama,
            'sebab' => 'ujian', 'status' => PemindahanCawangan::STATUS_DIPINDAH, 'tarikh_pindah' => now(),
        ]);

        // Sync queue (phpunit) runs the queued listener inline.
        event(new PemindahanCawanganDimulakan($pindah));

        Mail::assertSent(PemindahanMasukMail::class, fn ($m) => $m->hasTo($pengarah->email));
    }

    public function test_notification_listener_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MaklumkanPemindahanMasuk);
    }

    // ---- CawanganScope on KhidmatNasihat ----

    public function test_scope_hides_other_branch_kn_from_pinned_staff(): void
    {
        $a = $this->branch('BranchA', 'WC1');
        $b = $this->branch('BranchB', 'WC2');
        $knA = $this->makeKn($a->id);

        // Pinned to B (no view-all) → A's KN is invisible.
        $this->actingAs($this->pinned(User::ROLE_PEGAWAI, $b, 'PegB'));
        $this->assertNull(KhidmatNasihat::whereKey($knA->id)->first());

        // Pinned to A → visible.
        $this->actingAs($this->pinned(User::ROLE_PEGAWAI, $a, 'PegA'));
        $this->assertNotNull(KhidmatNasihat::whereKey($knA->id)->first());
    }

    public function test_scope_dual_branch_visibility_after_transfer(): void
    {
        $a = $this->branch('Origin', 'WD1');
        $b = $this->branch('Dest', 'WD2');
        $c = $this->branch('Other', 'WD3');
        // A KN transferred A->B: current cawangan_id=B, origin cawangan_asal_id=A.
        $kn = $this->makeKn($b->id, ['cawangan_asal_id' => $a->id]);

        $this->actingAs($this->pinned(User::ROLE_PEGAWAI, $a, 'Org'));
        $this->assertNotNull(KhidmatNasihat::whereKey($kn->id)->first(), 'origin keeps a transferred KN');

        $this->actingAs($this->pinned(User::ROLE_PEGAWAI, $b, 'Dst'));
        $this->assertNotNull(KhidmatNasihat::whereKey($kn->id)->first(), 'destination sees the transferred KN');

        $this->actingAs($this->pinned(User::ROLE_PEGAWAI, $c, 'Oth'));
        $this->assertNull(KhidmatNasihat::whereKey($kn->id)->first(), 'an unrelated branch never sees it');
    }

    public function test_view_all_role_sees_every_branch_kn(): void
    {
        $a = $this->branch('VA1', 'WE1');
        $kn = $this->makeKn($a->id);

        // koordinator holds cawangan.view-all → no branch filtering even if pinned.
        $this->actingAs($this->pinned(User::ROLE_KOORDINATOR, $this->branch('VA2', 'WE2'), 'Koor'));
        $this->assertNotNull(KhidmatNasihat::whereKey($kn->id)->first());
    }
}
