<?php

namespace App\Jobs;

use App\Exports\LaporanExport;
use App\Support\LaporanRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

/**
 * W20 - bulk report export off the request cycle. Large reports (thousands of rows) were
 * generated synchronously, blocking the response; this runs on the `database` queue and
 * writes the finished .xlsx to disk for the user to download.
 *
 * Branch isolation: the queue has no auth user, so CawanganScope is a no-op here - the
 * enqueuing controller resolves the user's effective branch and passes it in `filters`.
 */
class ExportLaporanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry if a queue worker is configured; irrelevant (but harmless) under dispatchSync. */
    public int $tries = 3;

    public function __construct(
        public string $type,
        public array $filters,
        public string $path,
        public string $disk = 'local',
    ) {}

    public function handle(): void
    {
        $report = LaporanRegistry::find($this->type);

        if ($report === null) {
            return;
        }

        // PERF-01: pass the query (not ->get()) so LaporanExport (FromQuery) chunks it.
        $query = LaporanRegistry::buildQuery($report, $this->filters, bypassScope: true);

        Excel::store(new LaporanExport($report['columns'], $query), $this->path, $this->disk);
    }

    /** Surface a failed generation instead of leaving the download route waiting on a phantom file. */
    public function failed(\Throwable $e): void
    {
        report($e);
    }
}
