<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Per-branch SLA statistic matrices (EPIC F — legacy `cetakan_statistik_*`).
 *
 * Five dashboards measure the % of cases meeting a time target (DATEDIFF on a
 * pair of forms dates), pivoted over a FIXED 23-branch list × 4 case kategori,
 * each kategori shown as CAPAI / TIDAK CAPAI / PERATUS%. A JUMLAH KESELURUHAN
 * footer aggregates all branches.
 *
 * Ports the legacy statistik printouts verbatim in business rules. Two legacy
 * bugs are corrected here: the `* 7.0` percentage typo in serahan/khidmat (we
 * always divide by 100) and the missing/typo Putrajaya branch in
 * serahan/khidmat (we use one canonical 23-branch list for all five).
 *
 * The aggregation query deliberately bypasses Eloquent's CawanganScope: these
 * are all-branch management dashboards, route-gated to HQ roles.
 */
class SlaMatrix
{
    /** Case categories shown as matrix columns (forms.kategori_kes values). */
    public const KATEGORI = ['Sivil', 'Syariah', 'Jenayah', 'Pendamping Guaman'];

    /** Canonical fixed branch row order (legacy hardcoded UNION list, files 1-3). */
    public const BRANCHES = [
        'JBG WP PUTRAJAYA', 'JBG PERLIS', 'JBG KEDAH', 'JBG LANGKAWI', 'JBG PULAU PINANG',
        'JBG PERAK', 'JBG TAIPING', 'JBG SELANGOR', 'JBG WP KUALA LUMPUR', 'JBG NEGERI SEMBILAN',
        'JBG MELAKA', 'JBG JOHOR', 'JBG MUAR', 'JBG PAHANG', 'JBG RAUB', 'JBG TERENGGANU',
        'JBG KELANTAN', 'JBG GUA MUSANG', 'JBG SABAH', 'JBG WP LABUAN', 'JBG SARAWAK',
        'JBG MIRI', 'JBG SIBU',
    ];

    /** The kesilapan-fail exclusion shared by every legacy statistik query. */
    private static function excludeKesilapan(Builder $q): void
    {
        $q->where(fn ($w) => $w->whereNull('sebab_tutup_fail')
            ->orWhere('sebab_tutup_fail', '!=', 'Kesilapan Menjana Nombor Fail'));
    }

    /** A registered no_fail is required for every statistik. */
    private static function hasNoFail(Builder $q): void
    {
        $q->whereNotNull('no_fail')->where('no_fail', '!=', '');
    }

    /**
     * Dashboard definitions (url slug => spec). `start`/`end` feed
     * DATEDIFF(end, start) <= target; `filter` applies the legacy WHERE rules.
     */
    public static function definitions(): array
    {
        return [
            'perakuan' => [
                'label' => 'Statistik Perakuan Bantuan Guaman',
                'title' => 'STATISTIK PERAKUAN BANTUAN GUAMAN',
                'desc' => 'Pemakluman keputusan Perakuan Bantuan Guaman dalam tempoh 40 hari dari tarikh penerimaan Borang I (tidak termasuk kes kuasa Menteri dan kes sumbangan).',
                'target' => 40,
                'start' => 'tarikh_permohonan', 'end' => 'tarikh_pemakluman',
                'filter' => function (Builder $q) {
                    self::hasNoFail($q);
                    $q->where('kelulusan', 'Tidak')->where('sumbangan', 'Tiada');
                    self::excludeKesilapan($q);
                },
            ],
            'fail-tiada' => [
                'label' => 'Statistik Pemfailan Kes Tidak Terlibat Pengantaraan',
                'title' => 'STATISTIK PEMFAILAN KES TIDAK TERLIBAT PENGANTARAAN',
                'desc' => 'Kes tidak terlibat pengantaraan difailkan di Mahkamah dalam tempoh 60 hari dari tarikh perakuan dikeluarkan.',
                'target' => 60,
                'start' => 'tarikh_perakuan', 'end' => 'tarikh_pemfailan_kes',
                'filter' => function (Builder $q) {
                    self::hasNoFail($q);
                    $q->where('status_pengantaraan', 'Tidak');
                    self::excludeKesilapan($q);
                },
            ],
            'fail-terlibat' => [
                'label' => 'Statistik Pemfailan Kes Terlibat Pengantaraan',
                'title' => 'STATISTIK PEMFAILAN KES TERLIBAT PENGANTARAAN',
                'desc' => 'Kes terlibat pengantaraan difailkan di Mahkamah dalam tempoh 120 hari dari tarikh perakuan dikeluarkan.',
                'target' => 120,
                'start' => 'tarikh_perakuan', 'end' => 'tarikh_pemfailan_kes',
                'filter' => function (Builder $q) {
                    self::hasNoFail($q);
                    $q->where('status_pengantaraan', 'Ya');
                    self::excludeKesilapan($q);
                },
            ],
            'serahan' => [
                'label' => 'Statistik Serahan Perintah Kes',
                'title' => 'STATISTIK SERAHAN PERINTAH KES',
                'desc' => 'Perintah/penghakiman bersih diserahkan kepada Orang Yang Dibantu dalam tempoh 7 hari selepas diterima dari Mahkamah.',
                'target' => 7,
                'start' => 'tarikh_perintah_bersih', 'end' => 'tarikh_serahan_perintah',
                'filter' => function (Builder $q) {
                    self::hasNoFail($q);
                    self::excludeKesilapan($q);
                },
            ],
            'khidmat' => [
                'label' => 'Statistik Khidmat Pengantaraan',
                'title' => 'STATISTIK KHIDMAT PENGANTARAAN',
                'desc' => 'Khidmat pengantaraan diselesaikan dalam tempoh 60 hari dari tarikh surat persetujuan pengantaraan ditandatangani.',
                'target' => 60,
                'start' => 'tarikh_persetujuan_pengantaraan', 'end' => 'tarikh_persetujuan',
                'filter' => function (Builder $q) {
                    self::hasNoFail($q);
                    self::excludeKesilapan($q);
                },
            ],
        ];
    }

