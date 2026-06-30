<?php

namespace App\Http\Controllers;

use App\Support\SlaListExport;
use App\Support\SlaMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Statistik SLA — per-branch achievement matrices (EPIC F).
 * Five all-branch dashboards (40/60/120/7/60 day) over the fixed 23-branch list.
 * HQ-gated in routes; the SlaMatrix aggregate bypasses CawanganScope on purpose.
 * Each matrix has a paired breach "senarai" CSV (the TIDAK CAPAI drill-down).
 */
class StatistikSlaController extends Controller
{
    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun',
        7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember',
    ];

    public function index(Request $request): View
    {
        return view('statistik.sla.index', [
            'defs' => SlaMatrix::definitions(),
            'year' => $this->year($request),
            'month' => $this->month($request),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        abort_unless(SlaMatrix::has($key), 404, 'Statistik tidak dijumpai.');

        $year = $this->year($request);
        $month = $this->month($request);

        return view('statistik.sla.show', [
            'key' => $key,
            'year' => $year,
            'month' => $month,
            'data' => SlaMatrix::compute($key, $year, $month),
            'branches' => SlaMatrix::BRANCHES,
            'kategori' => SlaMatrix::KATEGORI,
        ]);
    }

    public function pdf(Request $request, string $key): Response
    {
        abort_unless(SlaMatrix::has($key), 404, 'Statistik tidak dijumpai.');

        $year = $this->year($request);
        $month = $this->month($request);

        $pdf = Pdf::loadView('statistik.sla.pdf', [
            'key' => $key,
            'year' => $year,
            'month' => $month,
            'data' => SlaMatrix::compute($key, $year, $month),
            'branches' => SlaMatrix::BRANCHES,
            'kategori' => SlaMatrix::KATEGORI,
            'dijana' => now()->format('d/m/Y H:i'),
            'oleh' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('statistik-'.$key.'.pdf');
    }

    /**
     * Breach "senarai" CSV — the rows behind a dashboard's TIDAK CAPAI counts
     * (legacy export_senarai_*.php). Optional cawangan/kategori drill-down from
     * a matrix cell; period filters reconcile with the matrix. Branch-gated by
     * CawanganScope inside SlaListExport::query.
     */
    public function senarai(Request $request, string $key): StreamedResponse
    {
        abort_unless(SlaListExport::has($key), 404, 'Senarai tidak dijumpai.');

        $year = $this->year($request);
        $month = $this->month($request);
        $cawangan = $request->input('cawangan') ?: null;
        $kategori = $request->input('kategori') ?: null;

        $meta = SlaListExport::meta($key);
        $rows = SlaListExport::query($key, $year, $month, $cawangan, $kategori)->get();
        $filename = $meta['file'].'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($key, $meta, $rows, $year, $month, $cawangan, $kategori) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Malay text + the ="..." IC trick.

            foreach (self::senaraiEnvelope($meta['title'], $year, $month, $cawangan, $kategori) as $env) {
                fputcsv($out, $env);
            }
            fputcsv($out, array_merge(['BIL.'], SlaListExport::headers($key)));

            $bil = 1;
            foreach ($rows as $r) {
                fputcsv($out, SlaListExport::row($r, $key, $bil++));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /** Title + filter-summary rows printed before the senarai header. */
    private static function senaraiEnvelope(string $title, ?int $year, ?int $month, ?string $cawangan, ?string $kategori): array
    {
        return [
            [$title],
            [''],
            ['BULAN: '.($month ? (self::BULAN[$month] ?? $month) : 'Semua Bulan')],
            ['TAHUN: '.($year ?: 'Semua Tahun')],
            ['KATEGORI KES: '.($kategori ?: 'Semua Kategori Kes')],
            ['CAWANGAN: '.($cawangan ?: 'Semua Cawangan')],
            [''],
        ];
    }

    /** Optional year filter (blank = all years, matching legacy). */
    private function year(Request $request): ?int
    {
        $year = $request->input('tahun');

        return ($year !== null && $year !== '') ? (int) $year : null;
    }

    /** Optional month filter 1-12 (blank = all months). */
    private function month(Request $request): ?int
    {
        $month = (int) $request->input('bulan');

        return ($month >= 1 && $month <= 12) ? $month : null;
    }
}
