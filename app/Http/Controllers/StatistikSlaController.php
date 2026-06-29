<?php

namespace App\Http\Controllers;

use App\Support\SlaMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistik SLA — per-branch achievement matrices (EPIC F).
 * Five all-branch dashboards (40/60/120/7/60 day) over the fixed 23-branch list.
 * HQ-gated in routes; the SlaMatrix aggregate bypasses CawanganScope on purpose.
 */
class StatistikSlaController extends Controller
{
    public function index(Request $request): View
    {
        return view('statistik.sla.index', [
            'defs' => SlaMatrix::definitions(),
            'year' => $this->year($request),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        abort_unless(SlaMatrix::has($key), 404, 'Statistik tidak dijumpai.');

        $year = $this->year($request);

        return view('statistik.sla.show', [
            'key' => $key,
            'year' => $year,
            'data' => SlaMatrix::compute($key, $year),
            'branches' => SlaMatrix::BRANCHES,
            'kategori' => SlaMatrix::KATEGORI,
        ]);
    }

    public function pdf(Request $request, string $key): Response
    {
        abort_unless(SlaMatrix::has($key), 404, 'Statistik tidak dijumpai.');

        $year = $this->year($request);

        $pdf = Pdf::loadView('statistik.sla.pdf', [
            'key' => $key,
            'year' => $year,
            'data' => SlaMatrix::compute($key, $year),
            'branches' => SlaMatrix::BRANCHES,
            'kategori' => SlaMatrix::KATEGORI,
            'dijana' => now()->format('d/m/Y H:i'),
            'oleh' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('statistik-'.$key.'.pdf');
    }

    /** Optional year filter (blank = all years, matching legacy). */
    private function year(Request $request): ?int
    {
        $year = $request->input('tahun');

        return ($year !== null && $year !== '') ? (int) $year : null;
    }
}
