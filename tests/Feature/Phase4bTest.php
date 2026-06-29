<?php

namespace Tests\Feature;

use App\Models\ButiranPeguamPanel2;
use App\Models\PeguamPanel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Phase 4b — lawyer panel application approval workflow. Real DB, self-cleaning. */
class Phase4bTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        ButiranPeguamPanel2::where('kpBaru', 'like', 'PHPUNIT%')->delete();
        PeguamPanel::where('kp_peguam', 'like', 'PHPUNIT%')->delete();
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => 'PHPUnit '.$role, 'email' => $role.'@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => $role, 'is_active' => true,
        ]);
    }

    private function app(): ButiranPeguamPanel2
    {
        return ButiranPeguamPanel2::create([
            'namaPeguam' => 'PHPUNIT Calon', 'kpBaru' => 'PHPUNITKP1', 'jantina' => 'Lelaki',
            'noTelBimbit' => '0123456789', 'emelPeguam' => 'calon@firma.my', 'kelulusanAkademik' => 'LLB',
            'tahunPengalaman' => '5', 'tahunPengalamanSyarie' => '0', 'bilanganKes' => '10',
            'keteranganKes' => '-', 'permohonan_status' => '0',
        ]);
    }

    public function test_index_loads(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('permohonan-peguam.index'))
            ->assertOk()
            ->assertSee('Permohonan Peguam Panel');
    }

    public function test_pengarah_endorses(): void
    {
        $a = $this->app();

        $this->actingAs($this->user('pengarah'))
            ->post(route('permohonan-peguam.sokong', $a), ['sokonganPengarah' => '1', 'ulasan_sokonganPengarah' => 'Layak'])
            ->assertRedirect(route('permohonan-peguam.show', $a));

        $this->assertSame('1', $a->fresh()->sokonganPengarah);
    }

    public function test_pegawai_cannot_endorse(): void
    {
        $a = $this->app();

        $this->actingAs($this->user('pegawai'))
            ->post(route('permohonan-peguam.sokong', $a), ['sokonganPengarah' => '1'])
            ->assertSessionHasErrors('akses');

        $this->assertNull($a->fresh()->sokonganPengarah);
    }

    public function test_approve_promotes_to_panel(): void
    {
        $a = $this->app();

        $this->actingAs($this->user('admin'))
            ->post(route('permohonan-peguam.keputusan', $a), ['keputusan' => 'lulus', 'ulasan' => 'OK'])
            ->assertRedirect(route('permohonan-peguam.show', $a));

        $this->assertSame('1', $a->fresh()->permohonan_status);
        $this->assertDatabaseHas('peguam_panel', ['kp_peguam' => 'PHPUNITKP1', 'nama_peguam' => 'PHPUNIT Calon']);
    }

    public function test_reject_sets_status(): void
    {
        $a = $this->app();

        $this->actingAs($this->user('koordinator'))
            ->post(route('permohonan-peguam.keputusan', $a), ['keputusan' => 'tolak', 'ulasan' => 'Tak layak'])
            ->assertRedirect(route('permohonan-peguam.show', $a));

        $this->assertSame('2', $a->fresh()->permohonan_status);
        $this->assertDatabaseMissing('peguam_panel', ['kp_peguam' => 'PHPUNITKP1']);
    }

    public function test_pegawai_cannot_decide(): void
    {
        $a = $this->app();

        $this->actingAs($this->user('pegawai'))
            ->post(route('permohonan-peguam.keputusan', $a), ['keputusan' => 'lulus'])
            ->assertSessionHasErrors('akses');

        $this->assertSame('0', $a->fresh()->permohonan_status);
    }

    public function test_tarik_diri(): void
    {
        $a = $this->app();

        $this->actingAs($this->user('admin'))
            ->post(route('permohonan-peguam.tarik', $a), ['sebabBatal' => 'Berhenti'])
            ->assertRedirect(route('permohonan-peguam.show', $a));

        $this->assertSame('3', $a->fresh()->permohonan_status);
    }
}
