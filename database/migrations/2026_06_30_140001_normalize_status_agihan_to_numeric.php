<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BL-1 follow-up — converge forms.status_agihan onto ONE numeric encoding.
 *
 * The pre-parity single-step assignment path wrote legacy STRING labels
 * ('Ditawarkan'/'Diterima'/'Ditolak') into a column the 3-tier spine drives
 * numerically. With the single-step path retired and the lawyer-area queries
 * switched to StatusAgihan::bucketValues, this normalises any remaining legacy
 * string rows to their canonical numeric code so reads no longer depend on the
 * string aliases.
 *
 * Mapping mirrors StatusAgihan::LEGACY_STRING_MAP. 'Diserah Se' covers the
 * varchar(10) truncation of 'Diserah Semula'. Idempotent (numeric rows untouched;
 * re-running matches nothing).
 */
return new class extends Migration
{
    private const MAP = [
        'Ditawarkan' => '1',     // DITAWARKAN
        'Diterima' => '2',       // DITERIMA
        'Ditolak' => '4',        // PPUU_AGIH_SEMULA (offer declined -> re-pick)
        'Diserah Semula' => '5', // SELESAI
        'Diserah Se' => '5',     // varchar(10) truncation of 'Diserah Semula'
    ];

    public function up(): void
    {
        foreach (self::MAP as $string => $code) {
            DB::table('forms')->where('status_agihan', $string)->update(['status_agihan' => $code]);
        }
    }

    public function down(): void
    {
        // One-way normalisation. A numeric code cannot be safely reversed to a legacy
        // string (e.g. '4' may be spine-native or an ex-'Ditolak'), so this is a no-op.
        // Legacy strings, if ever reintroduced, are still resolved at read time via
        // App\Support\StatusAgihan::normalise()/bucketValues().
    }
};
