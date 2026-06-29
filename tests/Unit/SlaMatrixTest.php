<?php

namespace Tests\Unit;

use App\Support\SlaMatrix;
use Tests\TestCase;

/**
 * EPIC F — per-branch SLA matrix math. Pure (no DB): exercises the pivot,
 * percentage and definition wiring with synthetic grouped rows.
 */
class SlaMatrixTest extends TestCase
{
    public function test_peratus_rounds_and_guards_zero(): void
    {
        $this->assertSame(80.0, SlaMatrix::peratus(8, 10));
        $this->assertSame(33.33, SlaMatrix::peratus(1, 3));
        $this->assertSame(100.0, SlaMatrix::peratus(5, 5));
        $this->assertNull(SlaMatrix::peratus(0, 0));
    }

    public function test_five_dashboards_with_expected_targets(): void
    {
        $defs = SlaMatrix::definitions();

        $this->assertSame(
            ['perakuan', 'fail-tiada', 'fail-terlibat', 'serahan', 'khidmat'],
            array_keys($defs)
        );

        $this->assertSame(40, $defs['perakuan']['target']);
        $this->assertSame(60, $defs['fail-tiada']['target']);
        $this->assertSame(120, $defs['fail-terlibat']['target']);
        $this->assertSame(7, $defs['serahan']['target']);
        $this->assertSame(60, $defs['khidmat']['target']);

        foreach ($defs as $def) {
            $this->assertNotEmpty($def['start']);
            $this->assertNotEmpty($def['end']);
            $this->assertIsCallable($def['filter']);
        }
    }

    public function test_branch_list_is_fixed_23(): void
    {
        $this->assertCount(23, SlaMatrix::BRANCHES);
        $this->assertSame('JBG WP PUTRAJAYA', SlaMatrix::BRANCHES[0]);
        $this->assertContains('JBG SIBU', SlaMatrix::BRANCHES);
    }

    public function test_pivot_places_cells_and_totals_and_ignores_unknown_branch(): void
    {
        $branches = ['JBG PERLIS', 'JBG KEDAH'];
        $kategori = ['Sivil', 'Syariah'];

        $grouped = [
            (object) ['cawangan' => 'JBG PERLIS', 'jenis' => 'Sivil', 'capai' => 8, 'tidak' => 2],
            (object) ['cawangan' => 'JBG PERLIS', 'jenis' => 'Syariah', 'capai' => 3, 'tidak' => 1],
            (object) ['cawangan' => 'JBG KEDAH', 'jenis' => 'Sivil', 'capai' => 0, 'tidak' => 4],
            // Unknown branch must be dropped (legacy fixed-list behaviour).
            (object) ['cawangan' => 'JBG NOWHERE', 'jenis' => 'Sivil', 'capai' => 99, 'tidak' => 99],
        ];

        $out = SlaMatrix::pivot($grouped, $branches, $kategori);

        // Cell values + percentage.
        $this->assertSame(8, $out['matrix']['JBG PERLIS']['Sivil']['capai']);
        $this->assertSame(80.0, $out['matrix']['JBG PERLIS']['Sivil']['peratus']);
        $this->assertSame(0.0, $out['matrix']['JBG KEDAH']['Sivil']['peratus']);

        // Untouched cell stays zeroed with null percentage.
        $this->assertSame(0, $out['matrix']['JBG KEDAH']['Syariah']['capai']);
        $this->assertNull($out['matrix']['JBG KEDAH']['Syariah']['peratus']);

        // Per-kategori JUMLAH down the Sivil column = (8+0) capai / (10+4) total.
        $this->assertSame(8, $out['jumlah']['Sivil']['capai']);
        $this->assertSame(14, $out['jumlah']['Sivil']['total']);
        $this->assertSame(57.14, $out['jumlah']['Sivil']['peratus']);

        // Grand total ignores the unknown branch entirely.
        $this->assertSame(11, $out['grand']['capai']); // 8 + 3 + 0
        $this->assertSame(18, $out['grand']['total']); // 10 + 4 + 4
    }

    public function test_pivot_seeds_every_branch_and_kategori(): void
    {
        $out = SlaMatrix::pivot([], SlaMatrix::BRANCHES, SlaMatrix::KATEGORI);

        $this->assertCount(23, $out['matrix']);
        foreach (SlaMatrix::BRANCHES as $b) {
            foreach (SlaMatrix::KATEGORI as $k) {
                $this->assertSame(0, $out['matrix'][$b][$k]['total']);
            }
        }
        $this->assertNull($out['grand']['peratus']);
    }
}
