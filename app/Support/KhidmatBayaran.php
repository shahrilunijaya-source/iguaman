<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Khidmat Nasihat payment computation (parity map §2 - eligibility/payment logic
 * recovered from the Nuxt FE). Pure + testable: no DB, no Eloquent. Feed it the
 * level-1 category name + declared income + exemption flag and it returns the fee.
 *
 * Rules (first match wins):
 *   1. is_percuma                 → RM0   (full exemption - overrides everything).
 *   2. wakil context PENJARA|JKM  → RM0   (slice 3: prison/welfare rep - no fee,
 *                                          mirrors FE idPenjara/idJKM → RM0.00).
 *   3. PENDAMPING JENAYAH         → RM0   (penjara accompaniment - no fee).
 *      PENDAMPING GUAMAN          → RM0   (JKM accompaniment - no fee).
 *   4. SIVIL / SYARIAH AND income > RM50,000 → RM260 ("Laluan Sumbangan").
 *   5. default                    → RM10.
 *
 * MAHKAMAH wakil context is intentionally NOT free - the court representative
 * still pays per the normal income matrix (matches the Nuxt FE, where only
 * idPenjara/idJKM zero the fee).
 */
final class KhidmatBayaran
{
    /** Default advisory fee. */
    public const FI_ASAS = 10.0;

    /** "Laluan Sumbangan" fee - Sivil/Syariah applicants above the income threshold. */
    public const FI_SUMBANGAN = 260.0;

    /** Full / accompaniment exemption. */
    public const FI_PERCUMA = 0.0;

    /** Income (RM) above which Sivil/Syariah applicants pay the Sumbangan fee. */
    public const HAD_PENDAPATAN = 50000.0;

    /** Level-1 categories that are always free (accompaniment programmes). */
    public const KATEGORI_PERCUMA = ['PENDAMPING JENAYAH', 'PENDAMPING GUAMAN'];

    /** Level-1 categories eligible for the income-driven Sumbangan path. */
    public const KATEGORI_SUMBANGAN = ['SIVIL', 'SYARIAH'];

    /** Wakil (representative) contexts that are fee-exempt (slice 3). */
    public const WAKIL_PERCUMA = ['PENJARA', 'JKM'];

    /**
     * Compute the advisory fee.
     *
     * @param  string|null  $kategori  Level-1 category name (ref_kategori_kn.jenis_kategori).
     * @param  float|int|string|null  $pendapatan  Declared monthly/household income (RM).
     * @param  bool  $isPercuma  Full-exemption toggle.
     * @param  string|null  $jenisWakil  SEBAGAI_WAKIL context (PENJARA|JKM|MAHKAMAH); null for DIRI_SENDIRI.
     */
    public static function kira(?string $kategori, float|int|string|null $pendapatan = null, bool $isPercuma = false, ?string $jenisWakil = null): float
    {
        if ($isPercuma) {
            return self::FI_PERCUMA;
        }

        $jenisWakil = $jenisWakil !== null ? strtoupper(trim($jenisWakil)) : null;

        if ($jenisWakil !== null && in_array($jenisWakil, self::WAKIL_PERCUMA, true)) {
            return self::FI_PERCUMA;
        }

        $kategori = $kategori !== null ? strtoupper(trim($kategori)) : null;

        if ($kategori !== null && in_array($kategori, self::KATEGORI_PERCUMA, true)) {
            return self::FI_PERCUMA;
        }

        $income = is_numeric($pendapatan) ? (float) $pendapatan : 0.0;

        if ($kategori !== null && in_array($kategori, self::KATEGORI_SUMBANGAN, true) && $income > self::HAD_PENDAPATAN) {
            return self::FI_SUMBANGAN;
        }

        return self::FI_ASAS;
    }
}
