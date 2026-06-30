<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Support\SlaListExport;
use App\Support\WideExport;
use Tests\TestCase;

/**
 * P1 — SLA breach "senarai" list columns + day-count. Pure (no DB).
 */
class SlaListExportTest extends TestCase
{
    public function test_has_and_meta_cover_the_five_dashboards(): void
    {
        foreach (['perakuan', 'fail-tiada', 'fail-terlibat', 'serahan', 'khidmat'] as $key) {
            $this->assertTrue(SlaListExport::has($key), "missing list for {$key}");
        }
        $this->assertFalse(SlaListExport::has('tiada'));
        $this->assertSame('senarai_serahan_perintah_kes', SlaListExport::meta('serahan')['file']);
    }

    public function test_court_report_columns_have_tempoh_and_court_block(): void
    {
        $labels = array_map(fn ($c) => $c[0], SlaListExport::columns('fail-tiada'));

        $this->assertContains('CAWANGAN', $labels);
        $this->assertContains('NO. FAIL JBG', $labels);
        $this->assertContains('NO. KAD PENGENALAN', $labels);
        $this->assertContains('NAMA MAHKAMAH', $labels);
        $this->assertContains('TEMPOH MELEBIHI 60 HARI', $labels);
        $this->assertContains('STATUS', $labels);
        // court layout must not carry the mediation tail.
        $this->assertNotContains('KAEDAH SIDANG PENGANTARAAN', $labels);
        $this->assertCount(50, SlaListExport::columns('fail-tiada'));
    }

    public function test_each_court_key_carries_its_own_tempoh_label(): void
    {
        $label = fn ($key) => array_map(fn ($c) => $c[0], SlaListExport::columns($key));

        $this->assertContains('TEMPOH MELEBIHI 40 HARI', $label('perakuan'));
        $this->assertContains('TEMPOH MELEBIHI 120 HARI', $label('fail-terlibat'));
        $this->assertContains('TEMPOH MELEBIHI 7 HARI', $label('serahan'));
    }

    public function test_mediation_report_swaps_in_pengantaraan_tail(): void
    {
        $labels = array_map(fn ($c) => $c[0], SlaListExport::columns('khidmat'));

        $this->assertContains('KAEDAH SIDANG PENGANTARAAN', $labels);
        $this->assertContains('LOKASI PEMOHON', $labels);
        $this->assertContains('TEMPOH PENGANTARAAN MELEBIHI 60 HARI', $labels);
        $this->assertContains('STATUS', $labels);
        $this->assertNotContains('NAMA MAHKAMAH', $labels);
    }

    public function test_tempoh_counts_whole_days_and_blanks_when_undatable(): void
    {
        $this->assertSame('59 hari', SlaListExport::tempoh('2026-03-01', '2026-01-01'));
        $this->assertSame(WideExport::NO_DATA, SlaListExport::tempoh(null, '2026-01-01'));
        $this->assertSame(WideExport::NO_DATA, SlaListExport::tempoh('2026-03-01', '0000-00-00'));
    }

    public function test_row_prepends_bil_formats_nokp_and_day_count(): void
    {
        $r = new Form([
            'cawangan' => 'JBG PERLIS', 'no_fail' => 'JBG.X', 'nokp' => '900101-01-5555',
            'status' => 'Aktif', 'tarikh_perakuan' => '2026-01-01', 'tarikh_pemfailan_kes' => '2026-04-01',
        ]);

        $row = SlaListExport::row($r, 'fail-tiada', 7);

        $this->assertSame(7, $row[0]);
        $this->assertSame('JBG PERLIS', $row[1]);
        $this->assertContains('="900101015555"', $row);
        $this->assertContains('90 hari', $row); // 2026-01-01 → 2026-04-01
        $this->assertCount(1 + 50, $row);
    }
}
