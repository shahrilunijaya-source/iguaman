<?php

namespace App\Support;

use App\Models\Form;
use App\Models\SejarahPeguamPanel;
use App\Models\SejarahPpuu;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Tarik Diri Mewakili OYD" — a panel lawyer withdraws from representing an assisted person.
 * 4-stage chain (legacy tarikdiri/peguampanel.php + query/tarikdiri.php):
 *   PP submit 2→12 · PPUU semak 12→16 · Pengarah semak 16→17 · KP keputusan 17→{6 lulus | 2 tolak}
 * On approval the case is returned to the PPUU re-assignment pool (forms.status_agihan=4).
 * The active withdrawal record is the sejarah_peguam_panel row with status_rekod='aktif'.
 */
class TarikDiriService
{
    /** The 9 withdrawal reasons (pilihanTarikDiri). Ref: Seksyen 24 Akta Bantuan Guaman 1971. */
    public const REASONS = [
        'Konflik kepentingan',
        'Masalah kesihatan',
        'Komitmen peribadi / keluarga',
        'Beban kes terlalu tinggi',
        'Anak guam tidak memberi kerjasama',
        'Kes bertentangan dengan prinsip peribadi',
        'Tidak mahu sambung sebagai panel',
        'Kesalahan fakta semasa penugasan',
        'Anak guam mohon menukar peguam panel',
    ];

    /** Stage 1 — PP submits a withdrawal request (2→12). Returns the created history row. */
    public function ppSubmit(Form $kes, User $actor, array $data): SejarahPeguamPanel
    {
        return DB::transaction(function () use ($kes, $actor, $data) {
            $row = SejarahPeguamPanel::create([
                'id_kes' => $kes->id,
                'nama_pp_lama' => $kes->nama_pegawai_yang_dapat_kes,
                'kp_pp_lama' => $data['kpBaruPP'] ?? null,
                'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
                'pilihanTarikDiri' => $data['pilihanTarikDiri'],
                'alasan' => $data['alasan'] ?? null,
                'tarikhNextBicaraKes' => $data['tarikhNextBicaraKes'] ?? null,
                'permohonan_kali' => SejarahPeguamPanel::nextPermohonanKali($kes->id),
                'status' => StatusAgihan::DALAM_PROSES_TARIK_DIRI,
                'status_agihan' => StatusAgihan::DALAM_PROSES_TARIK_DIRI,
                'status_rekod' => 'aktif',
                'createdDate' => now(),
                'createdBy' => $actor->name,
            ]);

            $kes->update(['status_agihan' => StatusAgihan::DALAM_PROSES_TARIK_DIRI]);

            return $row;
        });
    }

    /** Stage 2 — PPUU reviews + forwards to Pengarah (12→16). */
    public function ppuuSemak(Form $kes, User $actor, string $ulasan): void
    {
        $this->advance($kes, $actor, StatusAgihan::SEMAKAN_PENGARAH_TD, ['ulasanPPUU' => $ulasan]);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Tarik diri disemak PPUU — dihantar kepada Pengarah (kes #{$kes->id}).");
    }

    /** Stage 3 — Pengarah reviews + forwards to Ketua Pengarah (16→17). */
    public function pengarahSemak(Form $kes, User $actor, string $ulasan): void
    {
        $this->advance($kes, $actor, StatusAgihan::SEMAKAN_KP_TD, ['ulasanPengarah' => $ulasan]);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Tarik diri disemak Pengarah — dihantar kepada Ketua Pengarah (kes #{$kes->id}).");
    }

    /** Stage 4 — Ketua Pengarah decides: approve (→6, return to pool) or reject (→2, PP continues). */
    public function kpKeputusan(Form $kes, User $actor, bool $approve, string $ulasan): void
    {
        DB::transaction(function () use ($kes, $actor, $approve, $ulasan) {
            $row = $this->aktifOrFail($kes->id);

            if ($approve) {
                $row->update([
                    'status' => StatusAgihan::TARIK_DIRI_LULUS,
                    'status_agihan' => StatusAgihan::TARIK_DIRI_LULUS,
                    'status_rekod' => 'selesai',
                    'keputusan_tarikDiriHQ' => '0',
                    'ulasanKetuaPengarah' => 'Permohonan Tarik Diri Mewakili OYD Diterima & Diluluskan',
                    'modifiedBy' => $actor->name,
                    'modifiedDate' => now(),
                ]);

                // Return the case to the PPUU re-assignment pool.
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

                $kes->update([
                    'status_agihan' => StatusAgihan::PPUU_AGIH_SEMULA,
                    'nama_pegawai_yang_dapat_kes' => null,
                    'agih_kepada' => null,
                    'tarikh_penugasan_peguam_panel' => null,
                ]);

                Audit::log('forms', $kes->id, Audit::APPROVE, "Tarik diri diluluskan Ketua Pengarah — kes dikembalikan untuk agihan semula (kes #{$kes->id}).");
            } else {
                $row->update([
                    'status' => StatusAgihan::DITERIMA,
                    'status_agihan' => StatusAgihan::DITERIMA,
                    'status_rekod' => 'selesai',
                    'keputusan_tarikDiriHQ' => '1',
                    'ulasanKetuaPengarah' => $ulasan,
                    'modifiedBy' => $actor->name,
                    'modifiedDate' => now(),
                ]);

                $kes->update(['status_agihan' => StatusAgihan::DITERIMA]);

                Audit::log('forms', $kes->id, Audit::REJECT, "Tarik diri tidak diluluskan Ketua Pengarah — peguam meneruskan kes (kes #{$kes->id}).");
            }
        });
    }

    /** The active withdrawal history row for a case (status_rekod = aktif). */
    public static function aktif(int $idKes): ?SejarahPeguamPanel
    {
        return SejarahPeguamPanel::where('id_kes', $idKes)
            ->where('status_rekod', 'aktif')
            ->whereIn('status_agihan', StatusAgihan::BUCKET_TARIK_DIRI)
            ->latest('id')
            ->first();
    }

    private function advance(Form $kes, User $actor, string $toStatus, array $extra): void
    {
        DB::transaction(function () use ($kes, $actor, $toStatus, $extra) {
            $this->aktifOrFail($kes->id)->update($extra + [
                'status' => $toStatus,
                'status_agihan' => $toStatus,
                'modifiedBy' => $actor->name,
                'modifiedDate' => now(),
            ]);

            $kes->update(['status_agihan' => $toStatus]);
        });
    }

    private function aktifOrFail(int $idKes): SejarahPeguamPanel
    {
        $row = self::aktif($idKes);
        abort_if($row === null, 422, 'Tiada permohonan tarik diri aktif untuk kes ini.');

        return $row;
    }
}
