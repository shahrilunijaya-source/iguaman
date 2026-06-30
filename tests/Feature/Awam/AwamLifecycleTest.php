<?php

namespace Tests\Feature\Awam;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
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
 * Batch 13 Slice C1 — citizen cancel + reschedule of KN appointment.
 * Live MySQL per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class AwamLifecycleTest extends TestCase
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
        $branchIds = Cawangan::where('nama', 'like', self::TAG.'%')->pluck('id');
        SlotTemuJanji::whereIn('cawangan_id', $branchIds)->delete();
        Cawangan::whereIn('id', $branchIds)->delete();
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

        $slot = SlotTemuJanji::create([
            'cawangan_id' => $branch->id,
            'tarikh_slot' => $date,
            'masa_mula' => '09:00',
            'masa_akhir' => '09:30',
            'is_temujanji' => false,
            'status_aktif' => true,
        ]);

        return [$branch, $date, '09:00', $slot];
    }

    /**
     * Create a booked KN owned by $user at the given branch/date/time.
     */
    private function makeBookedKn(User $user, Cawangan $branch, string $date, string $time): KhidmatNasihat
    {
        $kn = KhidmatNasihat::create([
            'no_permohonan' => self::TAG.'-LC-'.uniqid(),
            'nama_mangsa' => self::TAG.' Mangsa LC',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna' => $user->id,
            'cawangan_id' => $branch->id,
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
            'is_percuma' => false,
            'perakuan' => true,
            'jumlah_bayaran' => 0,
        ]);

        app(\App\Support\KhidmatNasihatService::class)->bookSlot($kn, $date, $time, $user->name);

        return $kn->fresh();
    }

    public function test_citizen_can_cancel_future_appointment(): void
    {
        [$branch, $date, $time, $slot] = $this->seedBranchWithSlot();
        $citizen = $this->makeAwamUser('lc1');
        $kn = $this->makeBookedKn($citizen, $branch, $date, $time);

        $this->assertNotNull($kn->id_temu_janji, 'Precondition: KN should have a temu janji booked.');

        $this->actingAs($citizen)
            ->post(route('awam.permohonan.batal', $kn))
            ->assertRedirect(route('awam.permohonan.show', $kn));

        $slot->refresh();
        $this->assertFalse((bool) $slot->is_temujanji, 'Slot should be freed after cancel.');
        $this->assertSame(KhidmatNasihat::STATUS_BATAL, $kn->fresh()->status_kn, 'KN status should be BATAL.');
    }

    public function test_citizen_can_reschedule_to_new_slot(): void
    {
        [$branch, $date, $time, $slot1] = $this->seedBranchWithSlot();
        $citizen = $this->makeAwamUser('lc2');
        $kn = $this->makeBookedKn($citizen, $branch, $date, $time);

        // Seed a second slot at a different time on the same date.
        $newDate = $this->bookableDate()->addDays(1);
        while ($newDate->isWeekend()) {
            $newDate->addDay();
        }
        $slot2 = SlotTemuJanji::create([
            'cawangan_id' => $branch->id,
            'tarikh_slot' => $newDate->toDateString(),
            'masa_mula' => '10:00',
            'masa_akhir' => '10:30',
            'is_temujanji' => false,
            'status_aktif' => true,
        ]);

        $this->actingAs($citizen)
            ->post(route('awam.permohonan.reschedule', $kn), [
                'tarikh_temu_janji' => $newDate->toDateString(),
                'masa_temu_janji' => '10:00',
            ])
            ->assertRedirect(route('awam.permohonan.show', $kn));

        $slot1->refresh();
        $slot2->refresh();
        $this->assertFalse((bool) $slot1->is_temujanji, 'Old slot should be freed.');
        $this->assertTrue((bool) $slot2->is_temujanji, 'New slot should be booked.');
    }

    public function test_cannot_cancel_other_citizens_appointment(): void
    {
        [$branch, $date, $time] = $this->seedBranchWithSlot();
        $owner = $this->makeAwamUser('lc3');
        $other = $this->makeAwamUser('lc4');
        $kn = $this->makeBookedKn($owner, $branch, $date, $time);

        $this->actingAs($other)
            ->post(route('awam.permohonan.batal', $kn))
            ->assertStatus(403);
    }
}
