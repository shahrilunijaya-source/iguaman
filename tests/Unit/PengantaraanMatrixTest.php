<?php

namespace Tests\Unit;

use App\Support\PengantaraanMatrix;
use Tests\TestCase;

/**
 * P1 (rk-pengantaraan slice 2) — penugasan assignment matrix pivots. Pure (no DB).
 */
class PengantaraanMatrixTest extends TestCase
{
    public function test_branch_axis_and_month_labels(): void
    {
        $this->assertCount(23, PengantaraanMatrix::BRANCHES);
        $this->assertSame('JBG WP PUTRAJAYA', PengantaraanMatrix::BRANCHES[0]);
        $this->assertCount(12, PengantaraanMatrix::BULAN);
        $this->assertSame('Jan', PengantaraanMatrix::BULAN[1]);
        $this->assertSame('Dis', PengantaraanMatrix::BULAN[12]);
    }

    public function test_pivot_kategori_counts_and_totals(): void
    {
        $rows = [
            (object) ['cawangan' => 'JBG PERLIS', 'sivil' => 3, 'syariah' => 2, 'jumlah' => 5],
            (object) ['cawangan' => 'JBG NOWHERE', 'sivil' => 9, 'syariah' => 9, 'jumlah' => 18], // not in axis → ignored
        ];

        $out = PengantaraanMatrix::pivotKategori($rows, ['JBG PERLIS', 'JBG KEDAH']);

        $this->assertSame(3, $out['matrix']['JBG PERLIS']['sivil']);
        $this->assertSame(2, $out['matrix']['JBG PERLIS']['syariah']);
        $this->assertSame(5, $out['matrix']['JBG PERLIS']['jumlah']);
        $this->assertSame(0, $out['matrix']['JBG KEDAH']['jumlah']);
        $this->assertSame(3, $out['total']['sivil']);   // unknown branch excluded
        $this->assertSame(5, $out['total']['jumlah']);
    }

    public function test_pivot_bulanan_sums_months_into_jumlah(): void
    {
        $o = (object) ['cawangan' => 'JBG PERLIS'];
        for ($m = 1; $m <= 12; $m++) {
            $o->{"b{$m}"} = 0;
        }
        $o->b3 = 4;
        $o->b7 = 1;

        $out = PengantaraanMatrix::pivotBulanan([$o], ['JBG PERLIS', 'JBG KEDAH']);

        $this->assertSame(4, $out['matrix']['JBG PERLIS'][3]);
        $this->assertSame(1, $out['matrix']['JBG PERLIS'][7]);
        $this->assertSame(0, $out['matrix']['JBG PERLIS'][1]);
        $this->assertSame(5, $out['matrix']['JBG PERLIS']['jumlah']); // 4 + 1
        $this->assertSame(0, $out['matrix']['JBG KEDAH']['jumlah']);
        $this->assertSame(4, $out['bulanan'][3]);
        $this->assertSame(5, $out['grand']);
    }

    public function test_pivot_bulanan_seeds_every_branch_zeroed(): void
    {
        $out = PengantaraanMatrix::pivotBulanan([], PengantaraanMatrix::BRANCHES);

        $this->assertCount(23, $out['matrix']);
        $this->assertSame(0, $out['grand']);
        $this->assertSame(0, $out['matrix']['JBG SIBU']['jumlah']);
    }
}
