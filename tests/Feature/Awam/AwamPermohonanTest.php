<?php

namespace Tests\Feature\Awam;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 13 — Citizen self-service KN portal: owner policy + apply + saringan gate.
 * Live MySQL per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class AwamPermohonanTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
        (new Batch8MastersSeeder)->run();
        (new TestUsersSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        // Remove any factory-created awam test users
        User::where('email', 'like', self::TAG.'.awam%@test.local')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->pluck('id');
        TemuJanji::whereIn('id_khidmat_nasihat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
        SlotTemuJanji::whereHas('cawangan', fn ($q) => $q->where('nama', 'like', self::TAG.'%'))->delete();
    }

    private function makeAwamUser(string $suffix = 'a'): User
    {
        $email = self::TAG.'.awam.'.$suffix.'@test.local';
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => self::TAG.' Awam '.strtoupper($suffix),
                'user_type' => User::TYPE_AWAM,
                'password' => bcrypt('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );
        $user->syncRoles(['awam']);

        return $user;
    }

    private function bookableDate(): Carbon
    {
        $d = Carbon::today()->addDays(10);
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }

    private function seedBranchWithSlot(): array
    {
        $branch = Cawangan::create([
            'jenis' => 'JBG',
            'kod' => 'TST',
            'nama' => self::TAG.' Awam Branch',
            'negeri_id' => 16,
            'status_aktif' => true,
        ]);

        $date = $this->bookableDate()->toDateString();

        SlotTemuJanji::create([
            'cawangan_id' => $branch->id,
            'tarikh_slot' => $date,
            'masa_mula' => '09:00',
            'masa_akhir' => '09:30',
            'is_temujanji' => false,
            'status_aktif' => true,
        ]);

        return [$branch, $date, '09:00'];
    }

    public function test_citizen_cannot_view_another_citizens_application(): void
    {
        $citizenA = $this->makeAwamUser('a');
        $citizenB = $this->makeAwamUser('b');

        $kn = KhidmatNasihat::create([
            'no_permohonan' => self::TAG.'-OWN-'.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa A',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna' => $citizenA->id,
            'cawangan_id' => Cawangan::where('status_aktif', true)->value('id'),
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
        ]);

        $this->actingAs($citizenB)
            ->get(route('awam.permohonan.show', $kn))
            ->assertStatus(403);
    }

    public function test_citizen_can_submit_diri_sendiri_application(): void
    {
        [$branch, $date, $time] = $this->seedBranchWithSlot();
        $citizen = $this->makeAwamUser('c');
        $kategori = RefKategoriKn::where('jenis_kategori', 'SIVIL')->firstOrFail();

        $this->actingAs($citizen)
            ->withSession(['awam_saringan' => ['jenis' => KhidmatNasihat::SARINGAN_SIVIL_SYARIAH, 'lulus' => true, 'sumbangan' => false]])
            ->post(route('awam.permohonan.store'), [
                'aksi' => 'hantar',
                'nama_mangsa' => self::TAG.' Mangsa Awam',
                'id_pengenalan_mangsa' => '900101015555',
                'cawangan_id' => $branch->id,
                'id_kategori' => $kategori->id,
                'jumlah_pendapatan' => 30000,
                'tarikh_temu_janji' => $date,
                'masa_temu_janji' => $time,
                'perakuan' => '1',
            ])
            ->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Awam')->firstOrFail();
        $this->assertSame(KhidmatNasihat::STATUS_BAHARU, $row->status_kn);
        $this->assertSame('DIRI_SENDIRI', $row->jenis_permohonan);
        $this->assertSame((int) $citizen->id, (int) $row->id_pengguna);
        $this->assertTrue($row->perakuan);
        $this->assertNotNull($row->id_temu_janji);
    }

    public function test_submit_without_saringan_is_blocked(): void
    {
        [$branch, $date, $time] = $this->seedBranchWithSlot();
        $citizen = $this->makeAwamUser('d');
        $kategori = RefKategoriKn::where('jenis_kategori', 'SIVIL')->firstOrFail();

        $this->actingAs($citizen)
            ->post(route('awam.permohonan.store'), [
                'aksi' => 'hantar',
                'nama_mangsa' => self::TAG.' Mangsa NoSaringan',
                'id_pengenalan_mangsa' => '900101015555',
                'cawangan_id' => $branch->id,
                'id_kategori' => $kategori->id,
                'tarikh_temu_janji' => $date,
                'masa_temu_janji' => $time,
                'perakuan' => '1',
            ])
            ->assertStatus(403);

        $this->assertNull(KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa NoSaringan')->first());
    }

    public function test_owner_can_view_own_application_renders_show(): void
    {
        $owner = $this->makeAwamUser('e');
        $kn = KhidmatNasihat::create([
            'no_permohonan' => self::TAG.'-SHOW-'.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa Show',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna' => $owner->id,
            'cawangan_id' => Cawangan::where('status_aktif', true)->value('id'),
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
            'is_percuma' => false,
            'perakuan' => true,
            'jumlah_bayaran' => 10,
        ]);

        $this->actingAs($owner)->get(route('awam.permohonan.show', $kn))->assertOk();
    }

    public function test_dashboard_renders_for_citizen(): void
    {
        $this->actingAs($this->makeAwamUser('f'))->get(route('awam.dashboard'))->assertOk();
    }

    public function test_create_form_renders_after_saringan(): void
    {
        $this->actingAs($this->makeAwamUser('g'))
            ->withSession(['awam_saringan' => ['jenis' => KhidmatNasihat::SARINGAN_SIVIL_SYARIAH, 'lulus' => true, 'sumbangan' => false]])
            ->get(route('awam.permohonan.create'))
            ->assertOk();
    }
}
