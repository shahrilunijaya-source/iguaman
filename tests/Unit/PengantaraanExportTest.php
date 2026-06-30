<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Support\WideExport;
use Tests\TestCase;

/**
 * P1 (rk-pengantaraan slice 1) — wide CSV columns for Penugasan Pengantaraan
 * (34 cols) and Pengantaraan Tidak Dirujuk (14 cols). Pure (no DB).
 */
class PengantaraanExportTest extends TestCase
{
    public function test_has_recognises_the_two_pengantaraan_reports(): void
    {
        $this->assertTrue(WideExport::has('penugasan-pengantaraan'));
        $this->assertTrue(WideExport::has('tidak-dirujuk'));
        $this->assertFalse(WideExport::has('tiada'));
    }

    public function test_penugasan_columns_count_and_key_labels(): void
    {
        $this->assertCount(34, WideExport::columns('penugasan-pengantaraan'));

        $labels = WideExport::headers('penugasan-pengantaraan');
        $this->assertContains('PERSETUJUAN PENGANTARAAN', $labels);
        $this->assertContains('TARIKH PENUGASAN PENGANTARAAN', $labels);
        $this->assertContains('KAEDAH SIDANG PENGANTARAAN', $labels);
        $this->assertContains('NAMA PEGAWAI PENGANTARA', $labels);
        $this->assertSame('CAWANGAN', $labels[0]);
        $this->assertSame('STATUS', $labels[33]);
    }

    public function test_tidak_dirujuk_columns_count_and_key_labels(): void
    {
        $this->assertCount(14, WideExport::columns('tidak-dirujuk'));

        $labels = WideExport::headers('tidak-dirujuk');
        $this->assertContains('PERLU PENGANTARAAN', $labels);
        $this->assertContains('ALASAN TIDAK DIRUJUK PENGANTARAAN', $labels);
        $this->assertSame('CAWANGAN', $labels[0]);
        $this->assertSame('STATUS', $labels[13]);
    }

    public function test_penugasan_row_prepends_bil_and_formats_nokp(): void
    {
        $r = new Form([
            'cawangan' => 'JBG PERLIS', 'no_fail' => 'JBG.X', 'nokp' => '900101-01-5555',
            'status' => 'Aktif', 'tarikh_perakuan' => '2026-02-01', 'tarikh_penugasan' => '2026-03-01',
            'nama_pegawai' => 'PEGAWAI A', 'setuju_pengantara' => 'Ya', 'status_pengantaraan' => 'Ya',
        ]);

        $row = WideExport::row($r, 'penugasan-pengantaraan', 5);

        $this->assertSame(5, $row[0]);
        $this->assertSame('JBG PERLIS', $row[1]);
        $this->assertContains('="900101015555"', $row);
        $this->assertContains('PEGAWAI A', $row);
        $this->assertCount(1 + 34, $row);
    }

    public function test_tidak_dirujuk_row_degrades_missing_spine_columns(): void
    {
        // alasan_tidak_rujuk_pengantaraan is not in the current forms spine → NO_DATA.
        $r = new Form(['cawangan' => 'JBG KEDAH', 'no_fail' => 'JBG.Y', 'status_pengantaraan' => 'Tidak']);

        $row = WideExport::row($r, 'tidak-dirujuk', 1);

        $this->assertSame(1, $row[0]);
        $this->assertSame('Tidak', $row[12]);          // PERLU PENGANTARAAN
        $this->assertSame(WideExport::NO_DATA, $row[13]); // ALASAN TIDAK DIRUJUK (missing col)
        $this->assertCount(1 + 14, $row);
    }
}
