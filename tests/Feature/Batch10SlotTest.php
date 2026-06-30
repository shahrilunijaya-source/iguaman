<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\PenutupanOperasi;
use App\Models\RefCuti;
use App\Models\SlotTemuJanji;
use App\Models\User;
use App\Support\CutiNegeri;
use App\Support\SlotAvailabilityService;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 10 slice 1 — SlotAvailabilityService rules + slot JSON endpoints.
 *
 * Reference "today" is fixed to Mon 2026-07-06 so the 4-working-day window is
 * deterministic: earliest bookable = Fri 2026-07-10.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch10SlotTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    /** Putrajaya — exists in ref_negeri; used for the branch state + holiday encoding. */
    private const NEGERI_ID = 16;

    private const TODAY = '2026-07-06';        // Monday
    private const EARLIEST = '2026-07-10';     // Friday (= Mon + 4 working days)
    private const BELOW_WINDOW = '2026-07-08'; // Wednesday (only 2 working days ahead)
    private const WEEKEND = '2026-07-11';      // Saturday
    private const HOLIDAY = '2026-07-13';      // Monday (ref_cuti)
    private const CLOSED = '2026-07-14';       // Tuesday (penutupan_operasi)
    private const OPEN = '2026-07-15';         // Wednesday (fully bookable)

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
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        // Cawangan delete cascades slot_temu_janji + penutupan_operasi (cawangan_id FK).
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
        RefCuti::where('nama_cuti', 'like', self::TAG.'%')->delete();
    }

    private function today(): Carbon
    {
        return Carbon::parse(self::TODAY)->startOfDay();
    }

    /** A tagged branch + an open slot on each scenario date. */
    private function seedBranchWithSlots(): Cawangan
    {
        $cawangan = Cawangan::create([
            'jenis' => 'JBG',
            'nama' => self::TAG.' Slot Branch',
            'negeri_id' => self::NEGERI_ID,
            'status_aktif' => true,
        ]);

        foreach ([self::BELOW_WINDOW, self::EARLIEST, self::WEEKEND, self::HOLIDAY, self::CLOSED, self::OPEN] as $date) {
            SlotTemuJanji::create([
                'cawangan_id' => $cawangan->id,
                'tarikh_slot' => $date,
                'masa_mula' => '09:00',
                'masa_akhir' => '09:30',
                'is_temujanji' => false,
                'status_aktif' => true,
            ]);
        }

        // Public holiday on HOLIDAY for the branch state (Putrajaya).
        RefCuti::create([
            'nama_cuti' => self::TAG.' Cuti Ujian',
            'tarikh_mula' => self::HOLIDAY,
            'tarikh_tamat' => self::HOLIDAY,
            'idnegeri' => CutiNegeri::encode([self::NEGERI_ID]),
            'created' => self::TODAY,
        ]);

        // Operational closure covering CLOSED.
        PenutupanOperasi::create([
            'cawangan_id' => $cawangan->id,
            'tarikh_mula' => self::CLOSED,
            'tarikh_tamat' => self::CLOSED,
            'sebab' => 'Ujian penutupan',
        ]);

        return $cawangan;
    }

    private function service(): SlotAvailabilityService
    {
        return app(SlotAvailabilityService::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ---- SlotAvailabilityService::availableDates ----

    public function test_available_dates_apply_every_rule(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        $dates = $this->service()->availableDates($cawangan->id, null, 30, $this->today());

        // Included: earliest bookable + a later fully-open weekday.
        $this->assertContains(self::EARLIEST, $dates);
        $this->assertContains(self::OPEN, $dates);

        // Excluded by each rule.
        $this->assertNotContains(self::BELOW_WINDOW, $dates, 'below 4-working-day window');
        $this->assertNotContains(self::WEEKEND, $dates, 'weekend');
        $this->assertNotContains(self::HOLIDAY, $dates, 'public holiday for branch state');
        $this->assertNotContains(self::CLOSED, $dates, 'operational closure');
    }

    public function test_date_with_no_open_slot_is_excluded(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        // Book (toggle) the earliest slot -> it must drop out of availability.
        SlotTemuJanji::where('cawangan_id', $cawangan->id)
            ->whereDate('tarikh_slot', self::EARLIEST)
            ->update(['is_temujanji' => true]);

        $dates = $this->service()->availableDates($cawangan->id, null, 30, $this->today());

        $this->assertNotContains(self::EARLIEST, $dates);
        $this->assertContains(self::OPEN, $dates);
    }

    public function test_holiday_blocks_only_matching_state(): void
    {
        // Branch in a DIFFERENT state (Selangor=10) must NOT be blocked by a Putrajaya holiday.
        $cawangan = Cawangan::create([
            'jenis' => 'JBG',
            'nama' => self::TAG.' Other State',
            'negeri_id' => 10,
            'status_aktif' => true,
        ]);
        SlotTemuJanji::create([
            'cawangan_id' => $cawangan->id, 'tarikh_slot' => self::HOLIDAY,
            'masa_mula' => '09:00', 'masa_akhir' => '09:30',
            'is_temujanji' => false, 'status_aktif' => true,
        ]);
        RefCuti::create([
            'nama_cuti' => self::TAG.' Cuti Putrajaya',
            'tarikh_mula' => self::HOLIDAY, 'tarikh_tamat' => self::HOLIDAY,
            'idnegeri' => CutiNegeri::encode([self::NEGERI_ID]), 'created' => self::TODAY,
        ]);

        $dates = $this->service()->availableDates($cawangan->id, null, 30, $this->today());

        $this->assertContains(self::HOLIDAY, $dates); // not a holiday for Selangor
    }

    // ---- SlotAvailabilityService::availableTimes ----

    public function test_available_times_for_open_date(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        $times = $this->service()->availableTimes($cawangan->id, self::OPEN, $this->today());
        $this->assertSame(['09:00'], $times);
    }

    public function test_available_times_empty_for_blocked_date(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        $this->assertSame([], $this->service()->availableTimes($cawangan->id, self::HOLIDAY, $this->today()));
        $this->assertSame([], $this->service()->availableTimes($cawangan->id, self::WEEKEND, $this->today()));
        $this->assertSame([], $this->service()->availableTimes($cawangan->id, self::BELOW_WINDOW, $this->today()));
    }

    // ---- JSON endpoint + permission gate ----

    public function test_endpoint_returns_dates_json(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        $this->actingAs($this->user('pegawai@test.local'))
            ->getJson(route('slot.tarikh', ['cawangan_id' => $cawangan->id]))
            ->assertOk()
            ->assertJsonStructure(['dates']);
    }

    public function test_endpoint_returns_times_json(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        $this->actingAs($this->user('pegawai@test.local'))
            ->getJson(route('slot.masa', ['cawangan_id' => $cawangan->id, 'tarikh' => self::OPEN]))
            ->assertOk()
            ->assertJsonStructure(['times']);
    }

    public function test_endpoint_permission_gate_blocks_lawyer(): void
    {
        $cawangan = $this->seedBranchWithSlots();

        // peguam (lawyer) lacks slot.view -> redirected by the permission middleware.
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('slot.tarikh', ['cawangan_id' => $cawangan->id]))
            ->assertStatus(302);
    }
}
