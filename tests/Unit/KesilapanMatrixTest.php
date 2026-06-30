<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Support\KesilapanMatrix;
use App\Support\SlaMatrix;
use App\Support\WideExport;
use Tests\TestCase;

/**
 * P1 — Kesilapan Nombor Fail per-month matrix pivot + export columns. Pure (no DB).
 */
class KesilapanMatrixTest extends TestCase
{
    public function test_pivot_counts_by_branch_and_month_with_totals(): void
    {
        $rows = [
            (object) ['cawangan' => 'JBG PERLIS', 'bulan' => 1, 'n' => 2],
            (object) ['cawangan' => 'JBG PERLIS', 'bulan' => 3, 'n' => 1],
            (object) ['cawangan' => 'JBG KEDAH', 'bulan' => 1, 'n' => 4],
            (object) ['cawangan' => 'JBG NOWHERE', 'bulan' => 1, 'n' => 9], // not in list → ignored
        ];

        $out = KesilapanMatrix::pivot($rows, ['JBG PERLIS', 'JBG KEDAH']);

        $this->assertSame(2, $out['matrix']['JBG PERLIS'][1]);
        $this->assertSame(1, $out['matrix']['JBG PERLIS'][3]);
        $this->assertSame(0, $out['matrix']['JBG PERLIS'][2]);
        $this->assertSame(3, $out['matrix']['JBG PERLIS']['jumlah']);
        $this->assertSame(4, $out['matrix']['JBG KEDAH']['jumlah']);

        $this->assertSame(6, $out['bulanan'][1]); // 2 + 4
        $this->assertSame(1, $out['bulanan'][3]);
        $this->assertSame(7, $out['grand']);      // unknown branch excluded
    }

    public function test_pivot_seeds_every_branch_zeroed(): void
    {
        $out = KesilapanMatrix::pivot([], SlaMatrix::BRANCHES);

        $this->assertCount(23, $out['matrix']);
        $this->assertSame(0, $out['grand']);
        $this->assertSame(0, $out['matrix']['JBG SIBU']['jumlah']);
    }

    public function test_export_has_36_columns_and_alasan(): void
    {
        $cols = WideExport::kesilapanColumns();
        $this->assertCount(36, $cols);

        $labels = array_map(fn ($c) => $c[0], $cols);
        $this->assertContains('ALASAN KESILAPAN NOMBOR FAIL', $labels);
        $this->assertContains('NO. KAD PENGENALAN', $labels);
    }

    public function test_export_row_prepends_bil_and_formats_nokp(): void
    {
        $r = new Form([
            'cawangan' => 'JBG PERLIS', 'no_fail' => 'JBG.X', 'nokp' => '900101-01-5555',
            'status' => 'Fail Tutup', 'alasan_kesilapan_no_fail' => 'Tersilap kunci',
        ]);

        $row = WideExport::kesilapanRow($r, 3);

        $this->assertSame(3, $row[0]);
        $this->assertSame('JBG PERLIS', $row[1]);
        $this->assertContains('="900101015555"', $row);
        $this->assertContains('Tersilap kunci', $row);
        $this->assertCount(1 + 36, $row);
    }
}
