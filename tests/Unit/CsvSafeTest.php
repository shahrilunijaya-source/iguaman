<?php

namespace Tests\Unit;

use App\Support\CsvSafe;
use PHPUnit\Framework\TestCase;

/** INJ-03 — spreadsheet formula-injection neutralization. */
class CsvSafeTest extends TestCase
{
    public function test_formula_prefixes_are_quoted(): void
    {
        $this->assertSame("'=1+1", CsvSafe::cell('=1+1'));
        $this->assertSame("'+44123", CsvSafe::cell('+44123'));
        $this->assertSame("'-5", CsvSafe::cell('-5'));
        $this->assertSame("'@SUM(A1)", CsvSafe::cell('@SUM(A1)'));
        $this->assertSame("'=cmd|'/c calc'!A1", CsvSafe::cell("=cmd|'/c calc'!A1"));
    }

    public function test_safe_values_pass_through(): void
    {
        $this->assertSame('Ahmad bin Ali', CsvSafe::cell('Ahmad bin Ali'));
        $this->assertSame('900101015555', CsvSafe::cell('900101015555'));
        $this->assertSame('', CsvSafe::cell(null));
        $this->assertSame('', CsvSafe::cell(''));
        $this->assertSame('123', CsvSafe::cell(123));
    }

    public function test_row_maps_each_cell(): void
    {
        $this->assertSame(["'=a", 'b', "'-c"], CsvSafe::row(['=a', 'b', '-c']));
    }
}
