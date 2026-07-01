<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Support\Bulan;
use App\Support\KesilapanMatrix;
use App\Support\SlaMatrix;
use App\Support\WideExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kesilapan Penjanaan Nombor Fail - files closed for a generated-number error
 * (P1, legacy cetakan_statistik_* + export_kesilapan_nombor_fail.php). The
 * inverse of EPIC F's universal Kesilapan exclusion: an all-branch per-month
 * count matrix + a wide CSV of the underlying records.
 */
class KesilapanController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) ($request->input('tahun') ?: now()->year);
        $kategori = $request->input('kategori') ?: null;

        return view('statistik.kesilapan.index', [
            'year' => $year,
            'kategori' => $kategori,
            'data' => KesilapanMatrix::compute($year, $kategori),
            'branches' => SlaMatrix::BRANCHES,
            'bulan' => Bulan::NAMES,
            'kategoriList' => SlaMatrix::KATEGORI,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $request->only(['bulan', 'tahun', 'cawangan', 'kategori']);
        $query = $this->query($request);
        $filename = 'laporan_kesilapan_penjanaan_nombor_fail_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filters, $query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($this->envelope($filters) as $env) {
                fputcsv($out, $env);
            }
            fputcsv($out, array_merge(['BIL.'], array_map(fn ($c) => $c[0], WideExport::kesilapanColumns())));

            // PERF-01: cursor() streams rows from the DB - no full result set in memory.
            $bil = 1;
            foreach ($query->cursor() as $r) {
                fputcsv($out, WideExport::kesilapanRow($r, $bil++));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /** Marker + optional filters, branch-gated by CawanganScope, ref_kes joined. */
    private function query(Request $request): Builder
    {
        return Form::query()
            ->leftJoin('ref_kes', 'forms.jenis_kes', '=', 'ref_kes.id_kes')
            ->select('forms.*', DB::raw("COALESCE(ref_kes.deskripsi, '".WideExport::NO_DATA."') as jenis_kes_text"))
            ->where('forms.status', KesilapanMatrix::MARKER_STATUS)
            ->where('forms.sebab_tutup_fail', KesilapanMatrix::MARKER_SEBAB)
            ->whereNotNull('forms.tarikh_tutup_fail')
            ->when($request->input('tahun'), fn (Builder $q, $v) => $q->whereYear('forms.tarikh_perakuan', $v))
            ->when($request->input('bulan'), fn (Builder $q, $v) => $q->whereMonth('forms.tarikh_perakuan', $v))
            ->when($request->input('cawangan'), fn (Builder $q, $v) => $q->where('forms.cawangan', $v))
            ->when($request->input('kategori'), fn (Builder $q, $v) => $q->where('forms.kategori_kes', $v))
            ->orderByDesc('forms.tarikh_tutup_fail');
    }

    /** Title + filter-summary rows before the header. */
    private function envelope(array $filters): array
    {
        $bulan = ($filters['bulan'] ?? '') !== '' ? Bulan::label($filters['bulan']) : 'Semua Bulan';

        return [
            ['LAPORAN KESILAPAN PENJANAAN NOMBOR FAIL'],
            [''],
            ['BULAN: '.$bulan],
            ['TAHUN: '.(($filters['tahun'] ?? '') !== '' ? $filters['tahun'] : 'Semua Tahun')],
            ['KATEGORI KES: '.(($filters['kategori'] ?? '') !== '' ? $filters['kategori'] : 'Semua Kategori Kes')],
            ['CAWANGAN: '.(($filters['cawangan'] ?? '') !== '' ? $filters['cawangan'] : 'Semua Cawangan')],
            [''],
        ];
    }
}
