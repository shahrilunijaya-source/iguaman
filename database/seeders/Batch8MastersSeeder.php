<?php

namespace Database\Seeders;

use App\Models\Cawangan;
use App\Models\RefJawatan;
use App\Models\RefKategoriKn;
use Illuminate\Database\Seeder;

/**
 * Batch 8 foundations seed. Sources (2026-06-30 dig):
 *   - JBG cawangan: 23 branches from live 2in1 data (forms/pegawai_jbg/users.cawangan),
 *     mapped to ref_negeri.id.
 *   - jawatan: 10 real titles from pegawai_jbg.jawatan.
 *   - KN category L1: the 4 "Jenis Khidmat" from the iGuaman FE eligibility screening.
 * JKM / Penjara branches + category L2/L3 are not in any source code — added via CRUD later.
 * Idempotent (updateOrCreate); safe to re-run.
 */
class Batch8MastersSeeder extends Seeder
{
    /** JBG branch => ref_negeri.id (see migration note: legacy int FK, mapped here). */
    private const JBG_CAWANGAN = [
        'JBG PUTRAJAYA' => 16,
        'JBG WP KUALA LUMPUR' => 14,
        'JBG WP LABUAN' => 15,
        'JBG SELANGOR' => 10,
        'JBG NEGERI SEMBILAN' => 5,
        'JBG MELAKA' => 4,
        'JBG JOHOR' => 1,
        'JBG MUAR' => 1,
        'JBG PAHANG' => 6,
        'JBG RAUB' => 6,
        'JBG TERENGGANU' => 11,
        'JBG KELANTAN' => 3,
        'JBG GUA MUSANG' => 3,
        'JBG PERLIS' => 9,
        'JBG KEDAH' => 2,
        'JBG LANGKAWI' => 2,
        'JBG PULAU PINANG' => 7,
        'JBG PERAK' => 8,
        'JBG TAIPING' => 8,
        'JBG SARAWAK' => 13,
        'JBG MIRI' => 13,
        'JBG SIBU' => 13,
        'JBG SABAH' => 12,
    ];

    private const JAWATAN = [
        'PENGARAH NEGERI',
        'KETUA CAWANGAN',
        'PENGARAH LITIGASI DAN NASIHAT SIVIL',
        'PENGARAH LITIGASI DAN NASIHAT SYARIAH',
        'PENGARAH PENGANTARAAN SYARIAH',
        'PENGARAH PEGUAM PANEL DAN PENDAMPING GUAMAN',
        'PEGAWAI UNDANG-UNDANG',
        'PENOLONG PEGAWAI UNDANG-UNDANG',
        'PEGAWAI SYARIAH',
        'PENOLONG PEGAWAI SYARIAH',
    ];

    /** KN level-1 categories ("Jenis Khidmat") — from the FE eligibility screening. */
    private const KATEGORI_KN = ['SIVIL', 'SYARIAH', 'PENDAMPING JENAYAH', 'PENDAMPING GUAMAN'];

    public function run(): void
    {
        foreach (self::JBG_CAWANGAN as $nama => $negeriId) {
            Cawangan::updateOrCreate(
                ['nama' => $nama],
                ['jenis' => 'JBG', 'negeri_id' => $negeriId, 'status_aktif' => true],
            );
        }

        foreach (self::JAWATAN as $nama) {
            RefJawatan::updateOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        foreach (self::KATEGORI_KN as $jenis) {
            RefKategoriKn::updateOrCreate(['jenis_kategori' => $jenis], ['aktif' => true]);
        }
    }
}
