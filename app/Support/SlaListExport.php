<?php

namespace App\Support;

use App\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SLA breach "senarai" list exports (P1 - legacy export_senarai_*.php).
 *
 * Each of the five SLA dashboards (SlaMatrix) has a paired breach LIST: the
 * underlying case rows whose DATEDIFF exceeds the target - i.e. the TIDAK CAPAI
 * side of the matrix, the cell drill-down. All five legacy files are
 * breach-only (the threshold is a hard WHERE), emit a wide case row plus a
 * "TEMPOH MELEBIHI N HARI" day-count column, exclude Kesilapan-Menjana files,
 * require a registered no_fail, and HQ/branch-gate the rows.
 *
 * The SLA date pair + target come from SlaMatrix::definitions() so the list
 * reconciles with the matrix. Two deliberate deviations from the legacy files,
 * both noted inline:
 *   - period (year/month) filters key off the SLA end date, not tarikh_perakuan,
 *     so the list count equals the matrix TIDAK cell (the matrix period filter
 *     is itself a P1 enhancement, not legacy);
 *   - the four "court" reports share one clean column layout with a consistent
 *     TEMPOH position; legacy file 1 (perakuan) had a documented header/value
 *     misalignment plus a one-off extra column, which we do not reproduce.
 *
 * Branch gating is delegated to Eloquent's CawanganScope (HQ roles see all,
 * scoped roles are pinned to their own cawangan) - the same mechanism the other
 * wide exports use, mirroring the legacy $isHQ ? all : own-cawangan logic.
 */
class SlaListExport
{
    /** List-specific title, filename base, day-count header + tail variant per key. */
    public const REPORTS = [
        'perakuan' => [
            'title' => 'SENARAI FAIL PERAKUAN MELEBIHI 40 HARI',
            'file' => 'senarai_fail_perakuan_melebihi_40_hari',
            'tempoh' => 'TEMPOH MELEBIHI 40 HARI',
            'tail' => 'court',
        ],
        'fail-tiada' => [
            'title' => 'SENARAI PEMFAILAN KES TIDAK TERLIBAT PENGANTARAAN',
            'file' => 'senarai_pemfailan_kes_tidak_terlibat_pengantaraan',
            'tempoh' => 'TEMPOH MELEBIHI 60 HARI',
            'tail' => 'court',
        ],
        'fail-terlibat' => [
            'title' => 'SENARAI PEMFAILAN KES TERLIBAT PENGANTARAAN',
            'file' => 'senarai_pemfailan_kes_terlibat_pengantaraan',
            'tempoh' => 'TEMPOH MELEBIHI 120 HARI',
            'tail' => 'court',
        ],
        'serahan' => [
            'title' => 'SENARAI SERAHAN PERINTAH KES',
            'file' => 'senarai_serahan_perintah_kes',
            'tempoh' => 'TEMPOH MELEBIHI 7 HARI',
            'tail' => 'court',
        ],
        'khidmat' => [
            'title' => 'SENARAI KHIDMAT PENGANTARAAN MELEBIHI 60 HARI',
            'file' => 'senarai_khidmat_pengantaraan_melebihi_60_hari',
            'tempoh' => 'TEMPOH PENGANTARAAN MELEBIHI 60 HARI',
            'tail' => 'mediation',
        ],
    ];

    /** True when $key is both a known SLA dashboard and has a paired list. */
    public static function has(string $key): bool
    {
        return SlaMatrix::has($key) && array_key_exists($key, self::REPORTS);
    }

    public static function meta(string $key): array
    {
        return self::REPORTS[$key];
    }

