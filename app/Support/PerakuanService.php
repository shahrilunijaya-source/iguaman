<?php

namespace App\Support;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * W14 - interim → muktamad legal-aid certificate (Perakuan Bantuan Guaman) lifecycle for
 * Pembelaan Awam (criminal) cases. A SEGERA case may be issued an INTERIM certificate
 * immediately so representation can start; once the application is fully approved the
 * certificate is finalised to MUKTAMAD. Guarded transitions mirror the AgihanService pattern
 * (state check + DB::transaction + Audit). The certificate number runs its own PRK series.
 */
class PerakuanService
{
    public const STATUS_INTERIM = 'INTERIM';

    public const STATUS_MUKTAMAD = 'MUKTAMAD';

    /**
     * Issue an INTERIM certificate. Requires the case to be SEGERA (urgent) unless an
     * authorised override is passed. Idempotent guard: only from the null state.
     */
    public function keluarInterim(Form $kes, User $actor, bool $override = false): void
    {
        abort_unless((bool) $kes->is_pembelaan_awam, 422, 'Perakuan hanya untuk kes Pembelaan Awam.');
        abort_if(filled($kes->status_perakuan), 422, 'Perakuan telah dikeluarkan untuk kes ini.');
        abort_unless($kes->is_segera || $override, 422, 'Perakuan interim hanya untuk kes segera.');

        DB::transaction(function () use ($kes) {
            $kes->update([
                'status_perakuan' => self::STATUS_INTERIM,
                'no_perakuan' => $this->generateNoPerakuan(),
                'tarikh_perakuan_interim' => now()->toDateString(),
            ]);
        });

        Audit::log('forms', $kes->id, Audit::APPROVE,
            "Perakuan Bantuan Guaman INTERIM dikeluarkan ({$kes->no_perakuan}): {$kes->nama}", $actor->name);
    }

    /** Finalise an INTERIM certificate to MUKTAMAD. */
    public function muktamadkan(Form $kes, User $actor): void
    {
        abort_unless($kes->status_perakuan === self::STATUS_INTERIM, 422,
            'Hanya perakuan interim boleh dimuktamadkan.');

        // PROC-16: an INTERIM certificate with no number is an impossible state. Fail loudly so the
        // upstream break is investigated, instead of silently minting a number that masks it.
        abort_if(blank($kes->no_perakuan), 422,
            'Perakuan interim ini tiada nombor - data tidak konsisten. Semak semula pengeluaran interim.');

        DB::transaction(function () use ($kes) {
            $kes->update([
                'status_perakuan' => self::STATUS_MUKTAMAD,
                'tarikh_perakuan_muktamad' => now()->toDateString(),
            ]);
        });

        Audit::log('forms', $kes->id, Audit::APPROVE,
            "Perakuan Bantuan Guaman MUKTAMAD ({$kes->no_perakuan}): {$kes->nama}", $actor->name);
    }

    /** Certificate number generator: PRK-{year}-{seq} (zero-padded, row-locked per year). */
    public function generateNoPerakuan(): string
    {
        $prefix = 'PRK-'.now()->year.'-';
        $last = Form::query()
            ->withoutGlobalScopes()
            ->where('no_perakuan', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('no_perakuan')
            ->value('no_perakuan');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
