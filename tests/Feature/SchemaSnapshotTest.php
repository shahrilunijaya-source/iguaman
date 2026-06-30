<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * W22 / G-H6 lock (D1 = accept reconstruction as canonical).
 *
 * butiran_peguam_panel_3..6 and sejarah_ppuu were reconstructed from legacy PHP
 * forms, not from an authoritative DB dump. We accept the current schema as canonical
 * and lock it here: this snapshot asserts the columns the consolidation audit warned
 * could be silently lost in ETL (CSO certs, eVendor, ADR, member/accreditation certs,
 * firm + bank details, and the 3-tier assignment spine) remain present. A migration
 * that drops any of them fails this test. Additive columns are allowed (subset check).
 */
class SchemaSnapshotTest extends TestCase
{
    /** @var array<string,array<int,string>> */
    private const SNAPSHOT = [
        'butiran_peguam_panel_3' => [
            'id', 'kpBaru', 'clpNumber', 'clpMula', 'clpAkhir',
            'csoNumber1', 'cso1Tauliah', 'cso1Mula', 'cso1Akhir',
            'csoNumber5', 'cso5Tauliah', 'cso5Mula', 'cso5Akhir',
            'lokasiBerguam1', 'lokasiBerguam1_status',
            'ybgk_kelulusan', 'adr_penimbangtara', 'adr_pengantara',
            'sijilAhli_nombor', 'sijilAhli_namaBadan',
            'sijilAkreditasi_nombor', 'sijilAkreditasi_namaBadan',
            'eVendor_daftar', 'eVendor_ID',
        ],
        'butiran_peguam_panel_4' => [
            'id', 'kpBaru', 'namaFirma', 'alamatFirma1', 'poskodFirma', 'negeriFirma',
            'noTelFirma', 'namaInsurans', 'noPolisi', 'amaunPerlindungan', 'polisiMula', 'polisiAkhir',
        ],
        'butiran_peguam_panel_5' => [
            'id', 'kpBaru', 'namaBank', 'noAkaunBank', 'alamatBank1', 'poskodBank', 'negeriBank',
        ],
        'butiran_peguam_panel_6' => [
            'id', 'kpBaru', 'category', 'checkbox_value', 'checkbox_value_status',
            'jenisKemaskini', 'modifiedBy', 'modifiedDate', 'ulasanPengarah',
        ],
        'sejarah_ppuu' => [
            'id', 'id_kes', 'idPPUU', 'tarikh_diberiAgihan', 'statusAgihan', 'statusMohonPP',
            'tarikh_syorPPUU', 'status_rekod', 'status_sokonganPengarah', 'ulasanPengarah',
            'tarikh_PengarahKemaskini', 'status_KP', 'ulasanKP', 'tarikh_KPKemaskini',
            'pilihan_Agihan', 'cawangan_peguampanel', 'nama_peguampanel', 'kpBaru_peguampanel',
            'ulasanPPUU', 'createdDate', 'createdBy', 'modifiedBy', 'modifiedDate',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    public function test_reconstructed_tables_retain_their_columns(): void
    {
        foreach (self::SNAPSHOT as $table => $expected) {
            $this->assertTrue(Schema::hasTable($table), "Missing reconstructed table: {$table}");

            $actual = Schema::getColumnListing($table);
            $missing = array_diff($expected, $actual);

            $this->assertEmpty(
                $missing,
                "Table {$table} lost columns the audit flagged as ETL-fragile: ".implode(', ', $missing)
            );
        }
    }
}
