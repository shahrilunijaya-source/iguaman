<?php

namespace App\Support;

/**
 * Cuti Umum state encoding (EPIC G — legacy ref_cuti.idnegeri).
 *
 * Legacy stores the states a holiday applies to as a fixed 16-slot,
 * comma-separated string. Slot k (1-based, = ref_negeri id) holds the state's
 * own 2-digit code when selected, or "00" when not:
 *
 *   "01,00,03,00,05,00,00,00,00,10,00,00,00,00,00,16"   (47 chars)
 *
 * Decoding is position-based (slot index → state id) to match the legacy
 * substr() reader exactly. Pure (no DB) so it is unit-testable in isolation.
 */
class CutiNegeri
{
    public const SLOTS = 16;

    /** Fallback id => name (legacy hardcoded order); RefNegeri is preferred at runtime. */
    public const STATES = [
        1 => 'Johor', 2 => 'Kedah', 3 => 'Kelantan', 4 => 'Melaka', 5 => 'Negeri Sembilan',
        6 => 'Pahang', 7 => 'Pulau Pinang', 8 => 'Perak', 9 => 'Perlis', 10 => 'Selangor',
        11 => 'Terengganu', 12 => 'Sabah', 13 => 'Sarawak', 14 => 'W.P. Kuala Lumpur',
        15 => 'W.P. Labuan', 16 => 'W.P. Putrajaya',
    ];

    /** Selected state ids → the 16-slot comma string. */
    public static function encode(array $ids): string
    {
        $selected = array_map('intval', $ids);

        $slots = [];
        for ($slot = 1; $slot <= self::SLOTS; $slot++) {
            $slots[] = in_array($slot, $selected, true) ? sprintf('%02d', $slot) : '00';
        }

        return implode(',', $slots);
    }

    /** 16-slot comma string → ascending list of selected state ids. */
    public static function decode(?string $idnegeri): array
    {
        if ($idnegeri === null || trim($idnegeri) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $idnegeri) as $index => $value) {
            $value = trim($value);
            $slot = $index + 1;
            if ($value !== '' && $value !== '00' && $slot <= self::SLOTS) {
                $ids[] = $slot;
            }
        }

        return $ids;
    }

    /** Readable state names for a stored string. $names is an id => nama map (RefNegeri). */
    public static function labels(?string $idnegeri, ?array $names = null): array
    {
        $map = $names ?: self::STATES;

        return array_map(fn ($id) => $map[$id] ?? "Negeri {$id}", self::decode($idnegeri));
    }

    /** True when every state is selected (legacy "Semua Negeri"). */
    public static function isAll(?string $idnegeri): bool
    {
        return count(self::decode($idnegeri)) === self::SLOTS;
    }
}
