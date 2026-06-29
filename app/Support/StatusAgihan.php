<?php

namespace App\Support;

/**
 * Canonical numeric state machine for forms.status_agihan — the legacy peguam-panel
 * case-assignment spine. The current build had diverged by writing string labels
 * ('Ditawarkan'…) into a column legacy used numerically; this restores the numeric
 * machine as canonical and maps the legacy strings for backward reads.
 *
 * Flow (Baru):  0 →(Pengarah terima) 8 →(PPUU pilih) 10 →(Pengarah sokong) 13 →(KP lulus) 1 →(PP terima) 2
 *   rejections:  Pengarah tolak →9 · Pengarah tak sokong →4 · KP tolak →15/14
 * Flow (Semula): 4/15 →(PPUU re-pick) 10 → …
 * Flow (Tarik Diri): 2 →(PP) 12 →(PPUU) 16 →(Pengarah) 17 →(KP) 6 lulus / 2 tolak
 */
final class StatusAgihan
{
    public const BARU_PENGARAH = '0';        // awaiting Pengarah review of a new case
    public const DITAWARKAN = '1';           // offered to panel lawyer
    public const DITERIMA = '2';             // accepted by panel lawyer (case active)
    public const PPUU_AGIH_SEMULA = '4';     // bounced back to PPUU to re-pick
    public const SELESAI = '5';              // case closed
    public const TARIK_DIRI_LULUS = '6';     // withdrawal approved → returned to pool
    public const LEBIH_MASA = '7';           // auto re-assign (no PP response in 7 days)
    public const DIAGIH_PPUU = '8';          // awaiting PPUU lawyer selection
    public const DITOLAK_PENGARAH = '9';     // Pengarah rejected the new case
    public const SOKONGAN_PENGARAH = '10';   // awaiting Pengarah endorsement of PPUU pick
    public const DALAM_PROSES_TARIK_DIRI = '12'; // PP submitted withdrawal
    public const KELULUSAN_KP = '13';        // awaiting Ketua Pengarah final approval
    public const TOLAK_KE_CAWANGAN = '14';   // KP rejected back to branch
    public const KELULUSAN_KP_SEMULA = '15'; // re-submitted to KP after rejection
    public const SEMAKAN_PENGARAH_TD = '16'; // withdrawal: awaiting Pengarah review
    public const SEMAKAN_KP_TD = '17';       // withdrawal: awaiting Ketua Pengarah review

    /** Numeric code → human label (Bahasa Melayu). */
    public const LABELS = [
        '0' => 'Dalam Proses Kelulusan Pengarah',
        '1' => 'Ditawarkan Kepada Peguam Panel',
        '2' => 'Diterima Oleh Peguam Panel',
        '4' => 'PPUU Agih Semula',
        '5' => 'Kes Selesai',
        '6' => 'Tarik Diri Mewakili OYD',
        '7' => 'Agihan Semula (Lebih Masa)',
        '8' => 'Dalam Proses Untuk Diagih PPUU',
        '9' => 'Ditolak Oleh Pengarah',
        '10' => 'Dalam Proses Sokongan Pengarah',
        '12' => 'Dalam Proses Tarik Diri',
        '13' => 'Dalam Proses Kelulusan Ketua Pengarah',
        '14' => 'Tolak Semula Ke Cawangan',
        '15' => 'Dalam Proses Kelulusan Ketua Pengarah (Semula)',
        '16' => 'Dalam Proses Semakan Pengarah (Tarik Diri)',
        '17' => 'Dalam Proses Semakan Ketua Pengarah (Tarik Diri)',
    ];

    /** Legacy string labels written by the pre-parity build → numeric code. */
    public const LEGACY_STRING_MAP = [
        'Ditawarkan' => self::DITAWARKAN,
        'Diterima' => self::DITERIMA,
        'Ditolak' => self::PPUU_AGIH_SEMULA,
        'Diserah Semula' => self::SELESAI,
    ];

    /** List-bucket status sets (senarai-pengagihan-*). */
    public const BUCKET_BARU = ['0', '8', '10', '13'];
    public const BUCKET_SEMASA = ['1', '2', '5'];
    public const BUCKET_SEMULA = ['4', '15'];
    public const BUCKET_TARIK_DIRI = ['12', '16', '17'];

    /** Human label for any stored value (numeric or legacy string). */
    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        $code = self::LEGACY_STRING_MAP[$code] ?? $code;

        return self::LABELS[$code] ?? $code;
    }

    /** Normalise a stored value (legacy string or numeric) to its numeric code. */
    public static function normalise(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::LEGACY_STRING_MAP[$code] ?? $code;
    }
}
