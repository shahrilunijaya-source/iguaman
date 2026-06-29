<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Phase 4 — agihan (assignment) + lawyer area, over the real iguaman_2in1 DB. Self-cleaning. */
class Phase4Test extends TestCase
{
    private const TAG = 'PHPUNIT';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        (new \Database\Seeders\RolePermissionSeeder())->run();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = Form::where('cawangan', self::TAG)->pluck('id');
        if ($ids->isNotEmpty()) {
            SejarahPeguamPanel::whereIn('id_kes', $ids)->delete();
            Form::whereIn('id', $ids)->delete();
        }
        PeguamPanel::where('nama_peguam', 'like', 'PHPUNIT%')->delete();
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    private function staff(): User
    {
        $user = User::create([
            'name' => 'PHPUnit Staff', 'email' => 'staff@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => 'pegawai', 'is_active' => true,
        ]);
        $user->syncRoles([$user->role]);

        return $user;
    }

    private function makeCase(): Form
    {
        return Form::create(['nama' => 'Kes Ujian', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now()]);
    }

    private function makePeguam(string $nama, string $kp): PeguamPanel
    {
        return PeguamPanel::create([
            'nama_peguam' => $nama, 'kp_peguam' => $kp, 'tel_peguam' => '0123456789',
            'emel_peguam' => strtolower($kp).'@firma.my', 'nama_firma' => 'Tetuan PHPUnit',
            'alamat_firma_1' => 'A1', 'alamat_firma_2' => 'A2', 'poskod_firma' => '40000',
            'negeri_firma' => 'Selangor', 'tel_firma' => '0312345678',
        ]);
    }

    public function test_assign_lawyer_to_case(): void
    {
        $kes = $this->makeCase();
        $p = $this->makePeguam('PHPUNIT Ali', 'KP001');

        $this->actingAs($this->staff())
            ->post(route('agihan.store', $kes), ['peguam_id' => $p->id])
            ->assertRedirect(route('kes.show', $kes));

        $kes->refresh();
        $this->assertSame('PHPUNIT Ali', $kes->nama_pegawai_yang_dapat_kes);
        // Agihan now creates an OFFER (status_agihan=Ditawarkan); the lawyer accepts/rejects
        // in their area (peguam offer workflow). Was 'Diagih' before that workflow shipped.
        $this->assertSame('Ditawarkan', $kes->status_agihan);
    }

    public function test_reassign_logs_previous_lawyer(): void
    {
        $kes = $this->makeCase();
        $a = $this->makePeguam('PHPUNIT Ali', 'KP001');
        $b = $this->makePeguam('PHPUNIT Bakar', 'KP002');
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('agihan.store', $kes), ['peguam_id' => $a->id]);
        $this->actingAs($staff)->post(route('agihan.store', $kes), ['peguam_id' => $b->id, 'alasan' => 'Konflik']);

        $this->assertDatabaseHas('sejarah_peguam_panel', [
            'id_kes' => $kes->id, 'nama_pp_lama' => 'PHPUNIT Ali', 'alasan' => 'Konflik',
        ]);
        $this->assertSame('PHPUNIT Bakar', $kes->fresh()->nama_pegawai_yang_dapat_kes);
    }

    public function test_assign_requires_peguam(): void
    {
        $kes = $this->makeCase();

        $this->actingAs($this->staff())
            ->post(route('agihan.store', $kes), [])
            ->assertSessionHasErrors('peguam_id');
    }

    public function test_beban_tugas_loads(): void
    {
        $this->actingAs($this->staff())
            ->get(route('agihan.beban'))
            ->assertOk()
            ->assertSee('Beban Tugas Peguam');
    }

    public function test_lawyer_sees_own_cases(): void
    {
        $p = $this->makePeguam('PHPUNIT Ali', 'KP001');
        $kes = $this->makeCase();
        $kes->update(['nama_pegawai_yang_dapat_kes' => 'PHPUNIT Ali']);

        $lawyer = User::create([
            'name' => 'PHPUNIT Ali', 'email' => 'ali@phpunit.local', 'password' => Hash::make('secret'),
            'user_type' => 'lawyer', 'role' => 'peguam', 'id_peguam_panel' => 'KP001', 'is_active' => true,
        ]);
        $lawyer->syncRoles([$lawyer->role]);

        $this->actingAs($lawyer)
            ->get(route('peguam.kes'))
            ->assertOk()
            ->assertSee('Kes Ujian');
    }

    public function test_lawyer_profil_loads(): void
    {
        $this->makePeguam('PHPUNIT Ali', 'KP001');
        $lawyer = User::create([
            'name' => 'PHPUNIT Ali', 'email' => 'ali@phpunit.local', 'password' => Hash::make('secret'),
            'user_type' => 'lawyer', 'role' => 'peguam', 'id_peguam_panel' => 'KP001', 'is_active' => true,
        ]);
        $lawyer->syncRoles([$lawyer->role]);

        $this->actingAs($lawyer)
            ->get(route('peguam.profil'))
            ->assertOk()
            ->assertSee('Tetuan PHPUnit');
    }

    public function test_lawyer_cannot_access_beban(): void
    {
        $lawyer = User::create([
            'name' => 'PHPUNIT Peguam', 'email' => 'peguam@phpunit.local', 'password' => Hash::make('secret'),
            'user_type' => 'lawyer', 'role' => 'peguam', 'is_active' => true,
        ]);
        $lawyer->syncRoles([$lawyer->role]);

        $this->actingAs($lawyer)
            ->get(route('agihan.beban'))
            ->assertRedirect(route('peguam.dashboard'));
    }
}
