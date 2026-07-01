<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ARCH-04 — Malay month names, the single source for statistik period labels.
 * Previously copied verbatim into KesilapanController, StatistikSlaController and
 * PengantaraanMatrix.
 */
class Bulan
{
    /** 1-indexed month => Malay name. */
    public const NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun',
        7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember',
    ];

    /** Month name for 1-12, or the raw value cast to string when out of range. */
    public static function label(int|string|null $month): string
    {
        return self::NAMES[(int) $month] ?? (string) $month;
    }
}
