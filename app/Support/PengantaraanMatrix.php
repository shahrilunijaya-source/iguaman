<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Penugasan Pengantaraan statistic matrices (P1 — legacy
 * statistik_penugasan_pengantaraan + statistik_penugasan_bulanan_pengantaraan).
 * Two all-branch pivots over the fixed 23-branch list:
 *   - kategori(): branch × [Sivil, Syariah, Jumlah] assignment counts;
 *   - bulanan():  branch × 12 months + Jumlah.
 *
 * Ports the legacy admin (strict) variant: full hygiene gate, tarikh_perakuan as
 * the single date column for both the year filter and the month bucketing. Pure
 * integer counts (no percentage). Like SlaMatrix this deliberately bypasses
 * CawanganScope — the fixed 23-branch axis IS the report and every branch is
 * always shown; the routes are HQ-gated via permission:statistik.view.
 *
 * Two legacy inconsistencies are corrected here (noted inline): the bulanan
 * kategori filter targets pengantaraan_kategori_kes (matching the row-set and
 * Matrix A) instead of the unrelated kategori_kes column, and JUMLAH is
 * consistently the sum of the 12 months.
 */
class PengantaraanMatrix
{
    /** Same canonical 23-branch axis as the SLA matrices (Putrajaya first). */
    public const BRANCHES = SlaMatrix::BRANCHES;