    /**
     * Branch-gated breach query for one dashboard. Eloquent Form (CawanganScope
     * applies the legacy HQ-sees-all / branch-forced-to-own gating), ref_kes
     * joined for JENIS KES, the matrix filter + DATEDIFF>target predicate, and
     * optional period / branch / kategori drill-down filters.
     */
    public static function query(string $key, ?int $year = null, ?int $month = null, ?string $cawangan = null, ?string $kategori = null): Builder
    {
        $def = SlaMatrix::definitions()[$key];
        $start = $def['start'];
        $end = $def['end'];
        $target = (int) $def['target'];

        $q = Form::query()
            ->leftJoin('ref_kes', 'forms.jenis_kes', '=', 'ref_kes.id_kes')
            ->select('forms.*', DB::raw("COALESCE(ref_kes.deskripsi, '".WideExport::NO_DATA."') as jenis_kes_text"))
            ->whereNotNull('forms.'.$start)
            ->whereNotNull('forms.'.$end)
            // breach-only: DATEDIFF over the trusted def date pair exceeds the target.
            ->whereRaw("DATEDIFF(`forms`.`{$end}`, `forms`.`{$start}`) > {$target}")
            // a registered no_fail is required (every legacy senarai export).
            ->whereNotNull('forms.no_fail')->where('forms.no_fail', '!=', '')
            // universal Kesilapan-Menjana exclusion.
            ->where(fn (Builder $w) => $w->whereNull('forms.sebab_tutup_fail')
                ->orWhere('forms.sebab_tutup_fail', '!=', 'Kesilapan Menjana Nombor Fail'));

        self::keyFilters($q, $key);

        // Period filter keys off the SLA end date so the list reconciles with the
        // matrix TIDAK cell (legacy keyed off tarikh_perakuan - see class docblock).
        $q->when($year, fn (Builder $w, $v) => $w->whereYear('forms.'.$end, $v))
            ->when($month, fn (Builder $w, $v) => $w->whereMonth('forms.'.$end, $v))
            ->when($cawangan, fn (Builder $w, $v) => $w->where('forms.cawangan', $v));

        // Restrict to the four matrix kategori so the full list == the sum of the
        // matrix cells; a drill-down kategori narrows to a single column.
        if (filled($kategori)) {
            $q->where('forms.kategori_kes', $kategori);
        } else {
            $q->whereIn('forms.kategori_kes', SlaMatrix::KATEGORI);
        }

        return $q
            ->orderByRaw("CASE WHEN forms.cawangan = 'JBG WP PUTRAJAYA' THEN 0 ELSE 1 END")
            ->orderByDesc('forms.'.$end);
    }

    /** Per-dashboard mirror of the matrix filter (kelulusan/sumbangan/status_pengantaraan). */
    private static function keyFilters(Builder $q, string $key): void
    {
        match ($key) {
            'perakuan' => $q->where('forms.kelulusan', 'Tidak')->where('forms.sumbangan', 'Tiada'),
            'fail-tiada' => $q->where('forms.status_pengantaraan', 'Tidak'),
            'fail-terlibat' => $q->where('forms.status_pengantaraan', 'Ya'),
            default => null,
        };
    }

    /** Ordered [label, resolver] column list for a dashboard key (BIL injected by row()). */
    public static function columns(string $key): array
    {
        $cfg = self::REPORTS[$key];
        $def = SlaMatrix::definitions()[$key];

        // Day-count column: round((end - start) / 86400) . ' hari', matching legacy.
        $tempoh = [$cfg['tempoh'], fn ($r) => self::tempoh($r->{$def['end']} ?? null, $r->{$def['start']} ?? null)];

        return $cfg['tail'] === 'mediation'
            ? self::mediationColumns($tempoh)
            : self::courtColumns($tempoh);
    }

    public static function headers(string $key): array
    {
        return array_map(fn ($c) => $c[0], self::columns($key));
    }

    /** Resolve one row to an ordered value list (BIL prepended). */
    public static function row(object $r, string $key, int $bil): array
    {
        $out = [$bil];
        foreach (self::columns($key) as $c) {
            $out[] = $c[1]($r);
        }

        return $out;
    }

    /** Day-count breach magnitude as "N hari", or "-Tiada Maklumat-" when undatable. */
    public static function tempoh($end, $start): string
    {
        $a = self::ts($end);
        $b = self::ts($start);
        if ($a === null || $b === null) {
            return WideExport::NO_DATA;
        }

        return (int) round(($a - $b) / 86400).' hari';
    }

