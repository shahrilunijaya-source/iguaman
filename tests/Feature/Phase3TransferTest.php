<?php

namespace Tests\Feature;

use App\Http\Controllers\StatistikPemindahanController;
use App\Models\Cawangan;
use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\PemindahanCawangan;
use App\Models\User;
use App\Support\KhidmatProsesService;
use App\Support\TransferCawanganService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W7 + W3 — branch-transfer engine. Live mysql; TAG rows self-clean.
 * Verifies label-move, D2 dual-branch visibility (forms scope + KN listQuery),
 * accept/reject lifecycle, and the cross-branch + double-transfer guards.
 */
class Phase3TransferTest extends TestCase
{
    private const NAMA_A = 'ZZTransferA';

    private const NAMA_B = 'ZZTransferB';

    private const TAG = 'PHPUNITPX';

    private Cawangan $cawA;

    private Cawangan $cawB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
        $this->cleanup();

        $this->cawA = Cawangan::create(['nama' => self::NAMA_A]);
        $this->cawB = Cawangan::create(['nama' => self::NAMA_B]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $names = [self::NAMA_A, self::NAMA_B];
        Form::withoutGlobalScopes()
            ->whereIn('cawangan', $names)
            ->orWhereIn('cawangan_asal', $names)
            ->delete();
        KhidmatNasihat::where('nama_mangsa', 'like', self::TAG.'%')->delete();
        PemindahanCawangan::whereIn('cawangan_tujuan', $names)->orWhereIn('cawangan_asal', $names)->delete();
        User::where('email', 'like', '%@pindah.local')->delete();
        Cawangan::whereIn('nama', $names)->delete();
    }

    private function makeUser(string $role, ?string $cawangan): User
    {
        $u = User::create([
            'name' => "Pindah $role $cawangan", 'email' => "$role-$cawangan@pindah.local",
            'password' => Hash::make('x'), 'user_type' => 'staff',
            'role' => $role, 'cawangan' => $cawangan, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function kesAt(string $cawangan): Form
    {
        return Form::create(['nama' => self::TAG, 'cawangan' => $cawangan, 'diterima' => '', 'created_at' => now()]);
    }

    private function knAt(int $cawanganId): KhidmatNasihat
    {
        return KhidmatNasihat::create([
            'no_permohonan' => self::TAG.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'jenis_kes' => 'SV',
            'cawangan_id' => $cawanganId,
        ]);
    }

    private function svc(): TransferCawanganService
    {
        return app(TransferCawanganService::class);
    }

    public function test_pindah_kes_moves_label_and_records_transfer(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $actor = $this->makeUser('pengarah', self::NAMA_A);

        $pindah = $this->svc()->pindahKes($kes, $this->cawB->id, 'OYD berpindah', $actor);

        $fresh = Form::withoutGlobalScopes()->find($kes->id);
        $this->assertSame(self::NAMA_B, $fresh->cawangan);
        $this->assertSame(self::NAMA_A, $fresh->cawangan_asal);
        $this->assertSame(PemindahanCawangan::STATUS_DIPINDAH, $pindah->status);
        $this->assertSame(self::NAMA_A, $pindah->cawangan_asal);
        $this->assertSame(self::NAMA_B, $pindah->cawangan_tujuan);
    }

    public function test_origin_and_destination_both_see_transferred_kes(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));

        // Origin (pinned A) retains visibility via cawangan_asal (D2 dual-branch).
        $this->actingAs($this->makeUser('pegawai', self::NAMA_A));
        $this->assertNotNull(Form::find($kes->id), 'origin lost the transferred case');

        // Destination (pinned B) sees it via the moved cawangan.
        $this->actingAs($this->makeUser('pegawai', self::NAMA_B));
        $this->assertNotNull(Form::find($kes->id), 'destination cannot see the transferred case');
    }

    public function test_tolak_reverses_label_back_to_origin(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $pindah = $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));

        $this->svc()->tolak($pindah, 'bukan bidang kuasa kami', $this->makeUser('pengarah', self::NAMA_B));

