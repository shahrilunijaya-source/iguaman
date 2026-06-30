<?php

namespace Tests\Feature;

use App\Mail\KesLebihMasaMail;
use App\Models\Form;
use App\Models\Scopes\CawanganScope;
use App\Models\SejarahPeguamPanel;
use App\Models\User;
use App\Support\LebihMasaService;
use App\Support\StatusAgihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * EPIC G — Lebih Masa auto re-assignment (legacy cron_lebih_masa.php).
 *
 * Live mysql (iguaman_2in1) per repo convention; rows tagged PHPUNIT, cleaned up.
 * NOTE: the service's run()/command process EVERY overdue offer in the shared db,
 * so the tests deliberately exercise reassign() on a single tagged case and
 * overdue() read-only (contains/excludes by id) — never the global run() — to
 * avoid mutating real rows.
 */
class LebihMasaTest extends TestCase
{
    private const TAG = 'PHPUNIT';

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
        $ids = Form::withoutGlobalScope(CawanganScope::class)->where('cawangan', self::TAG)->pluck('id');
        SejarahPeguamPanel::whereIn('id_kes', $ids)->delete();
        Form::withoutGlobalScope(CawanganScope::class)->where('cawangan', self::TAG)->delete();
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    private function offeredCase(string $offerDate): Form
    {
        return Form::create([
            'cawangan' => self::TAG,
            'nama' => 'UJIAN OYD',
            'nama_pegawai_yang_dapat_kes' => self::TAG.' Peguam',
            'agih_kepada' => self::TAG.' Peguam',
            'status_agihan' => StatusAgihan::DITAWARKAN,
            'tarikh_penugasan_peguam_panel' => $offerDate,
            'no_fail' => 'JBG.PHPUNIT.LM',
            'diterima' => '',
            'created_at' => now(),
        ]);
    }

    private function pengarah(): User
    {
        return User::create([
            'name' => 'PHPUnit Pengarah', 'email' => 'pengarah@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => 'pengarah', 'cawangan' => self::TAG, 'is_active' => true,
        ]);
    }

    public function test_reassign_bounces_offer_back_and_logs_history(): void
    {
        Mail::fake();
        $this->pengarah();
        $kes = $this->offeredCase(now()->subDays(10)->toDateString());

        app(LebihMasaService::class)->reassign($kes->fresh());

        $fresh = Form::withoutGlobalScope(CawanganScope::class)->find($kes->id);
        $this->assertSame(StatusAgihan::PPUU_AGIH_SEMULA, (string) $fresh->status_agihan);
        $this->assertNull($fresh->nama_pegawai_yang_dapat_kes);
        $this->assertNull($fresh->tarikh_penugasan_peguam_panel);
        $this->assertSame(LebihMasaService::REASON, $fresh->sebab_Tidak_Diluluskan);

        $sejarah = SejarahPeguamPanel::where('id_kes', $kes->id)->first();
        $this->assertNotNull($sejarah);
        $this->assertSame(StatusAgihan::LEBIH_MASA, (string) $sejarah->status_agihan);
        $this->assertSame(self::TAG.' Peguam', $sejarah->nama_pp_lama);
        $this->assertSame('tutup', $sejarah->status_rekod);
        $this->assertSame(LebihMasaService::REASON, $sejarah->alasan);

        Mail::assertSent(KesLebihMasaMail::class);
    }

    public function test_overdue_selects_old_offer_only(): void
    {
        $old = $this->offeredCase(now()->subDays(10)->toDateString());
        $recent = $this->offeredCase(now()->subDays(2)->toDateString());

        $ids = app(LebihMasaService::class)->overdue()->pluck('id');

        $this->assertTrue($ids->contains($old->id), 'overdue must include the 10-day-old offer');
        $this->assertFalse($ids->contains($recent->id), 'overdue must exclude the 2-day-old offer');
    }

    public function test_overdue_ignores_non_offered_status(): void
    {
        // Accepted (status 2), old date — must never be selected.
        $accepted = $this->offeredCase(now()->subDays(30)->toDateString());
        $accepted->update(['status_agihan' => StatusAgihan::DITERIMA]);

        $ids = app(LebihMasaService::class)->overdue()->pluck('id');

        $this->assertFalse($ids->contains($accepted->id));
    }
}