    private static function ts($v): ?int
    {
        if ($v instanceof Carbon) {
            return $v->getTimestamp();
        }
        $s = trim((string) ($v ?? ''));
        if ($s === '' || str_starts_with($s, '0000-00-00')) {
            return null;
        }
        try {
            return Carbon::parse($s)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- column definitions (verbatim legacy order) --------------------

    /** Shared case-info prefix (legacy cols CAWANGAN .. JENIS KES (JIKA LAIN-LAIN)). */
    private static function caseInfoColumns(): array
    {
        return [
            ['CAWANGAN', fn ($r) => WideExport::na($r->cawangan)],
            ['NO. FAIL JBG', fn ($r) => WideExport::na($r->no_fail)],
            ['TARIKH KHIDMAT NASIHAT', fn ($r) => WideExport::na($r->tarikh_khidmat_nasihat)],
            ['TARIKH PENERIMAAN PERMOHONAN BANTUAN GUAMAN', fn ($r) => WideExport::date($r->tarikh_permohonan)],
            ['BULAN PENERIMAAN BORANG 1', fn ($r) => WideExport::month($r->tarikh_permohonan)],
            ['TAHUN PENERIMAAN BORANG 1', fn ($r) => WideExport::year($r->tarikh_permohonan)],
            ['NAMA ORANG YANG DIBANTU', fn ($r) => WideExport::na($r->nama)],
            ['NO. KAD PENGENALAN', fn ($r) => WideExport::nokp($r->nokp)],
            ['UMUR', fn ($r) => WideExport::na($r->umur)],
            ['JANTINA', fn ($r) => WideExport::na($r->jantina)],
            ['KAUM', fn ($r) => WideExport::na($r->bangsa)],
            ['ETNIK/ SUKU KAUM', fn ($r) => WideExport::na($r->etnik)],
            ['AGAMA', fn ($r) => WideExport::na($r->agama)],
            ['AGAMA (LAIN-LAIN)', fn ($r) => WideExport::na($r->agamaLain)],
            ['STATUS OKU', fn ($r) => WideExport::na($r->oku)],
            ['KATEGORI BIDANG KUASA', fn ($r) => WideExport::na($r->kategori_kes2)],
            ['KELULUSAN MENTERI', fn ($r) => WideExport::na($r->kelulusan)],
            ['KEPUTUSAN MENTERI', fn ($r) => WideExport::na($r->keputusan_menteri)],
            ['KEPUTUSAN PERMOHONAN', fn ($r) => WideExport::na($r->keputusan)],
            ['TARIKH PERAKUAN BANTUAN GUAMAN (BORANG II)', fn ($r) => WideExport::date($r->tarikh_perakuan)],
            ['BULAN BORANG II', fn ($r) => WideExport::month($r->tarikh_perakuan)],
            ['TAHUN BORANG II', fn ($r) => WideExport::year($r->tarikh_perakuan)],
            ['TARIKH PEMBERITAHUAN PEMBERIAN PERAKUAN BANTUAN GUAMAN (BORANG IV)', fn ($r) => WideExport::date($r->tarikh_pemberitahuan_perakuan)],
            ['BULAN BORANG IV', fn ($r) => WideExport::month($r->tarikh_pemberitahuan_perakuan)],
            ['TAHUN BORANG IV', fn ($r) => WideExport::year($r->tarikh_pemberitahuan_perakuan)],
            ['PEGAWAI PENYIASAT', fn ($r) => WideExport::na($r->nama_pegawai_penyiasat)],
            ['JENIS ORANG YANG DIBANTU', fn ($r) => WideExport::na($r->jenis_oyd)],
            ['KATEGORI KES YANG DIDAFTARKAN', fn ($r) => WideExport::na($r->kategori_kes)],
            ['JENIS KATEGORI', fn ($r) => WideExport::na($r->jenis_kategori)],
            ['JENIS JENAYAH DALAM BIDANG KUASA', fn ($r) => WideExport::na($r->jenis_jenayah)],
            ['JENIS KES', fn ($r) => WideExport::na($r->jenis_kes_text ?? null)],
            ['JENIS KES (JIKA LAIN-LAIN)', fn ($r) => WideExport::na($r->jenis_kes_lain ?? null)],
        ];
    }

    /**
     * Court/case layout shared by perakuan / pemfailan / serahan lists. The
     * day-count column is spliced after the court block (legacy file-2/3 slot),
     * one consistent position for all four - see class docblock.
     *
     * @param  array{0:string,1:callable}  $tempoh
     */
    private static function courtColumns(array $tempoh): array
    {
        return array_merge(
            self::caseInfoColumns(),
            [
                ['PIHAK PENGENDALI KES', fn ($r) => WideExport::na($r->agih_kepada)],
                ['PEGAWAI PENGENDALI KES / PEGUAM PANEL', fn ($r) => WideExport::na($r->nama_pegawai_yang_dapat_kes)],
                ['NAMA MAHKAMAH', fn ($r) => WideExport::na($r->nama_mahkamah)],
                ['NO. KES MAHKAMAH', fn ($r) => WideExport::na($r->no_mahkamah)],
                ['TARIKH PEMFAILAN KE MAHKAMAH', fn ($r) => WideExport::na($r->tarikh_pemfailan_kes)],
                $tempoh,
                ['CARA PENYELESAIAN KES', fn ($r) => WideExport::na($r->sebab_selesai)],
                ['CARA PENYELESAIAN KES (LAIN-LAIN)', fn ($r) => WideExport::na($r->alasan_selesai)],
                ['TARIKH SELESAI KES', fn ($r) => WideExport::date($r->tarikh_selesai)],
                ['BULAN SELESAI KES', fn ($r) => WideExport::month($r->tarikh_selesai)],
                ['TAHUN SELESAI KES', fn ($r) => WideExport::year($r->tarikh_selesai)],
                ['TARIKH PERINTAH / KEPUTUSAN MAHKAMAH', fn ($r) => WideExport::na($r->tarikh_perintah)],
                ['TARIKH PERINTAH BERSIH DITERIMA OLEH JBG', fn ($r) => WideExport::na($r->tarikh_perintah_bersih)],
                ['TARIKH SERAHAN PERINTAH BERSIH ORANG YANG DIBANTU', fn ($r) => WideExport::na($r->tarikh_serahan_perintah)],
                ['TARIKH TUTUP FAIL', fn ($r) => WideExport::date($r->tarikh_tutup_fail)],
                ['SEBAB TUTUP FAIL', fn ($r) => WideExport::na($r->sebab_tutup_fail)],
                ['ALASAN PEMINDAHAN FAIL ke CAWANGAN LAIN', fn ($r) => WideExport::na($r->alasan_pemindahan_fail)],
                ['STATUS', fn ($r) => WideExport::na($r->status)],
            ],
        );
    }

    /**
     * Mediation layout (legacy file 5 - khidmat pengantaraan). Replaces the
     * court block with the pengantaraan fields; day-count sits just before STATUS.
     *
     * @param  array{0:string,1:callable}  $tempoh
     */
    private static function mediationColumns(array $tempoh): array
    {
        return array_merge(
            self::caseInfoColumns(),
            [
                ['PERLU PENGANTARAAN', fn ($r) => WideExport::na($r->status_pengantaraan)],
                ['TARIKH PENUGASAN PENGANTARAAN', fn ($r) => WideExport::date($r->tarikh_penugasan)],
                ['BULAN PENUGASAN PENGANTARAAN', fn ($r) => WideExport::month($r->tarikh_penugasan)],
                ['TAHUN PENUGASAN PENGANTARAAN', fn ($r) => WideExport::year($r->tarikh_penugasan)],
                ['NAMA PEGAWAI PENGANTARA', fn ($r) => WideExport::na($r->nama_pegawai)],
                ['PERSETUJUAN PENGANTARAAN', fn ($r) => WideExport::na($r->setuju_pengantara)],
                ['TARIKH PERSETUJUAN PENGANTARAAN', fn ($r) => WideExport::date($r->tarikh_persetujuan_pengantaraan)],
                ['BULAN PERSETUJUAN PENGANTARAAN', fn ($r) => WideExport::month($r->tarikh_persetujuan_pengantaraan)],
                ['TAHUN PERSETUJUAN PENGANTARAAN', fn ($r) => WideExport::year($r->tarikh_persetujuan_pengantaraan)],
                ['KAEDAH SIDANG PENGANTARAAN', fn ($r) => WideExport::na($r->kaedah_sidang)],
                ['LOKASI PEMOHON', fn ($r) => WideExport::na($r->lokasi_pihak_pertama)],
                ['LOKASI RESPONDEN', fn ($r) => WideExport::na($r->lokasi_pihak_kedua)],
                ['LOKASI PEGAWAI PENGANTARA', fn ($r) => WideExport::na($r->lokasi_pegawai_pengantara)],
                ['STATUS SIDANG PENGANTARAAN', fn ($r) => WideExport::na($r->status_sidang)],
                ['CARA PENYELESAIAN PENGANTARAAN', fn ($r) => WideExport::na($r->cara_selesai)],
                ['TARIKH PERJANJIAN PENYELESAIAN', fn ($r) => WideExport::na($r->tarikh_persetujuan)],
                $tempoh,
                ['STATUS', fn ($r) => WideExport::na($r->status)],
            ],
        );
    }
}