    /** Short month labels (legacy header order). */
    public const BULAN = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mac', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ogos', 9 => 'Sept', 10 => 'Okt', 11 => 'Nov', 12 => 'Dis',
    ];

    /** Shared row-set gate (legacy admin strict variant). */
    private static function gate(Builder $q, ?int $year): void
    {
        $q->where('status_pengantaraan', 'Ya')
            ->whereNotNull('pengantaraan_kategori_kes')->where('pengantaraan_kategori_kes', '!=', '')
            ->whereNotNull('no_fail')->whereRaw("TRIM(no_fail) != ''")->whereRaw("LOWER(no_fail) NOT LIKE '%null%'")
            ->whereNotNull('tarikh_perakuan')
            ->where(fn (Builder $w) => $w->whereNull('sebab_tutup_fail')->orWhere('sebab_tutup_fail', '!=', 'Kesilapan Menjana Nombor Fail'))
            ->when($year, fn (Builder $w, $v) => $w->whereYear('tarikh_perakuan', $v));
    }

    /** Branch × [sivil, syariah, jumlah] assignment-count matrix. */
    public static function kategori(?int $year = null): array
    {
        // pengantaraan_kategori_kes is stored lowercase ('sivil'/'syariah') in legacy.
        $q = DB::table('forms')->selectRaw(
            "cawangan,
             SUM(CASE WHEN LOWER(pengantaraan_kategori_kes) = 'sivil' THEN 1 ELSE 0 END) AS sivil,
             SUM(CASE WHEN LOWER(pengantaraan_kategori_kes) = 'syariah' THEN 1 ELSE 0 END) AS syariah,
             COUNT(*) AS jumlah"
        );
        self::gate($q, $year);
        $rows = $q->groupBy('cawangan')->get();

        return self::pivotKategori($rows, self::BRANCHES);
    }

    /** Branch × 12-month assignment-count matrix; optional sivil/syariah narrow. */
    public static function bulanan(?int $year = null, ?string $kategori = null): array
    {
        // Month indices come from a trusted range(), never request input.
        $months = implode(', ', array_map(
            fn ($m) => "SUM(CASE WHEN MONTH(tarikh_perakuan) = {$m} THEN 1 ELSE 0 END) AS b{$m}",
            range(1, 12)
        ));

        $q = DB::table('forms')->selectRaw("cawangan, {$months}");
        self::gate($q, $year);

        // Legacy filtered the unrelated kategori_kes column here; we filter
        // pengantaraan_kategori_kes so the cut reconciles with Matrix A.
        if (in_array(strtolower((string) $kategori), ['sivil', 'syariah'], true)) {
            $q->whereRaw('LOWER(pengantaraan_kategori_kes) = ?', [strtolower($kategori)]);
        }

        $rows = $q->groupBy('cawangan')->get();

        return self::pivotBulanan($rows, self::BRANCHES);
    }

    /**
     * Pure pivot for the kategori matrix.
     * Returns ['matrix' => [branch => [sivil,syariah,jumlah]], 'total' => [...]].
     */
    public static function pivotKategori(iterable $rows, array $branches): array
    {
        $matrix = [];
        foreach ($branches as $b) {
            $matrix[$b] = ['sivil' => 0, 'syariah' => 0, 'jumlah' => 0];
        }
        foreach ($rows as $r) {
            if (! isset($matrix[$r->cawangan])) {
                continue;
            }
            $matrix[$r->cawangan] = [
                'sivil' => (int) $r->sivil,
                'syariah' => (int) $r->syariah,
                'jumlah' => (int) $r->jumlah,
            ];
        }

        $total = ['sivil' => 0, 'syariah' => 0, 'jumlah' => 0];
        foreach ($matrix as $cell) {
            $total['sivil'] += $cell['sivil'];
            $total['syariah'] += $cell['syariah'];
            $total['jumlah'] += $cell['jumlah'];
        }

        return ['matrix' => $matrix, 'total' => $total];
    }

    /**
     * Pure pivot for the bulanan matrix; per-branch JUMLAH = sum of 12 months.
     * Returns ['matrix' => [branch => [1..12, 'jumlah']], 'bulanan' => [1..12], 'grand' => int].
     */
    public static function pivotBulanan(iterable $rows, array $branches): array
    {
        $matrix = [];
        foreach ($branches as $b) {
            $matrix[$b] = array_fill(1, 12, 0) + ['jumlah' => 0];
        }
        foreach ($rows as $r) {
            if (! isset($matrix[$r->cawangan])) {
                continue;
            }
            $row = [];
            $sum = 0;
            for ($m = 1; $m <= 12; $m++) {
                $n = (int) ($r->{"b{$m}"} ?? 0);
                $row[$m] = $n;
                $sum += $n;
            }
            $row['jumlah'] = $sum;
            $matrix[$r->cawangan] = $row;
        }

        $bulanan = array_fill(1, 12, 0);
        $grand = 0;
        foreach ($matrix as $cell) {
            for ($m = 1; $m <= 12; $m++) {
                $bulanan[$m] += $cell[$m];
            }
            $grand += $cell['jumlah'];
        }

        return ['matrix' => $matrix, 'bulanan' => $bulanan, 'grand' => $grand];
    }

    // ---- Pencapaian (KPI compliance funnel) ----------------------------

    /**
     * Broader gate for the KPI funnel (legacy laporan_pencapaian): hygiene only,
     * WITHOUT the status_pengantaraan filter — the funnel denominators count the
     * full universe of certified cases, the numerators are CASE subsets.
     */
    private static function gatePencapaian(Builder $q, ?int $year): void
    {
        $q->whereNotNull('no_fail')->whereRaw("TRIM(no_fail) != ''")->whereRaw("LOWER(no_fail) NOT LIKE '%null%'")
            ->whereNotNull('tarikh_perakuan')
            ->where(fn (Builder $w) => $w->whereNull('sebab_tutup_fail')->orWhere('sebab_tutup_fail', '!=', 'Kesilapan Menjana Nombor Fail'))
            ->when($year, fn (Builder $w, $v) => $w->whereYear('tarikh_perakuan', $v));
    }

    /**
     * Branch × 3-formula compliance matrix (legacy laporan_pencapaian_penugasan).
     * A four-stage funnel per branch — perakuan → penugasan → rujuk_minta →
     * selesai — with three consecutive-stage percentages:
     *   F1 = penugasan / perakuan, F2 = rujuk_minta / penugasan, F3 = selesai / rujuk_minta.
     *
     * Percentages are computed in PHP (legacy did it in SQL with NULLIF; we guard
     * denom>0 and show 0.0 like legacy). The on-screen file is canonical, so F2's
     * "sidang" numerator = setuju_pengantara='Ya' (the cetakan PDF adds a
     * status_sidang='Selesai' predicate — not ported). Period filter uses the
     * year on tarikh_perakuan (legacy offered a start/end date range; year keeps
     * it consistent with the sibling dashboards). kategori narrows by kategori_kes.
     */
    public static function pencapaian(?int $year = null, ?string $kategori = null): array
    {
        $q = DB::table('forms')->selectRaw(
            "cawangan,
             COUNT(*) AS perakuan,
             SUM(CASE WHEN status_pengantaraan = 'Ya' AND pengantaraan_kategori_kes IS NOT NULL AND pengantaraan_kategori_kes != '' THEN 1 ELSE 0 END) AS penugasan,
             SUM(CASE WHEN setuju_pengantara = 'Ya' THEN 1 ELSE 0 END) AS rujuk_minta,
             SUM(CASE WHEN cara_selesai = 'Selesai dengan Perjanjian Penyelesaian' THEN 1 ELSE 0 END) AS selesai"
        );
        self::gatePencapaian($q, $year);
        if (in_array($kategori, ['Sivil', 'Syariah'], true)) {
            $q->where('kategori_kes', $kategori);
        }
        $rows = $q->groupBy('cawangan')->get();

        return self::pivotPencapaian($rows, self::BRANCHES);
    }

    /**
     * Pure pivot for the pencapaian funnel.
     * Returns ['matrix' => [branch => [perakuan,penugasan,rujuk_minta,selesai,f1,f2,f3]], 'total' => [...]].
     */
    public static function pivotPencapaian(iterable $rows, array $branches): array
    {
        $blank = ['perakuan' => 0, 'penugasan' => 0, 'rujuk_minta' => 0, 'selesai' => 0];

        $matrix = [];
        foreach ($branches as $b) {
            $matrix[$b] = $blank + self::peratusCells($blank);
        }

        $sum = $blank;
        foreach ($rows as $r) {
            if (! isset($matrix[$r->cawangan])) {
                continue;
            }
            $c = [
                'perakuan' => (int) $r->perakuan,
                'penugasan' => (int) $r->penugasan,
                'rujuk_minta' => (int) $r->rujuk_minta,
                'selesai' => (int) $r->selesai,
            ];
            $matrix[$r->cawangan] = $c + self::peratusCells($c);
            foreach ($blank as $k => $_) {
                $sum[$k] += $c[$k];
            }
        }

        return ['matrix' => $matrix, 'total' => $sum + self::peratusCells($sum)];
    }

    /** F1/F2/F3 consecutive-stage percentages for a funnel count cell. */
    private static function peratusCells(array $c): array
    {
        return [
            'f1' => self::pct($c['penugasan'], $c['perakuan']),
            'f2' => self::pct($c['rujuk_minta'], $c['penugasan']),
            'f3' => self::pct($c['selesai'], $c['rujuk_minta']),
        ];
    }

    /** Percentage with denom>0 guard; 0.0 when no denominator (legacy showed 0). */
    private static function pct(int $num, int $denom): float
    {
        return $denom > 0 ? round($num / $denom * 100, 2) : 0.0;
    }
}
