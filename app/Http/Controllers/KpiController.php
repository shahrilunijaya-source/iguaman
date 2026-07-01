<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Penetapan Petunjuk Prestasi Utama (KPI) — yearly SLA dashboard.
 * Each KPI measures the % of cases meeting a time target (DATEDIFF on forms dates),
 * grouped by month + case type (kategori_kes). Ports the legacy JBG KPI screen.
 *
 * Column assumptions (legacy forms, dates may be sparse on staging data):
 *  - Perakuan:        tarikh_permohonan -> tarikh_pemakluman (<=40), excl. Menteri cases
 *  - Pemfailan:       tarikh_perakuan   -> tarikh_pemfailan_kes (60 non-mediation / 120 mediation)
 *  - Serahan perintah: tarikh_perintah_bersih -> tarikh_serahan_perintah (<=7)
 *  - Khidmat pengantaraan: tarikh_persetujuan_pengantaraan -> tarikh_selesai (<=60)
 */
class KpiController extends Controller
{
    public const TYPES = ['Sivil', 'Syariah', 'Jenayah', 'Pendamping Guaman'];

    /** PERF-04: cache the (all-branch) KPI aggregate briefly. */
    private const CACHE_TTL = 300;

    private function noMediation(Builder $q): void
    {
        $q->where(fn ($w) => $w->whereNull('status_pengantaraan')->orWhere('status_pengantaraan', ''));
    }

    private function withMediation(Builder $q): void
    {
        $q->whereNotNull('status_pengantaraan')->where('status_pengantaraan', '!=', '');
    }

    /** KPI definitions (label, description, target days, date columns, types, optional filter). */
    private function definitions(): array
    {
        return [
            [
                'key' => 'perakuan',
                'label' => 'KPI Perakuan Bantuan Guaman',
                'desc' => '100% pemakluman keputusan permohonan Perakuan Bantuan Guaman bagi kes Sivil, Syariah, Jenayah dan Pendamping Guaman dilaksanakan dalam tempoh 40 hari selepas tarikh penerimaan Borang I (tidak termasuk kes di bawah kuasa Menteri dan kes sumbangan).',
                'target' => 40,
                'start' => 'tarikh_permohonan', 'end' => 'tarikh_pemakluman', 'month' => 'tarikh_pemakluman',
                'types' => self::TYPES,
                'filter' => fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('keputusan_menteri')->orWhere('keputusan_menteri', '')),
            ],
            [
                'key' => 'fail_tanpa',
                'label' => 'KPI Pemfailan Kes Tidak Terlibat dengan Pengantaraan',
                'desc' => '100% kes yang tidak terlibat dengan pengantaraan JBG difailkan di Mahkamah dalam tempoh 60 hari dari tarikh perakuan dikeluarkan bagi kes Sivil, Syariah, Jenayah dan Pendamping Guaman.',
                'target' => 60,
                'start' => 'tarikh_perakuan', 'end' => 'tarikh_pemfailan_kes', 'month' => 'tarikh_pemfailan_kes',
                'types' => self::TYPES,
                'filter' => fn (Builder $q) => $this->noMediation($q),
            ],
            [
                'key' => 'fail_dengan',
                'label' => 'KPI Pemfailan Kes Terlibat dengan Pengantaraan',
                'desc' => '100% kes yang terlibat dengan pengantaraan JBG difailkan di Mahkamah dalam tempoh 120 hari dari tarikh perakuan dikeluarkan bagi kes Sivil, Syariah, Jenayah dan Pendamping Guaman.',
                'target' => 120,
                'start' => 'tarikh_perakuan', 'end' => 'tarikh_pemfailan_kes', 'month' => 'tarikh_pemfailan_kes',
                'types' => ['Sivil', 'Syariah'],
                'filter' => fn (Builder $q) => $this->withMediation($q),
            ],
            [
                'key' => 'serahan',
                'label' => 'KPI Serahan Perintah Kes',
                'desc' => '100% perintah/penghakiman bersih Sivil dan Syariah diserahkan kepada Orang Yang Dibantu dalam tempoh 7 hari selepas diterima dari Mahkamah.',
                'target' => 7,
                'start' => 'tarikh_perintah_bersih', 'end' => 'tarikh_serahan_perintah', 'month' => 'tarikh_serahan_perintah',
                'types' => ['Sivil', 'Syariah'],
                'filter' => null,
            ],
            [
                'key' => 'khidmat',
                'label' => 'KPI Khidmat Pengantaraan',
                'desc' => '100% khidmat pengantaraan hendaklah diselesaikan dalam masa 60 hari dari tarikh surat persetujuan pengantaraan ditandatangani.',
                'target' => 60,
                'start' => 'tarikh_persetujuan_pengantaraan', 'end' => 'tarikh_selesai', 'month' => 'tarikh_selesai',
                'types' => ['Sivil', 'Syariah'],
                'filter' => fn (Builder $q) => $this->withMediation($q),
            ],
        ];
    }

    public function index(Request $request): View
    {
        $year = (int) ($request->input('tahun') ?: now()->year);

        $kpis = array_map(fn ($def) => $this->compute($def, $year), $this->definitions());

        return view('kpi.index', ['kpis' => $kpis, 'year' => $year]);
    }

    /**
     * For one KPI + year, return per-type per-month met/missed counts.
     * matrix[type][month 1..12] = ['met' => n, 'missed' => n].
     */
    private function compute(array $def, int $year): array
    {
        // PERF-04: cache the scalar result (matrix + totals). $def carries a closure filter,
        // so it is excluded from the cache payload and re-attached after.
        $cached = Cache::remember("kpi:{$def['key']}:{$year}", self::CACHE_TTL, function () use ($def, $year) {
            // Column names come from the trusted $def array, never request input.
            $diff = "DATEDIFF(`{$def['end']}`, `{$def['start']}`)";

            $rows = DB::table('forms')
                ->selectRaw("kategori_kes AS jenis, MONTH(`{$def['month']}`) AS bulan,
                    SUM(CASE WHEN {$diff} <= {$def['target']} THEN 1 ELSE 0 END) AS met,
                    SUM(CASE WHEN {$diff} > {$def['target']} THEN 1 ELSE 0 END) AS missed")
                ->whereYear($def['month'], $year)
                ->whereNotNull($def['start'])
                ->whereNotNull($def['end'])
                ->whereIn('kategori_kes', $def['types'])
                ->when($def['filter'], fn ($q) => tap($q, $def['filter']))
                ->groupBy('jenis', DB::raw("MONTH(`{$def['month']}`)"))
                ->get();

            $matrix = [];
            foreach ($def['types'] as $t) {
                $matrix[$t] = array_fill(1, 12, ['met' => 0, 'missed' => 0]);
            }

            $totalMet = 0;
            $totalAll = 0;
            foreach ($rows as $r) {
                if (! isset($matrix[$r->jenis][(int) $r->bulan])) {
                    continue;
                }
                $matrix[$r->jenis][(int) $r->bulan] = ['met' => (int) $r->met, 'missed' => (int) $r->missed];
                $totalMet += (int) $r->met;
                $totalAll += (int) $r->met + (int) $r->missed;
            }

            return [
                'matrix' => $matrix,
                'achieved' => $totalAll > 0 ? round($totalMet / $totalAll * 100) : null,
                'total' => $totalAll,
            ];
        });

        return ['def' => $def] + $cached;
    }
}
