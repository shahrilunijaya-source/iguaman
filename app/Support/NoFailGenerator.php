<?php

namespace App\Support;

use App\Models\Form;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legal-aid file-number generator (legacy jFail.php). Format:
 *   JBG.{STATE}({jenis_kes}){seq}/{MMYYYY}   e.g.  JBG.KUL(085)1/032026
 * where seq is the running ROW_NUMBER() within (cawangan, jenis_kes) ordered by application
 * date (cumulative — no yearly reset). Replaces the degraded JBG/{abbrev}/{id}/{mmYY} stub.
 */
class NoFailGenerator
{
    /** Branch/state name (sans "JBG " prefix, upper-cased) → 3-letter code. */
    public const STATE_CODES = [
        'JOHOR' => 'JHR',
        'MUAR' => 'MUA',
        'KEDAH' => 'KDH',
        'LANGKAWI' => 'LGK',
        'KELANTAN' => 'KTN',
        'GUA MUSANG' => 'GMU',
        'MELAKA' => 'MLK',
        'NEGERI SEMBILAN' => 'NSN',
        'PAHANG' => 'PHG',
        'RAUB' => 'RAU',
        'PERLIS' => 'PLS',
        'PULAU PINANG' => 'PNG',
        'PERAK' => 'PRK',
        'TAIPING' => 'TPG',
        'SELANGOR' => 'SGR',
        'TERENGGANU' => 'TRG',
        'WP KUALA LUMPUR' => 'KUL',
        'KUALA LUMPUR' => 'KUL',
        'WP LABUAN' => 'LBN',
        'LABUAN' => 'LBN',
        'PUTRAJAYA' => 'PJY',
        'SARAWAK' => 'SWK',
        'MIRI' => 'MYY',
        'SIBU' => 'SBW',
        'SABAH' => 'SBH',
    ];

    public function generate(Form $kes): string
    {
        $code = $this->stateCode($kes->cawangan);
        $jenis = $kes->jenis_kes ?: '000';
        $seq = $this->sequence($kes);
        $date = $kes->tarikh_permohonan ? Carbon::parse($kes->tarikh_permohonan) : now();

        return sprintf('JBG.%s(%s)%d/%s', $code, $jenis, $seq, $date->format('mY'));
    }

    /**
     * W17 — a mediation's OWN file number, an independent PGT series that never
     * reuses the litigation no_fail. Format mirrors generate() with a PGT prefix:
     *   PGT.{STATE}({jenis_kes}){seq}/{MMYYYY}
     * seq is the next running number within the (cawangan, jenis_kes) partition of
     * rows that already carry a no_pengantaraan. The unique column is the backstop
     * against the rare concurrent-generation race.
     */
    public function generatePengantaraan(Form $kes): string
    {
        $code = $this->stateCode($kes->cawangan);
        $jenis = $kes->jenis_kes ?: '000';
        $seq = $this->pengantaraanSequence($kes);

        return sprintf('PGT.%s(%s)%d/%s', $code, $jenis, $seq, now()->format('mY'));
    }

    /** Next mediation sequence within the (cawangan, jenis_kes) partition. */
    private function pengantaraanSequence(Form $kes): int
    {
        $n = Form::withoutGlobalScope(\App\Models\Scopes\CawanganScope::class)
            ->whereNotNull('no_pengantaraan')
            ->where('no_pengantaraan', '!=', '')
            ->where('cawangan', $kes->cawangan)
            ->where('jenis_kes', $kes->jenis_kes)
            ->count();

        return $n + 1;
    }

    /**
     * W9 — Pembelaan Awam (public criminal defence) file number: a distinct PBA series so
     * criminal-defence files are visually separable from the litigation `JBG.` series.
     *   PBA.{STATE}({jenis_kes}){seq}/{MMYYYY}   e.g.  PBA.KUL(085)1/072026
     * Sequence runs within (cawangan, jenis_kes) over Pembelaan-tagged rows only.
     */
    public function generatePembelaan(Form $kes): string
    {
        $code = $this->stateCode($kes->cawangan);
        $jenis = $kes->jenis_kes ?: '000';
        $seq = $this->sequencePembelaan($kes);
        $date = $kes->tarikh_permohonan ? Carbon::parse($kes->tarikh_permohonan) : now();

        return sprintf('PBA.%s(%s)%d/%s', $code, $jenis, $seq, $date->format('mY'));
    }

    /** Map a branch name to its 3-letter code; fall back to the first 3 letters. */
    private function stateCode(?string $cawangan): string
    {
        $key = strtoupper(trim(preg_replace('/^JBG\s+/i', '', trim((string) $cawangan))));

        return self::STATE_CODES[$key]
            ?? (strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $cawangan) ?: 'JBG', 0, 3)) ?: 'JBG');
    }

    /** ROW_NUMBER() of this case within its (cawangan, jenis_kes) partition, by application date. */
    private function sequence(Form $kes): int
    {
        $row = DB::selectOne(
            'SELECT rn FROM (
                SELECT id, ROW_NUMBER() OVER (ORDER BY tarikh_permohonan ASC, id ASC) AS rn
                FROM forms WHERE cawangan = ? AND jenis_kes = ?
            ) t WHERE id = ?',
            [$kes->cawangan, $kes->jenis_kes, $kes->id]
        );

        return $row ? (int) $row->rn : 1;
    }

    /** ROW_NUMBER() within (cawangan, jenis_kes) over Pembelaan-tagged rows only. */
    private function sequencePembelaan(Form $kes): int
    {
        $row = DB::selectOne(
            'SELECT rn FROM (
                SELECT id, ROW_NUMBER() OVER (ORDER BY tarikh_permohonan ASC, id ASC) AS rn
                FROM forms WHERE cawangan = ? AND jenis_kes = ? AND is_pembelaan_awam = 1
            ) t WHERE id = ?',
            [$kes->cawangan, $kes->jenis_kes, $kes->id]
        );

        return $row ? (int) $row->rn : 1;
    }
}
