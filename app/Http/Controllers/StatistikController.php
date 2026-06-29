<?php

namespace App\Http\Controllers;

use App\Exports\KesExport;
use App\Models\Form;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

// Statistik (reporting) over the forms spine + Excel/PDF exports.
class StatistikController extends Controller
{
    public function index(Request $request): View
    {
        return view('statistik.index', $this->report($request) + [
            'filters' => $request->only(['cawangan', 'status', 'kategori']),
            'cawanganList' => Form::query()->whereNotNull('cawangan')->where('cawangan', '!=', '')->distinct()->orderBy('cawangan')->pluck('cawangan'),
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new KesExport($request->only(['cawangan', 'status', 'kategori', 'q'])),
            'kes-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function pdf(Request $request): Response
    {
        $pdf = Pdf::loadView('statistik.pdf', $this->report($request) + [
            'dijana' => now()->format('d/m/Y H:i'),
            'oleh' => $request->user()->name,
        ]);

        return $pdf->download('statistik-'.now()->format('Ymd-His').'.pdf');
    }

    /** Shared aggregates for dashboard + PDF. */
    private function report(Request $request): array
    {
        $kpi = [
            'jumlah' => (clone $this->filtered($request))->count(),
            'tutup' => (clone $this->filtered($request))->whereNotNull('tarikh_tutup_fail')->count(),
            'aktif' => (clone $this->filtered($request))->whereNull('tarikh_tutup_fail')->count(),
            'pengantaraan' => (clone $this->filtered($request))->whereNotNull('status_pengantaraan')->where('status_pengantaraan', '!=', '')->count(),
        ];

        return [
            'kpi' => $kpi,
            'byCawangan' => $this->groupCount($request, 'cawangan'),
            'byKategori' => $this->groupCount($request, 'kategori_kes'),
            'byStatus' => $this->groupCount($request, 'status'),
            'byBulan' => $this->byBulan($request),
        ];
    }

    private function filtered(Request $request): Builder
    {
        return Form::query()
            ->when($request->input('cawangan'), fn ($w, $v) => $w->where('cawangan', $v))
            ->when($request->input('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($request->input('kategori'), fn ($w, $v) => $w->where('kategori_kes', $v))
            ->when($request->input('q'), function ($w, $v) {
                $w->where(fn ($s) => $s->where('nama', 'like', "%{$v}%")->orWhere('nokp', 'like', "%{$v}%")->orWhere('no_fail', 'like', "%{$v}%"));
            });
    }

    /** [label => count] for a column, top 12, blanks excluded. */
    private function groupCount(Request $request, string $column): array
    {
        return $this->filtered($request)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select($column, DB::raw('COUNT(*) as n'))
            ->groupBy($column)->orderByDesc('n')->limit(12)
            ->pluck('n', $column)->all();
    }

    /** Cases per month by tarikh_permohonan, last 12 buckets. */
    private function byBulan(Request $request): array
    {
        return $this->filtered($request)
            ->whereNotNull('tarikh_permohonan')
            ->select(DB::raw("DATE_FORMAT(tarikh_permohonan, '%Y-%m') as bulan"), DB::raw('COUNT(*) as n'))
            ->groupBy('bulan')->orderByDesc('bulan')->limit(12)
            ->pluck('n', 'bulan')->all();
    }
}