    /** True if $key is a known dashboard. */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /**
     * Compute one dashboard. Returns ['def', 'matrix', 'jumlah', 'grand'].
     * Optional $year filters on the SLA end date (legacy showed all years).
     */
    public static function compute(string $key, ?int $year = null): array
    {
        $def = self::definitions()[$key];

        // Column names come from the trusted $def array, never from request input.
        $diff = "DATEDIFF(`{$def['end']}`, `{$def['start']}`)";

        $grouped = DB::table('forms')
            ->selectRaw("cawangan, kategori_kes AS jenis,
                SUM(CASE WHEN {$diff} <= {$def['target']} THEN 1 ELSE 0 END) AS capai,
                SUM(CASE WHEN {$diff} > {$def['target']} THEN 1 ELSE 0 END) AS tidak")
            ->whereNotNull($def['start'])
            ->whereNotNull($def['end'])
            ->whereIn('kategori_kes', self::KATEGORI)
            ->when($year, fn (Builder $q) => $q->whereYear($def['end'], $year))
            ->where(fn (Builder $q) => $def['filter']($q))
            ->groupBy('cawangan', 'jenis')
            ->get();

        return ['def' => $def] + self::pivot($grouped, self::BRANCHES, self::KATEGORI);
    }

    /**
     * Pure pivot: fold grouped {cawangan, jenis, capai, tidak} rows onto the
     * fixed branch × kategori grid, with per-kategori JUMLAH + grand total.
     * Rows whose cawangan is not in $branches are ignored (legacy fixed list).
     *
     * @param  iterable  $grouped  rows with ->cawangan ->jenis ->capai ->tidak
     * @return array{matrix: array, jumlah: array, grand: array}
     */
    public static function pivot(iterable $grouped, array $branches, array $kategori): array
    {
        $cell = fn () => ['capai' => 0, 'tidak' => 0, 'total' => 0, 'peratus' => null];

        $matrix = [];
        foreach ($branches as $b) {
            foreach ($kategori as $k) {
                $matrix[$b][$k] = $cell();
            }
        }

        foreach ($grouped as $r) {
            $b = $r->cawangan;
            $k = $r->jenis;
            if (! isset($matrix[$b][$k])) {
                continue;
            }
            $capai = (int) $r->capai;
            $tidak = (int) $r->tidak;
            $total = $capai + $tidak;
            $matrix[$b][$k] = ['capai' => $capai, 'tidak' => $tidak, 'total' => $total, 'peratus' => self::peratus($capai, $total)];
        }

        // Per-kategori JUMLAH (down each column) + grand total of everything.
        $jumlah = [];
        $grand = ['capai' => 0, 'tidak' => 0, 'total' => 0, 'peratus' => null];
        foreach ($kategori as $k) {
            $capai = $tidak = 0;
            foreach ($branches as $b) {
                $capai += $matrix[$b][$k]['capai'];
                $tidak += $matrix[$b][$k]['tidak'];
            }
            $total = $capai + $tidak;
            $jumlah[$k] = ['capai' => $capai, 'tidak' => $tidak, 'total' => $total, 'peratus' => self::peratus($capai, $total)];
            $grand['capai'] += $capai;
            $grand['tidak'] += $tidak;
        }
        $grand['total'] = $grand['capai'] + $grand['tidak'];
        $grand['peratus'] = self::peratus($grand['capai'], $grand['total']);

        return ['matrix' => $matrix, 'jumlah' => $jumlah, 'grand' => $grand];
    }

    /** Achievement % (CAPAI / TOTAL), 2 d.p.; null when no cases. */
    public static function peratus(int $capai, int $total): ?float
    {
        return $total > 0 ? round($capai / $total * 100, 2) : null;
    }
}
