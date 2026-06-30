<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\User;
use App\Support\KesKnSyncService;
use App\Support\KhidmatProsesService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W12 — reverse sync forms -> khidmat_nasihat. Live mysql; TAG rows self-clean.
 */
class KesKnSyncTest extends TestCase
{
    private const TAG = 'PHPUNITSY';

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
        Form::withoutGlobalScopes()
            ->where('nama', 'like', self::TAG.'%')
            ->orWhere('cawangan', self::TAG)
            ->delete();
        KhidmatNasihat::whereIn('id', $knIds)->delete();
    }

    private function selesaiKn(): KhidmatNasihat
    {
        return KhidmatNasihat::create([
            'no_permohonan' => self::TAG.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_SELESAI,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'jenis_kes' => 'SV',
            'cawangan_id' => \App\Models\Cawangan::value('id'),
        ]);
    }

    public function test_buka_kes_stamps_terbuka_on_kn(): void
    {
        $kn = $this->selesaiKn();
        $actor = User::where('email', 'koordinator@test.local')->firstOrFail();

        $form = app(KhidmatProsesService::class)->bukaKes($kn, $actor);
        $form->update(['cawangan' => self::TAG]); // tag for cleanup

        $this->assertSame(KesKnSyncService::STATE_TERBUKA, $kn->fresh()->status_kes_terbuka);
        $this->assertNotNull($kn->fresh()->tarikh_kes_dikemaskini);
    }

    public function test_tutup_fail_stamps_ditutup_on_kn(): void
    {
        $kn = $this->selesaiKn();
        $actor = User::where('email', 'koordinator@test.local')->firstOrFail();
        $form = app(KhidmatProsesService::class)->bukaKes($kn, $actor);
        $form->update(['cawangan' => self::TAG]);

        $this->actingAs(User::where('email', 'pengarah@test.local')->firstOrFail())
            ->post(route('kes.tutupfail', $form), ['sebab_tutup_fail' => 'Selesai'])
            ->assertRedirect();

        $this->assertSame(KesKnSyncService::STATE_DITUTUP, $kn->fresh()->status_kes_terbuka);
    }

    public function test_push_is_noop_without_linked_kn(): void
    {
        $form = Form::create(['nama' => self::TAG.' Solo', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now()]);

        // No linked KN — must not throw.
        app(KesKnSyncService::class)->pushToKn($form, KesKnSyncService::STATE_DITUTUP);
        $this->assertTrue(true);
    }
}
