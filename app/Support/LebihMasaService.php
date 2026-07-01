<?php

namespace App\Support;

use App\Mail\KesLebihMasaMail;
use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\Scopes\CawanganScope;
use App\Models\SejarahPeguamPanel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Lebih Masa - auto re-assignment of offers a panel lawyer never answered
 * (EPIC G, legacy cron_lebih_masa.php + formAgihanSemasa.php).
 *
 * A case offered to a lawyer (status_agihan = '1', tarikh_penugasan_peguam_panel set)
 * with no Terima/Tolak response within 7 days is bounced back to the PPUU for a
 * re-pick: forms.status_agihan -> '4', assignee + offer date cleared. A closed
 * sejarah_peguam_panel row is written with the Lebih Masa marker ('7'), and the
 * branch Pengarah is notified.
 *
 * Runs unauthenticated (scheduler), so CawanganScope is bypassed explicitly to
 * cover every branch. The mail is best-effort and never rolls back the re-assign
 * (an improvement over the legacy rollback-on-mail-failure).
 */
class LebihMasaService
{
    public const OVERDUE_DAYS = 7;

    public const REASON = 'Lebih Masa. Tidak ada maklum balas dari Peguam Panel tersebut sama ada untuk Terima atau Tolak Penawaran Penugasan ini';

    private const ACTOR = 'Sistem (Auto Lebih Masa)';

    /** Offered cases past the 7-day response window, across all branches. */
    public function overdue(): Collection
    {
        $cutoff = now()->subDays(self::OVERDUE_DAYS)->toDateString();

        return Form::withoutGlobalScope(CawanganScope::class)
            ->whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN]))
            ->whereNotNull('tarikh_penugasan_peguam_panel')
            ->whereDate('tarikh_penugasan_peguam_panel', '<', $cutoff)
            ->get();
    }

    /** Process every overdue offer; returns the number re-assigned. */
    public function run(?callable $onEach = null): int
    {
        $count = 0;
        foreach ($this->overdue() as $kes) {
            // PROC-18: isolate each row so one failure doesn't abort the whole nightly run
            // (the rest still get reassigned; the failure is reported for follow-up).
            try {
                $this->reassign($kes);
                $count++;
                if ($onEach) {
                    $onEach($kes);
                }
            } catch (\Throwable $e) {
                report($e);
                logger()->error('lebih-masa reassign failed', ['kes_id' => $kes->id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /** Bounce one overdue offer back to the PPUU pool (atomic), then notify. */
    public function reassign(Form $kes): void
    {
        $namaLama = $kes->nama_pegawai_yang_dapat_kes;
        $kpLama = $namaLama
            ? optional(PeguamPanel::where('nama_peguam', $namaLama)->first())->kp_peguam
            : null;

        DB::transaction(function () use ($kes, $namaLama, $kpLama) {
            SejarahPeguamPanel::create([
                'id_kes' => $kes->id,
                'nama_pp_lama' => $namaLama,
                'kp_pp_lama' => $kpLama,
                'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
                'status' => StatusAgihan::LEBIH_MASA,
                'status_agihan' => StatusAgihan::LEBIH_MASA,
                'alasan' => self::REASON,
                'permohonan_kali' => SejarahPeguamPanel::nextPermohonanKali($kes->id),
                'status_rekod' => 'tutup',
                'createdDate' => now(),
                'createdBy' => self::ACTOR,
                'modifiedBy' => self::ACTOR,
                'modifiedDate' => now(),
            ]);

            $kes->update([
                'status_agihan' => StatusAgihan::PPUU_AGIH_SEMULA,
                'nama_pegawai_yang_dapat_kes' => null,
                'tarikh_penugasan_peguam_panel' => null,
                'sebab_Tidak_Diluluskan' => self::REASON,
            ]);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE, "Agihan semula automatik (Lebih Masa) - {$namaLama} tidak memberi maklum balas (kes #{$kes->id}).");

        $this->notifyPengarah($kes);
    }

    /** Best-effort: notify active Pengarah of the case branch (never blocks). */
    private function notifyPengarah(Form $kes): void
    {
        $pengarah = User::query()
            ->where('role', User::ROLE_PENGARAH)
            ->where('is_active', true)
            ->where('cawangan', $kes->cawangan)
            ->get();

        if ($pengarah->isEmpty()) {
            $pengarah = User::query()->where('role', User::ROLE_PENGARAH)->where('is_active', true)->get();
        }

        foreach ($pengarah as $p) {
            if (! filled($p->email) || ! str_contains((string) $p->email, '@')) {
                continue;
            }
            try {
                Mail::to($p->email)->send(new KesLebihMasaMail($kes));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
