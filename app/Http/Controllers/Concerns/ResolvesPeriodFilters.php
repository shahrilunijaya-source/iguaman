<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * ARCH-04 / CODE-06 — shared statistik period parsing. The identical year()/month()
 * helpers were duplicated across StatistikSlaController, StatistikPengantaraanController
 * (and inline elsewhere); this is the one definition.
 */
trait ResolvesPeriodFilters
{
    /** Optional year filter (blank = all years, matching legacy). */
    protected function periodYear(Request $request): ?int
    {
        $year = $request->input('tahun');

        return ($year !== null && $year !== '') ? (int) $year : null;
    }

    /** Optional month filter 1-12 (blank = all months). */
    protected function periodMonth(Request $request): ?int
    {
        $month = (int) $request->input('bulan');

        return ($month >= 1 && $month <= 12) ? $month : null;
    }
}
