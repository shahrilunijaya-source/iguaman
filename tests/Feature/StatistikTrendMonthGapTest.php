<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regression: statistik "Trend Permohonan Bulanan" dropped zero-count months.
 * StatistikController::byBulan() grouped by month-with-data only, so a month
 * with no applications (e.g. July when Jun+Aug had cases) vanished from the
 * x-axis and the trend line jumped Jun → Aug, misrepresenting the series.
 *
 * Found by /qa on 2026-07-01.
 * Report: .gstack/qa-reports/qa-report-127-0-0-1-2026-07-01.md
 *
 * Runs against the LIVE mysql db (iguaman_2in1) per repo convention (see
 * EpicFStatistikExportTest). Case rows are tagged cawangan=PHPUNIT-TREND and
 * cleaned up so they never pollute the real spine.
 */
class StatistikTrendMonthGapTest extends TestCase
{
    private const TAG = 'PHPUNIT-TREND';

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
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        Form::where('cawangan', self::TAG)->delete();
    }

    private function seedCase(string $tarikh): Form
    {
        return Form::create([
            'cawangan' => self::TAG,
            'nama' => 'UJIAN TREND',
            'nokp' => '880808081234',
            'no_fail' => 'JBG.TREND.'.str_replace('-', '', $tarikh),
            'kategori_kes' => 'Sivil',
            'tarikh_permohonan' => $tarikh,
            'status' => 'Aktif',
            'diterima' => '',
            'created_at' => now(),
        ]);
    }

    public function test_trend_fills_zero_count_months_inside_the_active_range(): void
    {
        // Cases in Jun and Aug 2026 but NOT July → latest month becomes 2026-08,
        // so the 12-month window is 2025-09 .. 2026-08 and MUST include 2026-07.
        $this->seedCase('2026-06-10');
        $this->seedCase('2026-08-10');

        $this->actingAs(User::where('email', 'admin@test.local')->firstOrFail())
            ->get(route('statistik.index'))
            ->assertOk()
            ->assertViewHas('byBulan', function (array $byBulan): bool {
                // Exactly 12 contiguous monthly buckets.
                if (count($byBulan) !== 12) {
                    return false;
                }
                // The gap month is present and zero (the actual regression).
                if (! array_key_exists('2026-07', $byBulan) || $byBulan['2026-07'] !== 0) {
                    return false;
                }
                // Neighbours carry the seeded cases.
                if (($byBulan['2026-06'] ?? 0) < 1 || ($byBulan['2026-08'] ?? 0) < 1) {
                    return false;
                }
                // Keys are strictly consecutive months, newest → oldest.
                $keys = array_keys($byBulan);
                for ($i = 1; $i < count($keys); $i++) {
                    $expected = date('Y-m', strtotime($keys[$i - 1].'-01 -1 month'));
                    if ($keys[$i] !== $expected) {
                        return false;
                    }
                }

                return true;
            });
    }
}
