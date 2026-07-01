<?php

namespace App\Support;

use App\Mail\KesDitawarkanMail;
use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Models\SejarahPpuu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The legacy 3-tier case-assignment state machine (pp-agihan). Each method is one guarded
 * transition over forms.status_agihan + the sejarah_ppuu spine. Mirrors formAgihanBaru /
 * formAgihanBaruPengarah / formAgihanSemula. Transition emails fan out to the next actor
 * via NotifikasiAgihan (best-effort, outside the db transaction); the lawyer offer email
 * is sent on KP approval.
 *
 *   Baru:  0 →[pengarahTerima] 8 →[ppuuPilih] 10 →[pengarahSokong] 13 →[kpLulus] 1
 *   reject paths: pengarahTolakBaru →9 · pengarahTidakSokong →4 · kpTolak →15
 */
class AgihanService
{
    /** Entry - send an approved, unassigned case into the spine, awaiting Pengarah (NULL→0). */
    public function masuk(Form $kes, User $actor): void
    {
        $kes->update(['status_agihan' => StatusAgihan::BARU_PENGARAH]);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Kes dihantar ke proses agihan - menunggu Pengarah (kes #{$kes->id}).");
    }

    /** Recovery - re-open a Pengarah-rejected new case for another attempt (9→0). */
    public function pengarahBukaSemula(Form $kes, User $actor, ?string $ulasan): void
    {
        $kes->update(['status_agihan' => StatusAgihan::BARU_PENGARAH]);
        $suffix = $ulasan ? ": {$ulasan}" : '';
        Audit::log('forms', $kes->id, Audit::UPDATE, "Agihan ditolak dibuka semula untuk pertimbangan baharu{$suffix} (kes #{$kes->id}).");
    }

    /** Recovery - abandon assignment of a rejected case; it stays in rekod kes, unassigned (9→NULL). */
    public function pengarahBatalAgihan(Form $kes, User $actor, string $sebab): void
    {
        $kes->update([
            'status_agihan' => null,
            'nama_pegawai_yang_dapat_kes' => null,
            'agih_kepada' => null,
        ]);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Agihan kes dibatalkan (tidak akan diagih peguam): {$sebab} (kes #{$kes->id}).");
    }

    /** Tier 1 - Pengarah accepts a new case and hands it to a PPUU for lawyer selection (0→8). */
    public function pengarahTerima(Form $kes, User $actor, int $idPPUU): void
    {
        DB::transaction(function () use ($kes, $actor, $idPPUU) {
            $this->closeAktif($kes->id);

            SejarahPpuu::create([
                'id_kes' => $kes->id,
                'idPPUU' => $idPPUU,
                'tarikh_diberiAgihan' => now(),
                'statusAgihan' => StatusAgihan::DIAGIH_PPUU,
                'status_rekod' => SejarahPpuu::REKOD_AKTIF,
                'createdDate' => now(),
                'createdBy' => $actor->name,
            ]);

            $kes->update(['status_agihan' => StatusAgihan::DIAGIH_PPUU]);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE, "Agihan baru diterima Pengarah, diserah kepada PPUU (kes #{$kes->id}).");
        app(NotifikasiAgihan::class)->pengarahTerima($kes, $idPPUU);
    }

    /** Tier 1 - Pengarah rejects a new case (0→9). */
    public function pengarahTolakBaru(Form $kes, User $actor, string $sebab): void
    {
        $kes->update(['status_agihan' => StatusAgihan::DITOLAK_PENGARAH]);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Agihan baru ditolak oleh Pengarah: {$sebab} (kes #{$kes->id}).");
        app(NotifikasiAgihan::class)->pengarahTolak($kes, $sebab);
    }

    /** Tier 2 - PPUU picks a panel lawyer (Pilihan A own-cawangan / B other-negeri) (8→10). */
    public function ppuuPilih(Form $kes, User $actor, array $pick): void
    {
        DB::transaction(function () use ($kes, $actor, $pick) {
            $rec = SejarahPpuu::aktif($kes->id) ?? SejarahPpuu::create([
                'id_kes' => $kes->id,
                'idPPUU' => $actor->id,
                'status_rekod' => SejarahPpuu::REKOD_AKTIF,
                'createdDate' => now(),
                'createdBy' => $actor->name,
            ]);

            $rec->update([
                'pilihan_Agihan' => $pick['pilihan'],
                'cawangan_peguampanel' => $pick['cawangan'] ?? null,
                'nama_peguampanel' => $pick['namaPP'],
                'kpBaru_peguampanel' => $pick['kpPP'] ?? null,
                'ulasanPPUU' => $pick['ulasan'] ?? null,
                'tarikh_syorPPUU' => now(),
                'statusAgihan' => StatusAgihan::SOKONGAN_PENGARAH,
                'modifiedBy' => $actor->name,
                'modifiedDate' => now(),
            ]);

            $kes->update(['status_agihan' => StatusAgihan::SOKONGAN_PENGARAH]);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE, "PPUU memilih peguam ({$pick['namaPP']}) - dihantar untuk sokongan Pengarah (kes #{$kes->id}).");
        app(NotifikasiAgihan::class)->ppuuPilih($kes, $pick['namaPP']);
    }

    /** Tier 2 - Pengarah endorses the PPUU pick → forward to Ketua Pengarah (10→13). */
    public function pengarahSokong(Form $kes, User $actor, ?string $ulasan): void
    {
        DB::transaction(function () use ($kes, $ulasan) {
            $this->aktifOrFail($kes->id)->update([
                'status_sokonganPengarah' => '0',
                'ulasanPengarah' => $ulasan,
                'tarikh_PengarahKemaskini' => now(),
                'statusAgihan' => StatusAgihan::KELULUSAN_KP,
            ]);

            $kes->update(['status_agihan' => StatusAgihan::KELULUSAN_KP]);
        });

        Audit::log('forms', $kes->id, Audit::APPROVE, "Pemilihan peguam disokong Pengarah - dihantar untuk kelulusan Ketua Pengarah (kes #{$kes->id}).");
    }

    /** Tier 2 - Pengarah rejects the PPUU pick → back to PPUU to re-pick (10→4). */
    public function pengarahTidakSokong(Form $kes, User $actor, string $ulasan): void
    {
        DB::transaction(function () use ($kes, $actor, $ulasan) {
            $rec = $this->aktifOrFail($kes->id);
            $rec->update([
                'status_sokonganPengarah' => '1',
                'ulasanPengarah' => $ulasan,
                'tarikh_PengarahKemaskini' => now(),
                'status_rekod' => SejarahPpuu::REKOD_TUTUP,
            ]);

            $this->logReassignment($kes, $actor, StatusAgihan::PPUU_AGIH_SEMULA, "Pemilihan tidak disokong Pengarah: {$ulasan}");

            SejarahPpuu::create([
                'id_kes' => $kes->id,
                'idPPUU' => $rec->idPPUU,
                'tarikh_diberiAgihan' => now(),
                'statusAgihan' => StatusAgihan::PPUU_AGIH_SEMULA,
                'status_rekod' => SejarahPpuu::REKOD_AKTIF,
                'createdDate' => now(),
                'createdBy' => $actor->name,
            ]);

            $kes->update(['status_agihan' => StatusAgihan::PPUU_AGIH_SEMULA]);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE, "Pemilihan peguam tidak disokong - dikembalikan kepada PPUU (kes #{$kes->id}).");
    }

    /** Tier 3 - Ketua Pengarah approves → offer the case to the lawyer (13→1). */
    public function kpLulus(Form $kes, User $actor, ?string $ulasan): void
    {
        $rec = $this->aktifOrFail($kes->id);

        DB::transaction(function () use ($kes, $rec, $ulasan) {
            $rec->update([
                'status_KP' => '0',
                'ulasanKP' => $ulasan,
                'tarikh_KPKemaskini' => now(),
                'statusAgihan' => StatusAgihan::DITAWARKAN,
            ]);

            $kes->update([
                'status_agihan' => StatusAgihan::DITAWARKAN,
                'nama_pegawai_yang_dapat_kes' => $rec->nama_peguampanel,
                'agih_kepada' => $rec->nama_peguampanel,
                'tarikh_penugasan_peguam_panel' => now()->toDateString(),
            ]);
        });

        Audit::log('forms', $kes->id, Audit::APPROVE, "Agihan diluluskan Ketua Pengarah - ditawarkan kepada {$rec->nama_peguampanel} (kes #{$kes->id}).");
        $this->emailOffer($kes, $rec->kpBaru_peguampanel);
    }

    /** Tier 3 - Ketua Pengarah rejects → re-submit to PPUU (13→15). */
    public function kpTolak(Form $kes, User $actor, string $ulasan): void
    {
        DB::transaction(function () use ($kes, $actor, $ulasan) {
            $rec = $this->aktifOrFail($kes->id);
            $rec->update([
                'status_KP' => '1',
                'ulasanKP' => $ulasan,
                'tarikh_KPKemaskini' => now(),
                'status_rekod' => SejarahPpuu::REKOD_TUTUP,
            ]);

            SejarahPpuu::create([
                'id_kes' => $kes->id,
                'idPPUU' => $rec->idPPUU,
                'tarikh_diberiAgihan' => now(),
                'statusAgihan' => StatusAgihan::KELULUSAN_KP_SEMULA,
                'status_rekod' => SejarahPpuu::REKOD_AKTIF,
                'createdDate' => now(),
                'createdBy' => $actor->name,
            ]);

            $kes->update(['status_agihan' => StatusAgihan::KELULUSAN_KP_SEMULA]);
        });

        Audit::log('forms', $kes->id, Audit::REJECT, "Agihan tidak diluluskan Ketua Pengarah: {$ulasan} - dikembalikan kepada PPUU (kes #{$kes->id}).");
        app(NotifikasiAgihan::class)->kpTolak($kes, $ulasan);
    }

    /** Close any open sejarah_ppuu record for a case (rotation). */
    private function closeAktif(int $idKes): void
    {
        SejarahPpuu::where('id_kes', $idKes)
            ->where('status_rekod', SejarahPpuu::REKOD_AKTIF)
            ->update(['status_rekod' => SejarahPpuu::REKOD_TUTUP]);
    }

    private function aktifOrFail(int $idKes): SejarahPpuu
    {
        $rec = SejarahPpuu::aktif($idKes);
        abort_if($rec === null, 422, 'Tiada rekod agihan PPUU aktif untuk kes ini.');

        return $rec;
    }

    /** Record the outgoing lawyer + increment the per-case reassignment counter. */
    private function logReassignment(Form $kes, User $actor, string $status, string $alasan): void
    {
        SejarahPeguamPanel::create([
            'id_kes' => $kes->id,
            'nama_pp_lama' => $kes->nama_pegawai_yang_dapat_kes,
            'kp_pp_lama' => null,
            'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
            'status' => $status,
            'status_agihan' => $status,
            'alasan' => $alasan,
            'permohonan_kali' => SejarahPeguamPanel::nextPermohonanKali($kes->id),
            'status_rekod' => 'aktif',
            'createdDate' => now(),
            'createdBy' => $actor->name,
            'modifiedBy' => $actor->name,
            'modifiedDate' => now(),
        ]);
    }

    /** Offer email to the selected lawyer (never blocks the transition on mail failure). */
    private function emailOffer(Form $kes, ?string $kpPeguam): void
    {
        if (! $kpPeguam) {
            return;
        }

        $peguam = PeguamPanel::where('kp_peguam', $kpPeguam)->first();
        if (! $peguam || ! filled($peguam->emel_peguam) || ! str_contains((string) $peguam->emel_peguam, '@')) {
            return;
        }

        try {
            Mail::to($peguam->emel_peguam)->send(new KesDitawarkanMail($kes, $peguam));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
