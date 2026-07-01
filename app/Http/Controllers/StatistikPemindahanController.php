<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PemindahanCawangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * W8 + W4 - branch-transfer statistics (KPI Pemindahan). Reads ONLY the
 * pemindahan_cawangan ledger (KpiController stays SLA-only). Per-branch
 * MASUK (in) / KELUAR (out) counts across the 12 months of a chosen year.
 *
 * KELUAR = transfers initiated FROM a branch, by tarikh_pindah.
 * MASUK   = transfers received AT a branch, by COALESCE(tarikh_terima, tarikh_pindah).
 * Rejected (DITOLAK) transfers reverse the move, so they are excluded from both
 * sides - the matrix reflects live/accepted movement only.
 *
 * $jenis is always a hardcoded constant (never request input), so the raw
 * MONTH()/YEAR() expressions carry no user data (mirrors KpiController).
 */
class StatistikPemindahanController extends Controller
{
    private const BULAN = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];

    public function kes(Request $request): View
    {
        return $this->render($request, PemindahanCawangan::JENIS_KES, 'Kes (Litigasi)', 'kpi.pindah.kes');
    }

    public function khidmatNasihat(Request $request): View
    {
        return $this->render($request, PemindahanCawangan::JENIS_KN, 'Khidmat Nasihat', 'kpi.pindah.kn');
    }

    private function render(Request $request, string $jenis, string $tajuk, string $aktif): View
    {
        $year = (int) ($request->input('tahun') ?: now()->year);

        [$matrix, $totals] = $this->compute($jenis, $year);

        return view('statistik-pemindahan.index', [
            'year' => $year,
            'bulan' => self::BULAN,
            'matrix' => $matrix,        // [branch => [1..12 => ['masuk'=>n,'keluar'=>n]]]
            'totals' => $totals,        // ['masuk'=>n,'keluar'=>n] grand totals
            'tajuk' => $tajuk,
            'aktif' => $aktif,          // active tab route name
        ]);
    }

    /**
     * @return array{0: array<string,array<int,array{masuk:int,keluar:int}>>, 1: array{masuk:int,keluar:int}}
     */
    private function compute(string $jenis, int $year): array
    {
        $out = DB::table('pemindahan_cawangan')
            ->selectRaw('cawangan_asal AS cawangan, MONTH(tarikh_pindah) AS bulan, COUNT(*) AS jml')
            ->where('jenis_rekod', $jenis)
            ->where('status', '!=', PemindahanCawangan::STATUS_DITOLAK)
            ->whereYear('tarikh_pindah', $year)
            ->whereNotNull('cawangan_asal')
            ->groupBy('cawangan_asal', DB::raw('MONTH(tarikh_pindah)'))
            ->get();

        $in = DB::table('pemindahan_cawangan')
            ->selectRaw('cawangan_tujuan AS cawangan, MONTH(COALESCE(tarikh_terima, tarikh_pindah)) AS bulan, COUNT(*) AS jml')
            ->where('jenis_rekod', $jenis)
            ->where('status', '!=', PemindahanCawangan::STATUS_DITOLAK)
            ->whereRaw('YEAR(COALESCE(tarikh_terima, tarikh_pindah)) = ?', [$year])
            ->whereNotNull('cawangan_tujuan')
            ->groupBy('cawangan_tujuan', DB::raw('MONTH(COALESCE(tarikh_terima, tarikh_pindah))'))
            ->get();

        // Branch axis = every branch that appears on either side.
        $branches = collect($out)->pluck('cawangan')
            ->merge(collect($in)->pluck('cawangan'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $matrix = [];
        foreach ($branches as $b) {
            $matrix[$b] = array_fill(1, 12, ['masuk' => 0, 'keluar' => 0]);
        }

        $totals = ['masuk' => 0, 'keluar' => 0];

        foreach ($out as $row) {
            $matrix[$row->cawangan][(int) $row->bulan]['keluar'] = (int) $row->jml;
            $totals['keluar'] += (int) $row->jml;
        }
        foreach ($in as $row) {
            $matrix[$row->cawangan][(int) $row->bulan]['masuk'] = (int) $row->jml;
            $totals['masuk'] += (int) $row->jml;
        }

        return [$matrix, $totals];
    }
}
