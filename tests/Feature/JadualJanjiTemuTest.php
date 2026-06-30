<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\PenutupanOperasi;
use App\Models\RefCuti;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Models\User;
use App\Support\CutiNegeri;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 10 slice 3 — Jadual Janji Temu (read-only month calendar).
 *
 * Renders booked temu_janji per branch on a month grid and marks days the
 * SlotAvailabilityService excludes (weekend / state holiday / operational
 * closure). Scenario month = July 2026. Live mysql per repo convention;
 * PHPUNIT-tagged rows cleaned up.
 */
class JadualJanjiTemuTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    /** Putrajaya — branch state, used for holiday encoding. */
    private const NEGERI_ID = 16;

    private const MONTH = '2026-07';
    private const BOOKED = '2026-07-15';   // Wednesday — a booked appointment
    private const HOLIDAY = '2026-07-13';  // Monday — ref_cuti for the branch state
    private const CLOSED = '2026-07-14';   // Tuesday — penutupan_operasi
    private const WEEKEND = '2026-07-11';  // Saturday

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
        // Cawangan delete cascades temu_janji + slot_temu_janji + penutupan_operasi.
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
        RefCuti::where('nama_cuti', 'like', self::TAG.'%')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Branch in Putrajaya + one booked appointment + a holiday + a closure. */
    private function seedBranch(): Cawangan
    {
        $cawangan = Cawangan::create([
            'jenis' => 'JBG',
            'nama' => self::TAG.' Jadual Branch',
            'negeri_id' => self::NEGERI_ID,
            'status_aktif' => true,
        ]);

        $slot = SlotTemuJanji::create([
            'cawangan_id' => $cawangan->id,
            'tarikh_slot' => self::BOOKED,
            'masa_mula' => '10:00',
            'masa_akhir' => '10:30',
            'is_temujanji' => true,
            'status_aktif' => true,
        ]);

        TemuJanji::create([
            'cawangan_id' => $cawangan->id,
            'slot_temu_janji_id' => $slot->id,
            'tarikh_temu_janji' => self::BOOKED,
            'masa_mula' => '10:00',
            'masa_akhir' => '10:30',
            'status' => 'DISAHKAN',
        ]);

        RefCuti::create([
            'nama_cuti' => self::TAG.' Cuti Jadual',
            'tarikh_mula' => self::HOLIDAY,
            'tarikh_tamat' => self::HOLIDAY,
            'idnegeri' => CutiNegeri::encode([self::NEGERI_ID]),
            'created' => '2026-06-30',
        ]);

        PenutupanOperasi::create([
            'cawangan_id' => $cawangan->id,
            'tarikh_mula' => self::CLOSED,
            'tarikh_tamat' => self::CLOSED,
            'sebab' => self::TAG.' tutup',
        ]);

        return $cawangan;
    }

    public function test_calendar_renders_booking_on_its_day(): void
    {
        $cawangan = $this->seedBranch();

        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('jadual.index', ['cawangan_id' => $cawangan->id, 'bulan' => self::MONTH]))
            ->assertOk()
            ->assertSee('DISAHKAN')        // the booking status chip
            ->assertSee('10:00');          // the booking time
    }

    public function test_calendar_marks_holiday_and_closure_days_closed(): void
    {
        $cawangan = $this->seedBranch();

        $html = $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('jadual.index', ['cawangan_id' => $cawangan->id, 'bulan' => self::MONTH]))
            ->assertOk()
            ->getContent();

        // Holiday + closure days carry the "is-closed" cell marker.
        foreach ([self::HOLIDAY, self::CLOSED, self::WEEKEND] as $date) {
            $day = (int) Carbon::parse($date)->format('j');
            $this->assertMatchesRegularExpression(
                '/data-day="'.$date.'"[^>]*class="[^"]*is-closed/',
                $html,
                "Expected {$date} (day {$day}) to be marked closed."
            );
        }
    }

    public function test_default_month_renders_without_explicit_filters(): void
    {
        $cawangan = $this->seedBranch();

        // No bulan param → controller defaults to current month, still 200.
        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('jadual.index', ['cawangan_id' => $cawangan->id]))
            ->assertOk();
    }

    public function test_lawyer_blocked(): void
    {
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('jadual.index'))
            ->assertStatus(302);
    }
}
