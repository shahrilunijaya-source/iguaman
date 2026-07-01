<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\PenutupanOperasi;
use App\Models\RefCuti;
use App\Models\SlotTemuJanji;
use App\Models\User;
use App\Support\CutiNegeri;
use App\Support\SlotGenerator;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 10 slice 2 — SlotGenerator + penutupan operasi CRUD + per-branch session config.
 *
 * Generation range Mon 2026-08-03 → Sun 2026-08-09. With the default Sat/Sun weekend,
 * a holiday on Wed (08-05) and a closure on Thu (08-06), the only working days are
 * Mon/Tue/Fri (3 days). Window 09:00–11:00 @ 30 min = 4 slots/day → 12 slots.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch10SlotGenTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    private const NEGERI_ID = 16; // Putrajaya

    private const FROM = '2026-08-03';      // Monday

    private const TO = '2026-08-09';        // Sunday

    private const HOLIDAY = '2026-08-05';   // Wednesday

    private const CLOSED = '2026-08-06';    // Thursday

    private const SATURDAY = '2026-08-08';

    private const SUNDAY = '2026-08-09';

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
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
        RefCuti::where('nama_cuti', 'like', self::TAG.'%')->delete();
    }

    private function generator(): SlotGenerator
    {
        return app(SlotGenerator::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** A tagged branch with a 09:00–11:00 / 30-min window. */
    private function branch(array $overrides = []): Cawangan
    {
        return Cawangan::create(array_merge([
            'jenis' => 'JBG',
            'nama' => self::TAG.' Gen Branch '.uniqid(),
            'negeri_id' => self::NEGERI_ID,
            'masa_buka' => '09:00',
            'masa_tutup' => '11:00',
            'tempoh_slot_minit' => 30,
            'status_aktif' => true,
        ], $overrides));
    }

    private function seedHolidayAndClosure(Cawangan $cawangan): void
    {
        RefCuti::create([
            'nama_cuti' => self::TAG.' Cuti Gen',
            'tarikh_mula' => self::HOLIDAY,
            'tarikh_tamat' => self::HOLIDAY,
            'idnegeri' => CutiNegeri::encode([self::NEGERI_ID]),
            'created' => self::FROM,
        ]);

        PenutupanOperasi::create([
            'cawangan_id' => $cawangan->id,
            'tarikh_mula' => self::CLOSED,
            'tarikh_tamat' => self::CLOSED,
            'sebab' => self::TAG.' tutup',
        ]);
    }

    // ---- SlotGenerator ----

    public function test_generates_correct_count_skipping_weekend_holiday_closure(): void
    {
        $cawangan = $this->branch();
        $this->seedHolidayAndClosure($cawangan);

        $result = $this->generator()->generate($cawangan, null, self::FROM, self::TO);

        // Working days = Mon, Tue, Fri (Wed=holiday, Thu=closed, Sat/Sun=weekend). 4 slots/day.
        $this->assertSame(12, $result['created']);

        $this->assertSame(0, SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::HOLIDAY)->count(), 'holiday skipped');
        $this->assertSame(0, SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::CLOSED)->count(), 'closure skipped');
        $this->assertSame(0, SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::SATURDAY)->count(), 'saturday skipped');
        $this->assertSame(0, SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::SUNDAY)->count(), 'sunday skipped');
        $this->assertSame(4, SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::FROM)->count(), 'monday has 4 slots');
    }

    public function test_respects_window_and_tempoh(): void
    {
        $cawangan = $this->branch(['masa_buka' => '08:00', 'masa_tutup' => '09:00', 'tempoh_slot_minit' => 15]);

        // Single Monday only — clean working day.
        $result = $this->generator()->generate($cawangan, null, self::FROM, self::FROM);

        // 08:00–09:00 @ 15 min = 4 slots (08:00, 08:15, 08:30, 08:45).
        $this->assertSame(4, $result['created']);

        $times = SlotTemuJanji::where('cawangan_id', $cawangan->id)->orderBy('masa_mula')->pluck('masa_mula')
            ->map(fn ($t) => Carbon::parse($t)->format('H:i'))->all();
        $this->assertSame(['08:00', '08:15', '08:30', '08:45'], $times);
    }

    public function test_generation_is_idempotent(): void
    {
        $cawangan = $this->branch();
        $this->seedHolidayAndClosure($cawangan);

        $first = $this->generator()->generate($cawangan, null, self::FROM, self::TO);
        $second = $this->generator()->generate($cawangan, null, self::FROM, self::TO);

        $this->assertSame(12, $first['created']);
        $this->assertSame(0, $second['created'], 're-run creates nothing');
        $this->assertSame(12, $second['existing'], 're-run sees all as existing');
        $this->assertSame(12, SlotTemuJanji::where('cawangan_id', $cawangan->id)->count(), 'no duplicates');
    }

    public function test_per_branch_weekend_blocks_friday_not_sunday(): void
    {
        // hari_minggu = "5,6" (Fri/Sat). Generate Fri 08-07 + Sat 08-08 + Sun 08-09.
        $cawangan = $this->branch(['hari_minggu' => '5,6']);

        $this->generator()->generate($cawangan, null, '2026-08-07', self::SUNDAY);

        $friday = SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', '2026-08-07')->count();
        $saturday = SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::SATURDAY)->count();
        $sunday = SlotTemuJanji::where('cawangan_id', $cawangan->id)->whereDate('tarikh_slot', self::SUNDAY)->count();

        $this->assertSame(0, $friday, 'Friday blocked by branch weekend config');
        $this->assertSame(0, $saturday, 'Saturday blocked by branch weekend config');
        $this->assertGreaterThan(0, $sunday, 'Sunday is a working day for this branch');
    }

    // ---- Penutupan Operasi CRUD + gate ----

    public function test_penutupan_store_and_destroy(): void
    {
        $cawangan = $this->branch();

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('penutupan.store'), [
                'cawangan_id' => $cawangan->id,
                'tarikh_mula' => self::CLOSED,
                'tarikh_tamat' => self::CLOSED,
                'sebab' => self::TAG.' ujian',
            ])
            ->assertRedirect(route('penutupan.index'));

        $row = PenutupanOperasi::where('cawangan_id', $cawangan->id)->first();
        $this->assertNotNull($row);

        $this->actingAs($this->user('pembantu@test.local'))
            ->delete(route('penutupan.destroy', $row))
            ->assertRedirect(route('penutupan.index'));

        $this->assertNull(PenutupanOperasi::find($row->id));
    }

    public function test_penutupan_gate_blocks_lawyer(): void
    {
        // peguam lacks slot.manage -> redirected by the permission middleware.
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('penutupan.index'))
            ->assertStatus(302);
    }

    public function test_slot_generation_gate_blocks_lawyer(): void
    {
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('slot.index'))
            ->assertStatus(302);
    }

    public function test_generate_endpoint_creates_slots(): void
    {
        $cawangan = $this->branch();

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('slot.generate'), [
                'cawangan_id' => $cawangan->id,
                'from' => self::FROM,
                'to' => self::FROM, // single Monday
            ])
            ->assertRedirect();

        $this->assertSame(4, SlotTemuJanji::where('cawangan_id', $cawangan->id)->count());
    }

    public function test_destroy_endpoint_removes_unbooked_only(): void
    {
        $cawangan = $this->branch();
        $this->generator()->generate($cawangan, null, self::FROM, self::FROM); // 4 slots Monday

        // Book one slot — it must survive teardown.
        SlotTemuJanji::where('cawangan_id', $cawangan->id)->orderBy('masa_mula')->first()
            ->update(['is_temujanji' => true]);

        $this->actingAs($this->user('pembantu@test.local'))
            ->delete(route('slot.destroy'), [
                'cawangan_id' => $cawangan->id,
                'from' => self::FROM,
                'to' => self::FROM,
            ])
            ->assertRedirect();

        $this->assertSame(1, SlotTemuJanji::where('cawangan_id', $cawangan->id)->count(), 'booked slot kept');
        $this->assertSame(1, SlotTemuJanji::where('cawangan_id', $cawangan->id)->where('is_temujanji', true)->count());
    }
}
