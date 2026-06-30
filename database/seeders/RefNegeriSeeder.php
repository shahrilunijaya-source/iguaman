<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ref_negeri — the 16 Malaysian states with their JBG region category (A/B/C, used by SLA).
 * The legacy dump (legacy-domain.sql) is structure-only (--no-data), so a fresh DB has an
 * empty ref_negeri; this seeds it so worktrees / clean installs match production reference data.
 * Idempotent (updateOrInsert by fixed id, bypassing mass-assignment for explicit ids).
 */
class RefNegeriSeeder extends Seeder
{
    private const NEGERI = [
        [1, 'JOHOR', 'A'], [2, 'KEDAH', 'A'], [3, 'KELANTAN', 'A'], [4, 'MELAKA', 'C'],
        [5, 'NEGERI SEMBILAN', 'C'], [6, 'PAHANG', 'C'], [7, 'PULAU PINANG', 'C'], [8, 'PERAK', 'C'],
        [9, 'PERLIS', 'C'], [10, 'SELANGOR', 'C'], [11, 'TERENGGANU', 'A'], [12, 'SABAH', 'B'],
        [13, 'SARAWAK', 'B'], [14, 'WILAYAH PERSEKUTUAN KUALA LUMPUR', 'C'],
        [15, 'WILAYAH PERSEKUTUAN LABUAN', 'B'], [16, 'WILAYAH PERSEKUTUAN PUTRAJAYA', 'C'],
    ];

    public function run(): void
    {
        foreach (self::NEGERI as [$id, $nama, $kategori]) {
            DB::table('ref_negeri')->updateOrInsert(
                ['id' => $id],
                ['nama' => $nama, 'aktif' => '1', 'kategori' => $kategori],
            );
        }
    }
}
