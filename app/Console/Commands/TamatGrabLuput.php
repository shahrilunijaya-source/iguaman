<?php

namespace App\Console\Commands;

use App\Support\AgihanLuarService;
use Illuminate\Console\Command;

/**
 * W5 - expire Khidmat Nasihat grabs left unclaimed past the 7-day window
 * (status_agihan_pl BUKA_GRAB -> LUPUT). Scheduled daily in routes/console.php.
 * Mirrors the Lebih Masa cron (agihan:lebih-masa).
 */
class TamatGrabLuput extends Command
{
    protected $signature = 'grab:tamat-luput';

    protected $description = 'Tamatkan tempoh grab Khidmat Nasihat yang melebihi 7 hari tanpa tuntutan peguam panel (LUPUT).';

    public function handle(AgihanLuarService $service): int
    {
        $count = $service->tamatGrabLuput(function ($kn) {
            $this->line("  • KN #{$kn->id} ({$kn->no_permohonan}) tamat tempoh grab (luput).");
        });

        $this->info("Tamat Grab Luput selesai: {$count} KN diluputkan.");

        return self::SUCCESS;
    }
}