        $fresh = Form::withoutGlobalScopes()->find($kes->id);
        $this->assertSame(self::NAMA_A, $fresh->cawangan);
        $this->assertNull($fresh->cawangan_asal);
        $this->assertSame(PemindahanCawangan::STATUS_DITOLAK, $pindah->fresh()->status);
        $this->assertSame('bukan bidang kuasa kami', $pindah->fresh()->sebab_tolak);
    }

    public function test_terima_marks_accepted_and_keeps_dual_visibility(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $pindah = $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));

        $this->svc()->terima($pindah, $this->makeUser('pengarah', self::NAMA_B));

        $this->assertSame(PemindahanCawangan::STATUS_DITERIMA, $pindah->fresh()->status);
        $fresh = Form::withoutGlobalScopes()->find($kes->id);
        $this->assertSame(self::NAMA_B, $fresh->cawangan);
        $this->assertSame(self::NAMA_A, $fresh->cawangan_asal); // origin retained after accept
    }

    public function test_branch_pinned_actor_cannot_transfer_another_branch_kes(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $actorB = $this->makeUser('pengarah', self::NAMA_B);

        $this->expectException(RuntimeException::class);
        $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $actorB);
    }

    public function test_cannot_open_a_second_pending_transfer(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $actor = $this->makeUser('pengarah', self::NAMA_A);
        $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $actor);

        $this->expectException(RuntimeException::class);
        $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $actor);
    }

    public function test_destination_branch_only_may_accept(): void
    {
        $kes = $this->kesAt(self::NAMA_A);
        $pindah = $this->svc()->pindahKes($kes, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));

        // Origin officer (A) is NOT the destination — may not accept.
        $this->expectException(RuntimeException::class);
        $this->svc()->terima($pindah, $this->makeUser('pengarah', self::NAMA_A));
    }

    public function test_pindah_kn_moves_cawangan_id_and_origin_retains_in_worklist(): void
    {
        $kn = $this->knAt($this->cawA->id);
        $actorA = $this->makeUser('pengarah', self::NAMA_A);

        $this->svc()->pindahKn($kn, $this->cawB->id, 'sebab', $actorA);

        $fresh = $kn->fresh();
        $this->assertSame($this->cawB->id, (int) $fresh->cawangan_id);
        $this->assertSame($this->cawA->id, (int) $fresh->cawangan_asal_id);

        // Origin (pinned A) keeps the KN in its processing worklist via cawangan_asal_id.
        $proses = app(KhidmatProsesService::class);
        $originIds = $proses->listQuery($actorA, [])->pluck('id');
        $this->assertTrue($originIds->contains($kn->id), 'origin lost the transferred KN from its worklist');

        // Destination (pinned B) sees it via the moved cawangan_id.
        $destIds = $proses->listQuery($this->makeUser('pegawai', self::NAMA_B), [])->pluck('id');
        $this->assertTrue($destIds->contains($kn->id), 'destination cannot see the transferred KN');
    }

    public function test_draf_kn_cannot_be_transferred(): void
    {
        $kn = $this->knAt($this->cawA->id);
        $kn->update(['status_kn' => KhidmatNasihat::STATUS_DRAF]);

        $this->expectException(RuntimeException::class);
        $this->svc()->pindahKn($kn, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));
    }

    public function test_kn_with_no_origin_branch_cannot_be_transferred(): void
    {
        $kn = $this->knAt($this->cawA->id);
        $kn->update(['cawangan_id' => null]); // branch deleted out from under a live KN

        $this->expectException(RuntimeException::class);
        // HQ/view-all actor bypasses the branch guard, so the NULL-origin guard must catch it.
        $this->svc()->pindahKn($kn, $this->cawB->id, 'sebab', $this->makeUser('koordinator', self::NAMA_A));
    }

    public function test_ppuu_cannot_accept_a_kn_transfer(): void
    {
        $kn = $this->knAt($this->cawA->id);
        $this->svc()->pindahKn($kn, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));
        $pindah = PemindahanCawangan::where('jenis_rekod', PemindahanCawangan::JENIS_KN)->where('id_rekod', $kn->id)->firstOrFail();

        // ppuu holds kes.pindah but NOT khidmat.manage — must not act on a KN transfer.
        $ppuu = $this->makeUser('ppuu', self::NAMA_B);
        $this->assertFalse($this->svc()->canActOn($pindah, $ppuu));

        $this->expectException(RuntimeException::class);
        $this->svc()->terima($pindah, $ppuu);
    }

    public function test_pengarah_can_accept_a_kn_transfer(): void
    {
        $kn = $this->knAt($this->cawA->id);
        $this->svc()->pindahKn($kn, $this->cawB->id, 'sebab', $this->makeUser('pengarah', self::NAMA_A));
        $pindah = PemindahanCawangan::where('jenis_rekod', PemindahanCawangan::JENIS_KN)->where('id_rekod', $kn->id)->firstOrFail();

        // pengarah holds khidmat.manage + is the destination branch.
        $this->svc()->terima($pindah, $this->makeUser('pengarah', self::NAMA_B));

        $this->assertSame(PemindahanCawangan::STATUS_DITERIMA, $pindah->fresh()->status);
    }

    public function test_w8_stat_matrix_counts_in_out_and_excludes_rejected(): void
    {
        $actorA = $this->makeUser('pengarah', self::NAMA_A);
        $actorB = $this->makeUser('pengarah', self::NAMA_B);

        // Two A->B case transfers: one accepted, one rejected (rejected must NOT count).
        $accepted = $this->kesAt(self::NAMA_A);
        $p1 = $this->svc()->pindahKes($accepted, $this->cawB->id, 'a', $actorA);
        $this->svc()->terima($p1, $actorB);

        $rejected = $this->kesAt(self::NAMA_A);
        $p2 = $this->svc()->pindahKes($rejected, $this->cawB->id, 'b', $actorA);
        $this->svc()->tolak($p2, 'tak setuju', $actorB);

        $svc = new StatistikPemindahanController;
        $compute = (new \ReflectionClass($svc))->getMethod('compute');
        $compute->setAccessible(true);
        [$matrix, $totals] = $compute->invoke($svc, PemindahanCawangan::JENIS_KES, (int) now()->year);

        // Exactly one live movement: A keluar 1, B masuk 1; rejected excluded.
        $this->assertSame(1, $totals['keluar']);
        $this->assertSame(1, $totals['masuk']);
        $month = (int) now()->month;
        $this->assertSame(1, $matrix[self::NAMA_A][$month]['keluar']);
        $this->assertSame(0, $matrix[self::NAMA_A][$month]['masuk']);
        $this->assertSame(1, $matrix[self::NAMA_B][$month]['masuk']);
    }
}
