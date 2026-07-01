<?php

namespace App\Http\Controllers;

use App\Jobs\ExportLaporanJob;
use App\Models\Form;
use App\Support\CsvSafe;
use App\Support\LaporanRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
    /** Report registry (shared with the queued bulk-export job). */
    private function reports(): array
    {
        return LaporanRegistry::all();
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
        $query = $this->query($report, $request);
        $filename = $type.'-'.now()->format('Ymd-His').'.csv';
        $userId = $request->user()->id;

        return response()->streamDownload(function () use ($cols, $query, $type, $userId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values($cols));

            // PERF-01: cursor() streams rows one at a time — the full result set never lands
            // in PHP memory, so a large export can't exhaust memory_limit mid-request.
            $n = 0;
            foreach ($query->cursor() as $r) {
                fputcsv($out, array_map(fn ($f) => $this->cell($r, $f), array_keys($cols)));
                $n++;
            }
            fclose($out);

            Log::info('export.download', ['type' => $type, 'format' => 'csv', 'user_id' => $userId, 'rows' => $n]);
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
        $report = LaporanRegistry::find($type);
        abort_if($report === null, 404, 'Laporan tidak dijumpai.');

        return $report;
    }

    /** Build the filtered query for a report (cawangan + date range on tarikh_permohonan). */
    private function query(array $report, Request $request): Builder
    {
        return LaporanRegistry::buildQuery($report, $request->only(['cawangan', 'dari', 'hingga']));
    }

    /**
     * W20 — queue a bulk .xlsx export off the request cycle. The user's effective branch is
     * resolved here (the queue has no auth user) and passed to the job for isolation.
     */
    public function eksportPukal(Request $request, string $type): RedirectResponse
    {
        $this->report($type); // 404 on unknown type

        $filters = $request->only(['cawangan', 'dari', 'hingga']);
        $user = $request->user();

        // Restrict to the user's own branch unless they may view all branches.
        if ($user->isStaff() && filled($user->cawangan) && ! $user->can('cawangan.view-all')) {
            $filters['cawangan'] = $user->cawangan;
        }

        $file = $type.'-'.now()->format('Ymd-His').'.xlsx';

        // Per-user export directory binds a finished file to its generator, closing the
        // predictable-filename, non-owner-bound export IDOR (see muatTurunEksport). dispatchSync
        // because Hostinger shared hosting runs no queue worker — a queued job would never execute.
        try {
            ExportLaporanJob::dispatchSync($type, $filters, 'exports/'.$user->id.'/'.$file);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Eksport pukal gagal dijana. Sila cuba lagi.');
        }

        Log::info('export.generate', ['type' => $type, 'user_id' => $user->id, 'file' => $file, 'filters' => $filters]);

        return back()->with('status', 'Eksport pukal siap. Muat turun: '.$file)
            ->with('eksport_fail', $file);
    }

    /**
     * W20 — stream a finished bulk-export file. Scoped to the requesting user's own export
     * directory (exports/{userId}/) so a user can only download files they generated — closing the
     * predictable-filename, non-owner-bound export IDOR. basename() blocks path traversal.
     */
    public function muatTurunEksport(Request $request, string $fail): StreamedResponse
    {
        $name = basename($fail);
        $path = 'exports/'.$request->user()->id.'/'.$name;
        abort_unless(Storage::disk('local')->exists($path), 404, 'Fail belum siap atau tidak dijumpai.');

        Log::info('export.download', ['file' => $name, 'user_id' => $request->user()->id]);

        return Storage::disk('local')->download($path, $name);
    }

    /** Render one cell, formatting Carbon dates. */
    private function cell(Form $row, string $field): string
    {
        $v = $row->$field;

        if ($v instanceof Carbon) {
            return $v->format('d/m/Y');
        }

        return CsvSafe::cell($v);
    }
}
