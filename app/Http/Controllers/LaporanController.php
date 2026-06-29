<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan — dedicated report screens over the forms spine.
 * Litigasi (Permohonan / Pendaftaran Fail / Status Fail) and Pengantaraan
 * (Penugasan / Pencapaian / Tidak Dirujuk), each filterable + CSV/PDF export.
 * All queries respect the CawanganScope (branch isolation) automatically.
 */
class LaporanController extends Controller
{
    /** Report registry: key => [label, group, filter, columns]. */
    private function reports(): array
    {
        $base = ['no_fail' => 'No. Fail', 'nama' => 'Pemohon', 'nokp' => 'No. KP', 'kategori_kes' => 'Kategori', 'cawangan' => 'Cawangan'];

        return [
            'permohonan' => [
                'label' => 'Laporan Permohonan', 'group' => 'Litigasi',
                'filter' => null,
                'columns' => $base + ['status' => 'Status', 'tarikh_permohonan' => 'Tarikh Mohon'],
            ],
            'pendaftaran-fail' => [
                'label' => 'Pendaftaran Fail', 'group' => 'Litigasi',
                'filter' => fn (Builder $q) => $q->whereNotNull('no_fail')->where('no_fail', '!=', ''),
                'columns' => $base + ['nama_pegawai' => 'Pegawai', 'tarikh_daftar' => 'Tarikh Daftar'],
            ],
            'status-fail' => [
                'label' => 'Status Fail', 'group' => 'Litigasi',
                'filter' => null,
                'columns' => $base + ['status' => 'Status', 'tarikh_tutup_fail' => 'Tarikh Tutup'],
            ],
            'penugasan-pengantaraan' => [
                'label' => 'Penugasan Pengantaraan', 'group' => 'Pengantaraan',
                'filter' => fn (Builder $q) => $q->whereNotNull('status_pengantaraan')->where('status_pengantaraan', '!=', ''),
                'columns' => $base + ['nama_pegawai' => 'Pengantara', 'tarikh_penugasan' => 'Tarikh Penugasan', 'status_pengantaraan' => 'Status'],
            ],
            'pencapaian-pengantaraan' => [
                'label' => 'Pencapaian Pengantaraan', 'group' => 'Pengantaraan',
                'filter' => fn (Builder $q) => $q->whereNotNull('cara_selesai')->where('cara_selesai', '!=', ''),
                'columns' => $base + ['cara_selesai' => 'Cara Selesai', 'tarikh_selesai' => 'Tarikh Selesai'],
            ],
            'tidak-dirujuk' => [
                'label' => 'Tidak Dirujuk Pengantaraan', 'group' => 'Pengantaraan',
                'filter' => fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('status_pengantaraan')->orWhere('status_pengantaraan', '')),
                'columns' => $base + ['status' => 'Status', 'tarikh_permohonan' => 'Tarikh Mohon'],
            ],
        ];
    }

    public function index(): View
    {
        return view('laporan.index', ['reports' => $this->reports()]);
    }

    public function show(Request $request, string $type): View
    {
        $report = $this->report($type);

        $rows = $this->query($report, $request)->paginate(30)->withQueryString();

        return view('laporan.show', [
            'type' => $type,
            'report' => $report,
            'rows' => $rows,
            'filters' => $request->only(['cawangan', 'dari', 'hingga']),
            'cawanganList' => Form::query()->whereNotNull('cawangan')->where('cawangan', '!=', '')->distinct()->orderBy('cawangan')->pluck('cawangan'),
        ]);
    }

    public function csv(Request $request, string $type): StreamedResponse
    {
        $report = $this->report($type);
        $cols = $report['columns'];
        $rows = $this->query($report, $request)->get();
        $filename = $type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($cols, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values($cols));
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($f) => $this->cell($r, $f), array_keys($cols)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request, string $type): Response
    {
        $report = $this->report($type);
        $rows = $this->query($report, $request)->limit(2000)->get();

        $pdf = Pdf::loadView('laporan.pdf', [
            'report' => $report,
            'rows' => $rows,
            'dijana' => now()->format('d/m/Y H:i'),
            'oleh' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream($type.'.pdf');
    }

    private function report(string $type): array
    {
        $report = $this->reports()[$type] ?? null;
        abort_if($report === null, 404, 'Laporan tidak dijumpai.');

        return $report;
    }

    /** Build the filtered query for a report (cawangan + date range on tarikh_permohonan). */
    private function query(array $report, Request $request): Builder
    {
        return Form::query()
            ->when($report['filter'], fn ($q) => tap($q, $report['filter']))
            ->when($request->input('cawangan'), fn ($q, $v) => $q->where('cawangan', $v))
            ->when($request->input('dari'), fn ($q, $v) => $q->whereDate('tarikh_permohonan', '>=', $v))
            ->when($request->input('hingga'), fn ($q, $v) => $q->whereDate('tarikh_permohonan', '<=', $v))
            ->orderByDesc('id');
    }

    /** Render one cell, formatting Carbon dates. */
    private function cell(Form $row, string $field): string
    {
        $v = $row->$field;

        if ($v instanceof Carbon) {
            return $v->format('d/m/Y');
        }

        return (string) ($v ?? '');
    }
}
