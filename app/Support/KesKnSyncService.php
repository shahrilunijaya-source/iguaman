<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Form;
use App\Models\KhidmatNasihat;
use Illuminate\Support\Facades\DB;

/**
 * W12 — reverse sync from the litigation case (forms) back to the originating
 * Khidmat Nasihat. Explicit service call (NOT a model event): Form is updated
 * from many controllers + the assignment machine, so a saved-event would fire
 * spuriously. Idempotent and a no-op when the case has no linked KN.
 */
class KesKnSyncService
{
    public const STATE_TERBUKA = 'TERBUKA';

    public const STATE_SELESAI = 'SELESAI';

    public const STATE_DITUTUP = 'DITUTUP';

    /** Push a downstream case state onto the linked KN, if any. */
    public function pushToKn(Form $kes, string $state, ?string $actor = null): void
    {
        $kn = KhidmatNasihat::where('id_forms', $kes->id)->first();

        if ($kn === null) {
            return;
        }

        // CODE-01: KN state change + its audit trail commit together (savepoint when nested).
        DB::transaction(function () use ($kn, $kes, $state, $actor) {
            $kn->update([
                'status_kes_terbuka' => $state,
                'tarikh_kes_dikemaskini' => now(),
            ]);

            Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
                "Sync dari kes #{$kes->id}: status kes -> {$state}.", $actor);
        });
    }
}
