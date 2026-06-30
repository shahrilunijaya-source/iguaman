<?php

namespace Tests\Feature;

use App\Models\ButiranPeguamPanel2;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W10 — panel-lawyer registration track (jalur) split + forked approver tier.
 * Criminal applications route to the Pembelaan Awam approvers; civil/syariah to
 * the Peguam Panel approvers. Live mysql; TAG rows self-clean.
 */
class PeguamJalurTest extends TestCase
{
    private const TAG = 'PHPUNITJL';

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
        ButiranPeguamPanel2::where('namaPeguam', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', '%@jl.local')->delete();
    }

    private function application(string $jalur): ButiranPeguamPanel2
    {
        return ButiranPeguamPanel2::create([
            'namaPeguam' => self::TAG.' Pemohon', 'kpBaru' => '900101015555',
            'jantina' => 'L', 'noTelBimbit' => '0123456789', 'emelPeguam' => 'pemohon@jl.local',
            'kelulusanAkademik' => 'LLB', 'tahunPengalaman' => 5, 'tahunPengalamanSyarie' => 0,
            'bilanganKes' => 0, 'keteranganKes' => '-',
            'permohonan_status' => '0', 'semakan_ppuu' => '1', 'tarikhMohon' => now(),
            'jalur_permohonan' => $jalur,
        ]);
    }

    private function approver(string $role): User
    {
        $u = User::create([
            'name' => self::TAG.' '.$role, 'email' => $role.'@jl.local', 'password' => Hash::make('x'),
            'user_type' => User::TYPE_STAFF, 'role' => $role, 'is_active' => true,
        ]);
        $u->syncRoles([$role]);

        return $u;
    }

    public function test_criminal_application_endorsed_only_by_pembelaan_awam_pengarah(): void
    {
        $app = $this->application(ButiranPeguamPanel2::JALUR_JENAYAH);

        // Civil Pengarah must NOT be able to endorse a criminal application.
        $this->actingAs($this->approver('pengarah'))
            ->post(route('permohonan-peguam.sokong', $app), ['sokonganPengarah' => '1'])->assertRedirect();
        $this->assertNull($app->fresh()->sokonganPengarah);

        // Pengarah Pembelaan Awam endorses it.
        $this->actingAs($this->approver('pengarah_pembelaan_awam'))
            ->post(route('permohonan-peguam.sokong', $app), ['sokonganPengarah' => '1'])->assertRedirect();
        $this->assertSame('1', $app->fresh()->sokonganPengarah);
    }

    public function test_civil_application_endorsed_only_by_peguam_panel_pengarah(): void
    {
        $app = $this->application(ButiranPeguamPanel2::JALUR_SIVIL_SYARIAH);

        // Pembelaan Awam Pengarah must NOT endorse a civil application.
        $this->actingAs($this->approver('pengarah_pembelaan_awam'))
            ->post(route('permohonan-peguam.sokong', $app), ['sokonganPengarah' => '1'])->assertRedirect();
        $this->assertNull($app->fresh()->sokonganPengarah);

        // Civil Pengarah endorses it.
        $this->actingAs($this->approver('pengarah'))
            ->post(route('permohonan-peguam.sokong', $app), ['sokonganPengarah' => '1'])->assertRedirect();
        $this->assertSame('1', $app->fresh()->sokonganPengarah);
    }

    public function test_criminal_final_decision_only_by_pembelaan_awam_ketua(): void
    {
        $app = $this->application(ButiranPeguamPanel2::JALUR_JENAYAH);
        $app->update(['sokonganPengarah' => '1']);

        // Civil Ketua Pengarah blocked on a criminal application.
        $this->actingAs($this->approver('ketua_pengarah'))
            ->post(route('permohonan-peguam.keputusan', $app), ['keputusan' => 'lulus'])->assertRedirect();
        $this->assertSame('0', $app->fresh()->permohonan_status);

        // Ketua Pembelaan Awam approves -> status Lulus.
        $this->actingAs($this->approver('ketua_pembelaan_awam'))
            ->post(route('permohonan-peguam.keputusan', $app), ['keputusan' => 'lulus'])->assertRedirect();
        $this->assertSame('1', $app->fresh()->permohonan_status);
    }
}
