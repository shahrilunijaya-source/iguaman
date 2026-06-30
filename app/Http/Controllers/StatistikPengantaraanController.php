<?php

namespace App\Http\Controllers;

use App\Support\PengantaraanMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistik Penugasan Pengantaraan — two all-branch assignment matrices
 * (P1, legacy statistik_penugasan_pengantaraan + _bulanan). HQ-gated in routes;
 * PengantaraanMatrix bypasses CawanganScope on purpose (fixed 23-branch axis).
 */
class StatistikPengantaraanController extends Controller
{
    public function index(Request $request): View
    {
        return view('statistik.pengantaraan.index', ['year' => $this->year($request)]);
    }

    public function kategori(Request $request): View
    {
        $year = $this->year($request);

        return view('statistik.pengantaraan.kategori', [
            'year' => $year,
            'data' => PengantaraanMatrix::kategori($year),
            'branches' => PengantaraanMatrix::BRANCHES,
        ]);
    }

    public function bulanan(Request $request): View
    {
        $year = $this->year($request);
        $kategori = $request->input('kategori') ?: null;

        return view('statistik.pengantaraan.bulanan', [
            'year' => $year,
            'kategori' => $kategori,
            'data' => PengantaraanMatrix::bulanan($year, $kategori),
            'branches' => PengantaraanMatrix::BRANCHES,
            'bulan' => PengantaraanMatrix::BULAN,
        ]);
    }

    public function pencapaian(Request $request): View
    {
        $year = $this->year($request);
        $kategori = $request->input('kategori') ?: null;

        return view('statistik.pengantaraan.pencapaian', [
            'year' => $year,
            'kategori' => $kategori,
            'data' => PengantaraanMatrix::pencapaian($year, $kategori),
            'branches' => PengantaraanMatrix::BRANCHES,
        ]);
    }

    public function pdf(Request $request, string $jenis): Response
    {
        abort_unless(in_array($jenis, ['kategori', 'bulanan', 'pencapaian'], true), 404, 'Statistik tidak dijumpai.');

        $year = $this->year($request);
        $kategori = in_array($jenis, ['bulanan', 'pencapaian'], true) ? ($request->input('kategori') ?: null) : null;

        $data = match ($jenis) {
            'bulanan' => PengantaraanMatrix::bulanan($year, $kategori),
            'pencapaian' => PengantaraanMatrix::pencapaian($year, $kategori),
            default => PengantaraanMatrix::kategori($year),
        };

        $pdf = Pdf::loadView('statistik.pengantaraan.pdf', [
            'jenis' => $jenis,
            'year' => $year,
            'kategori' => $kategori,
            'data' => $data,
            'branches' => PengantaraanMatrix::BRANCHES,
            'bulan' => PengantaraanMatrix::BULAN,
            'dijana' => now()->format('d/m/Y H:i'),
            'oleh' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('statistik-penugasan-pengantaraan-'.$jenis.'.pdf');
    }

    /** Optional year filter (blank = all years, matching legacy). */
    private function year(Request $request): ?int
    {
        $year = $request->input('tahun');

        return ($year !== null && $year !== '') ? (int) $year : null;
    }
}
