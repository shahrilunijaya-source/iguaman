<?php

namespace Tests\Feature;

use App\Mail\AgihanTransisiMail;
use App\Models\Form;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use App\Support\AgihanService;
use App\Support\NotifikasiAgihan;
use App\Support\StatusAgihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * EPIC G — 3-tier assignment transition emails (legacy agihanbaru/* mail blocks).
 * Live mysql (iguaman_2in1) per repo convention; rows tagged PHPUNIT, cleaned up.
 * Assertions target specific recipient addresses (not counts) because the HQ
 * Pengarah recipient set also contains real rows in the shared db.
 */
class NotifikasiAgihanTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
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
        Form::withoutGlobalScope(CawanganScope::class)->where('cawangan', self::TAG)->delete();
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    private function user(string $email, string $role, ?string $cawangan = self::TAG): User
    {
        return User::create([
            'name' => 'PHPUnit '.$role, 'email' => $email,
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => $role, 'cawangan' => $cawangan, 'is_active' => true,
        ]);
    }

    private function kes(): Form
    {
        return Form::create([
            'cawangan' => self::TAG, 'nama' => 'UJIAN OYD',
            'status_agihan' => StatusAgihan::BARU_PENGARAH, 'no_fail' => 'JBG.PHPUNIT.NOTI',
            'diterima' => '', 'created_at' => now(),
        ]);
    }

    public function test_pengarah_terima_notifies_chosen_ppuu(): void
    {
        Mail::fake();
        $ppuu = $this->user('ppuu@phpunit.local', 'ppuu');
        $kes = $this->kes();

        app(NotifikasiAgihan::class)->pengarahTerima($kes, $ppuu->id);

        Mail::assertSent(AgihanTransisiMail::class, 1);
        Mail::assertSent(AgihanTransisiMail::class, fn ($m) => $m->hasTo('ppuu@phpunit.local')
            && $m->tajuk === 'Pemakluman Tugasan bagi Agihan Baru');
    }

    public function test_pengarah_tolak_notifies_branch_supervisors(): void
    {
        Mail::fake();
        $this->user('branchpengarah@phpunit.local', 'pengarah');
        $this->user('branchkoord@phpunit.local', 'koordinator');
        // Same role but a DIFFERENT branch must be excluded.
        $this->user('otherpengarah@phpunit.local', 'pengarah', 'LAIN');
        $kes = $this->kes();

        app(NotifikasiAgihan::class)->pengarahTolak($kes, 'Tidak memenuhi kriteria');

        Mail::assertSent(AgihanTransisiMail::class, 2);
        Mail::assertSent(AgihanTransisiMail::class, fn ($m) => $m->hasTo('branchpengarah@phpunit.local'));
        Mail::assertSent(AgihanTransisiMail::class, fn ($m) => $m->hasTo('branchkoord@phpunit.local'));
        Mail::assertNotSent(AgihanTransisiMail::class, fn ($m) => $m->hasTo('otherpengarah@phpunit.local'));
    }

    public function test_ppuu_pilih_notifies_pengarah(): void
    {
        Mail::fake();
        $this->user('branchpengarah@phpunit.local', 'pengarah');
        $kes = $this->kes();

        app(NotifikasiAgihan::class)->ppuuPilih($kes, 'PEGUAM UJIAN');

        Mail::assertSent(AgihanTransisiMail::class, fn ($m) => $m->hasTo('branchpengarah@phpunit.local')
            && $m->tajuk === 'Pemakluman Status Tugasan bagi Agihan Baru');
    }

    public function test_service_pengarah_tolak_baru_wires_email_and_status(): void
    {
        Mail::fake();
        $this->user('branchpengarah@phpunit.local', 'pengarah');
        $actor = $this->user('actor@phpunit.local', 'pengarah');
        $kes = $this->kes();

        app(AgihanService::class)->pengarahTolakBaru($kes, $actor, 'Sebab ujian');

        $this->assertSame(StatusAgihan::DITOLAK_PENGARAH, (string) $kes->fresh()->status_agihan);
        Mail::assertSent(AgihanTransisiMail::class, fn ($m) => $m->hasTo('branchpengarah@phpunit.local'));
    }
}
