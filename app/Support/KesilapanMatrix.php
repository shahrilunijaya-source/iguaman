<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kesilapan Penjanaan Nombor Fail — per-branch × month count matrix
 * (P1, legacy cetakan_statistik_kesilapan_penjanaan_nombor_fail.php).
 *
 * Counts files closed for a generated-number error, by MONTH(tarikh_perakuan),
 * over the fixed 23-branch list, with a JUMLAH column (per branch) + per-month
 * footer + grand total. All-branch aggregate (CawanganScope bypassed via the
 * raw query); route-gated to statistik viewers.
 */
class KesilapanMatrix
{
    public const MARKER_STATUS = 'Fail Tutup';

    public const MARKER_SEBAB = 'Kesilapan Menjana Nombor Fail';

    /** Compute the matrix for an optional year + kategori filter. */
    public static function compute(?int $year = null, ?string $kategori = null): array
    {
        $rows = DB::table('forms')
            ->selectRaw('cawangan, MONTH(tarikh_perakuan) AS bulan, COUNT(*) AS n')
            ->where('status', self::MARKER_STATUS)
            ->where('sebab_tutup_fail', self::MARKER_SEBAB)
            ->whereNotNull('tarikh_tutup_fail')
            ->whereNotNull('tarikh_perakuan')
            ->when($year, fn (Builder $q) => $q->whereYear('tarikh_perakuan', $year))
            ->when($kategori, fn (Builder $q) => $q->where('kategori_kes', $kategori))
            ->groupBy('cawangan', DB::raw('MONTH(tarikh_perakuan)'))
            ->get();

        return self::pivot($rows, SlaMatrix::BRANCHES);
    }

    /**
     * Pure pivot: fold {cawangan, bulan, n} rows onto branch × 12-month grid.
     * Returns ['matrix' => [branch => [1..12 => n, 'jumlah' => rowTotal]],
     *          'bulanan' => [1..12 => total], 'grand' => int].
     *
     * @param  iterable  $rows  rows with ->cawangan ->bulan (1-12) ->n
     */
    public static function pivot(iterable $rows, array $branches): array
    {
        $matrix = [];
        foreach ($branches as $b) {
            $matrix[$b] = array_fill(1, 12, 0) + ['jumlah' => 0];
        }

        foreach ($rows as $r) {
            $b = $r->cawangan;
            $m = (int) $r->bulan;
            if (! isset($matrix[$b]) || $m < 1 || $m > 12) {
                continue;
            }
            $matrix[$b][$m] = (int) $r->n;
        }

        $bulanan = array_fill(1, 12, 0);
        $grand = 0;
        foreach ($branches as $b) {
            $row = 0;
            for ($m = 1; $m <= 12; $m++) {
                $row += $matrix[$b][$m];
                $bulanan[$m] += $matrix[$b][$m];
            }
            $matrix[$b]['jumlah'] = $row;
            $grand += $row;
        }

        return ['matrix' => $matrix, 'bulanan' => $bulanan, 'grand' => $grand];
    }
}
