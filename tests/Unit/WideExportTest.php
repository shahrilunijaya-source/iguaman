<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Support\WideExport;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * EPIC F — wide-column export cell logic. Pure (no DB): exercises the legacy
 * formatters (blanks, dates, derived BULAN/TAHUN, reason decode, computed
 * STATUS PEMFAILAN, NoKP-as-Excel-text) and the verbatim column ordering.
 */
class WideExportTest extends TestCase
{
    public function test_column_counts_match_legacy_parity(): void
    {
        // Header lists exclude the BIL counter (injected by row()).
        $this->assertCount(49, WideExport::headers('permohonan'));
        $this->assertCount(27, WideExport::headers('pendaftaran-fail'));
        $this->assertCount(53, WideExport::headers('status-fail'));
    }

    public function test_nokp_emitted_as_excel_text_formula(): void
    {
        $this->assertSame('="900101015555"', WideExport::nokp('900101-01-5555'));
        $this->assertSame('="900101015555"', WideExport::nokp('900101015555'));
        $this->assertSame(WideExport::NO_DATA, WideExport::nokp(''));
        $this->assertSame(WideExport::NO_DATA, WideExport::nokp(null));
    }

    public function test_na_blanks_and_carbon_dates(): void
    {
        $this->assertSame(WideExport::NO_DATA, WideExport::na(null));
        $this->assertSame(WideExport::NO_DATA, WideExport::na(''));
        $this->assertSame(WideExport::NO_DATA, WideExport::na('0000-00-00'));
        $this->assertSame('Sivil', WideExport::na('Sivil'));
        $this->assertSame('15/01/2026', WideExport::na(Carbon::parse('2026-01-15')));
    }

    public function test_date_month_year_helpers(): void
    {
        $this->assertSame('09/03/2026', WideExport::date('2026-03-09'));
        $this->assertSame('03', WideExport::month('2026-03-09'));
        $this->assertSame('2026', WideExport::year('2026-03-09'));
        $this->assertSame(WideExport::NO_DATA, WideExport::date(''));
        $this->assertSame(WideExport::NO_DATA, WideExport::month('0000-00-00'));
    }

    public function test_reason_decode_map(): void
    {
        $this->assertSame('Kes Tidak Bermerit', WideExport::reason('1'));
        $this->assertSame('Pemohon Meninggal Dunia', WideExport::reason('6'));
        $this->assertSame(WideExport::NO_DATA, WideExport::reason('99'));
        $this->assertSame(WideExport::NO_DATA, WideExport::reason(null));
    }

    public function test_status_pemfailan_precedence(): void
    {
        $this->assertSame('Fail Tutup', WideExport::statusPemfailan((object) ['status' => 'Fail Tutup', 'tarikh_selesai' => '2026-01-01', 'tarikh_pemfailan_kes' => '2026-01-01']));
        $this->assertSame('Selesai', WideExport::statusPemfailan((object) ['status' => 'Aktif', 'tarikh_selesai' => '2026-01-01', 'tarikh_pemfailan_kes' => null]));
        $this->assertSame('Pemfailan Selesai', WideExport::statusPemfailan((object) ['status' => 'Aktif', 'tarikh_selesai' => null, 'tarikh_pemfailan_kes' => '2026-01-01']));
        $this->assertSame('Belum Difailkan', WideExport::statusPemfailan((object) ['status' => 'Aktif', 'tarikh_selesai' => null, 'tarikh_pemfailan_kes' => null]));
    }

    public function test_row_prepends_bil_and_resolves_in_order(): void
    {
        // Eloquent Form returns null for unset attributes (matches the real caller);
        // a partial stdClass would warn on undefined properties.
        $r = new Form([
            'cawangan' => 'JBG PERLIS', 'no_fail' => 'JBG.PLS.01', 'tarikh_perakuan' => '2026-02-10',
            'nama' => 'Ujian OYD', 'nokp' => '880808-08-1234', 'status' => 'Aktif',
        ]);

        $row = WideExport::row($r, 'pendaftaran-fail', 7);

        $this->assertSame(7, $row[0]);                 // BIL
        $this->assertSame('JBG PERLIS', $row[1]);      // CAWANGAN
        $this->assertSame('JBG.PLS.01', $row[2]);      // NO. FAIL JBG
        $this->assertSame('10/02/2026', $row[3]);      // TARIKH PERAKUAN
        $this->assertContains('="880808081234"', $row); // NoKP text formula present
        $this->assertCount(1 + count(WideExport::headers('pendaftaran-fail')), $row);
    }

    public function test_envelope_includes_title_and_conditional_status_row(): void
    {
        $statusEnv = WideExport::envelope('status-fail', []);
        $this->assertSame(['LAPORAN STATUS FAIL KES'], $statusEnv[0]);
        $flat = array_merge(...$statusEnv);
        $this->assertTrue((bool) array_filter($flat, fn ($l) => str_starts_with($l, 'STATUS PEMFAILAN KES:')));

        $permEnv = WideExport::envelope('permohonan', ['dari' => '2026-01-01', 'hingga' => '2026-12-31']);
        $flatPerm = array_merge(...$permEnv);
        $this->assertSame(['LAPORAN PERMOHONAN BANTUAN GUAMAN'], $permEnv[0]);
        $this->assertFalse((bool) array_filter($flatPerm, fn ($l) => str_starts_with($l, 'STATUS PEMFAILAN KES:')));
        $this->assertTrue((bool) array_filter($flatPerm, fn ($l) => str_contains($l, '2026-01-01 hingga 2026-12-31')));
    }
}
