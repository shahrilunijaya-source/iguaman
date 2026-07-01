<?php

namespace App\Console\Commands;

use App\Support\LebihMasaService;
use Illuminate\Console\Command;

/**
 * Auto re-assign panel-lawyer offers left unanswered past the 7-day window
 * (EPIC G - legacy cron_lebih_masa.php). Scheduled daily in routes/console.php.
 */
class AgihanLebihMasa extends Command
{
    protected $signature = 'agihan:lebih-masa';

    protected $description = 'Agih semula tawaran kes yang melebihi 7 hari tanpa maklum balas Peguam Panel (Lebih Masa).';

    public function handle(LebihMasaService $service): int
    {
        $count = $service->run(function ($kes) {
            $this->line("  • Kes #{$kes->id} ({$kes->no_fail}) diagih semula.");
        });

        $this->info("Lebih Masa selesai: {$count} kes diagih semula.");

        return self::SUCCESS;
    }
}
