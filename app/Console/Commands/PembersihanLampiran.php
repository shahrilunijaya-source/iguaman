<?php

namespace App\Console\Commands;

use App\Support\RetensiLampiranService;
use Illuminate\Console\Command;

/**
 * W6 — report (default) or dispose of case attachments past the 7-year retention window.
 * Report-only by default so disposal of legal records is always a deliberate `--purge` run.
 * Scheduled monthly (report-only) in routes/console.php.
 */
class PembersihanLampiran extends Command
{
    protected $signature = 'lampiran:bersih-retensi {--purge : Lupuskan fail melebihi tempoh retensi (default: lapor sahaja)}';

    protected $description = 'Lapor atau lupuskan lampiran kes yang melebihi tempoh retensi 7 tahun.';

    public function handle(RetensiLampiranService $service): int
    {
        $purge = (bool) $this->option('purge');

        $result = $service->run($purge, function ($file, $didPurge) {
            $this->line(sprintf(
                '  • #%d %s (%s)%s',
                $file->id,
                $file->nama,
                optional($file->uploaded_at)->format('Y-m-d') ?? '—',
                $didPurge ? ' — DILUPUSKAN' : ''
            ));
        });

        if ($purge) {
            $this->info("Retensi lampiran: {$result['purged']} fail dilupuskan.");
        } else {
            $this->warn("Retensi lampiran (lapor sahaja): {$result['count']} fail melebihi tempoh 7 tahun. Jalankan dengan --purge untuk melupuskan.");
        }

        return self::SUCCESS;
    }
}
