<?php

namespace App\Support;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Models\SejarahPpuu;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Panel-lawyer active/inactive lifecycle (legacy selenggara-peguampanel-detail.php +
 * query/selenggaraPengguna.php). Deactivating a lawyer who still holds active cases triggers
 * DEATH-REDISTRIBUTION: every case they handle is returned to the PPUU re-assignment pool so
 * no assisted person is left without representation — the most dangerous legacy parity gap.
 */
class PeguamLifecycleService
{
    /** Statuses where the lawyer is actively responsible for the case (offered/accepted). */
    private const ACTIVE_CASE_STATUSES = [StatusAgihan::DITAWARKAN, StatusAgihan::DITERIMA];

    /**
     * Deactivate a panel lawyer with a justification and redistribute their active cases.
     * Returns the number of cases returned to the pool.
     */
    public function deactivate(PeguamPanel $lawyer, User $actor, string $sebab): int
    {
        return DB::transaction(function () use ($lawyer, $actor, $sebab) {
            $lawyer->update([
                'statusAktif' => PeguamPanel::TIDAK_AKTIF,
                'sebabTidakAktif' => $sebab,
                'tarikhTidakAktif' => now()->toDateString(),
            ]);

            // Block the lawyer's login.
            User::where('id_peguam_panel', $lawyer->kp_peguam)->update(['is_active' => false]);

            $redistributed = $this->redistributeActiveCases($lawyer, $actor, $sebab);

            Audit::log('peguam_panel', $lawyer->id, Audit::UPDATE,
                "Peguam dinyahaktifkan ({$sebab}): {$lawyer->nama_peguam}. {$redistributed} kes diagih semula.");

            return $redistributed;
        });
    }

    /** Reactivate a previously deactivated lawyer (does not auto-reassign cases back). */
    public function reactivate(PeguamPanel $lawyer, User $actor): void
    {
        DB::transaction(function () use ($lawyer, $actor) {
            $lawyer->update([
                'statusAktif' => PeguamPanel::AKTIF,
                'sebabTidakAktif' => null,
                'tarikhTidakAktif' => null,
            ]);

            User::where('id_peguam_panel', $lawyer->kp_peguam)->update(['is_active' => true]);

            Audit::log('peguam_panel', $lawyer->id, Audit::UPDATE, "Peguam diaktifkan semula: {$lawyer->nama_peguam}.");
        });
    }

    /** Return every active case held by the lawyer to the PPUU pool (status_agihan → 4). */
    private function redistributeActiveCases(PeguamPanel $lawyer, User $actor, string $sebab): int
    {
        $cases = Form::query()
            ->where('nama_pegawai_yang_dapat_kes', $lawyer->nama_peguam)
            ->whereIn('status_agihan', StatusAgihan::bucketValues(self::ACTIVE_CASE_STATUSES))
            ->get();

        foreach ($cases as $kes) {
            SejarahPpuu::where('id_kes', $kes->id)->where('status_rekod', SejarahPpuu::REKOD_AKTIF)
                ->update(['status_rekod' => SejarahPpuu::REKOD_TUTUP]);
            SejarahPpuu::create([
                'id_kes' => $kes->id,
                'tarikh_diberiAgihan' => now(),
                'statusAgihan' => StatusAgihan::PPUU_AGIH_SEMULA,
                'status_rekod' => SejarahPpuu::REKOD_AKTIF,
                'createdDate' => now(),
                'createdBy' => $actor->name,
            ]);

            SejarahPeguamPanel::create([
                'id_kes' => $kes->id,
                'nama_pp_lama' => $lawyer->nama_peguam,
                'kp_pp_lama' => $lawyer->kp_peguam,
                'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
                'status' => StatusAgihan::PPUU_AGIH_SEMULA,
                'status_agihan' => StatusAgihan::PPUU_AGIH_SEMULA,
                'alasan' => "Peguam dinyahaktifkan: {$sebab}",
                'permohonan_kali' => SejarahPeguamPanel::nextPermohonanKali($kes->id),
                'status_rekod' => 'aktif',
                'createdDate' => now(),
                'createdBy' => $actor->name,
                'modifiedBy' => $actor->name,
                'modifiedDate' => now(),
            ]);

            $kes->update([
                'status_agihan' => StatusAgihan::PPUU_AGIH_SEMULA,
                'nama_pegawai_yang_dapat_kes' => null,
                'agih_kepada' => null,
                'tarikh_penugasan_peguam_panel' => null,
            ]);
        }

        return $cases->count();
    }
}
