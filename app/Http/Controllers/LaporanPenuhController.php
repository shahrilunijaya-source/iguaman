<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Support\WideExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan Penuh — wide-column CSV exports (EPIC F, legacy `export_*.php`).
 * Permohonan / Pendaftaran Fail / Status Fail, with the legacy title+filter
 * envelope, derived BULAN/TAHUN columns, ref_kes JENIS KES join, and NoKP
 * emitted as an Excel text formula. CawanganScope enforces the legacy
 * HQ-sees-all / branch-forced-to-own gating automatically.
 */
class LaporanPenuhController extends Controller
{
    public function csv(Request $request, string $type): StreamedResponse
    {
        abort_unless(WideExport::has($type), 404, 'Laporan tidak dijumpai.');

        $meta = WideExport::meta($type);
        $filters = $request->only(['dari', 'hingga', 'kategori', 'cawangan', 'status']);
        $rows = $this->query($type, $meta, $request)->get();

        $filename = $meta['file'].'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($type, $filters, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Malay text + the ="..." IC trick.

            foreach (WideExport::envelope($type, $filters) as $env) {
                fputcsv($out, $env);
            }
            fputcsv($out, array_merge(['BIL.'], WideExport::headers($type)));

            $bil = 1;
            foreach ($rows as $r) {
                fputcsv($out, WideExport::row($r, $type, $bil++));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /** Build the filtered, branch-gated query (forms + ref_kes join). */
    private function query(string $type, array $meta, Request $request): Builder
    {
        $q = Form::query()
            ->leftJoin('ref_kes', 'forms.jenis_kes', '=', 'ref_kes.id_kes')
            ->select('forms.*', DB::raw("COALESCE(ref_kes.deskripsi, '".WideExport::NO_DATA."') as jenis_kes_text"))
            // Exclude files closed for a generated-number error (every legacy export does this).
            ->whereNot(fn (Builder $w) => $w->where('forms.status', 'Fail Tutup')
                ->where('forms.sebab_tutup_fail', 'Kesilapan Menjana Nombor Fail'));

        $this->baseFilters($q, $type);
        $this->requestFilters($q, $meta, $request);

        return $q
            ->orderByRaw("CASE WHEN forms.cawangan = 'JBG WP PUTRAJAYA' THEN 0 ELSE 1 END")
            ->orderByDesc('forms.'.$meta['tarikh_col']);
    }

    /** Report-specific base WHERE (registered file / perakuan presence). */
    private function baseFilters(Builder $q, string $type): void
    {
        if ($type === 'permohonan') {
            return;
        }

        $q->whereNotNull('forms.no_fail')->where('forms.no_fail', '!=', '');

        if ($type === 'pendaftaran-fail') {
            $q->whereRaw("LOWER(forms.no_fail) NOT LIKE '%null%'")
                ->whereNotNull('forms.tarikh_perakuan');
        }
    }

    /** Optional user filters: date range, kategori, cawangan, status. */
    private function requestFilters(Builder $q, array $meta, Request $request): void
    {
        $q->when($request->input('dari'), fn (Builder $w, $v) => $w->whereDate('forms.'.$meta['tarikh_col'], '>=', $v))
            ->when($request->input('hingga'), fn (Builder $w, $v) => $w->whereDate('forms.'.$meta['tarikh_col'], '<=', $v))
            ->when($request->input('cawangan'), fn (Builder $w, $v) => $w->where('forms.cawangan', $v));

        $kategori = $request->input('kategori');
        if ($kategori === 'TIADA_MAKLUMAT' && $meta['kategori_col'] === 'kategori_kes_borang') {
            $q->where(fn (Builder $w) => $w->whereNull('forms.kategori_kes_borang')->orWhere('forms.kategori_kes_borang', ''));
        } elseif (filled($kategori)) {
            $q->where('forms.'.$meta['kategori_col'], $kategori);
        }

        if ($meta['has_status']) {
            $this->statusFilter($q, (string) $request->input('status'));
        }
    }

    /** STATUS PEMFAILAN KES filter for the status-fail report. */
    private function statusFilter(Builder $q, string $status): void
    {
        match ($status) {
            'Fail Tutup' => $q->where('forms.status', 'Fail Tutup'),
            'Selesai' => $q->whereNotNull('forms.tarikh_selesai')->where('forms.status', '!=', 'Fail Tutup'),
            'Pemfailan Selesai' => $q->whereNotNull('forms.tarikh_pemfailan_kes')->where('forms.status', '!=', 'Fail Tutup'),
            'Belum Difailkan' => $q->whereNull('forms.tarikh_pemfailan_kes'),
            default => null,
        };
    }
}
