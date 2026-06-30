<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Wide-column report exports (EPIC F — legacy `export_*.php`).
 *
 * Holds the verbatim ordered column lists for the three wide CSVs
 * (Permohonan / Pendaftaran Fail / Status Fail), plus the legacy cell
 * formatters: "-Tiada Maklumat-" blanks, d/m/Y dates with derived BULAN/TAHUN
 * columns, the reason-code decode map, the computed STATUS PEMFAILAN column,
 * and — critically — NoKP emitted as an Excel text formula (`="012345"`) so
 * 12-digit ICs are not mangled into scientific notation.
 *
 * Everything here is pure (no DB, no request): column resolution takes a single
 * row object and returns strings, which makes it unit-testable in isolation.
 * The query, filters and CawanganScope branch-gating live in the controller.
 */
class WideExport
{
    public const NO_DATA = '-Tiada Maklumat-';

    /** ALASAN PERMOHONAN DITOLAK decode (legacy 1-6 reason codes). */
    public const REASON_MAP = [
        '1' => 'Kes Tidak Bermerit',
        '2' => 'Kes Tidak Memenuhi Kriteria',
        '3' => 'Kes telah Selesai di Mahkamah',
        '4' => 'Lain-lain',
        '5' => 'Permohonan telah Ditarik Balik',
        '6' => 'Pemohon Meninggal Dunia',
    ];

    /** Per-report metadata (title, filter labels, query knobs, filename base). */
    public static function meta(string $type): array
    {
        return [
            'permohonan' => [
                'title' => 'LAPORAN PERMOHONAN BANTUAN GUAMAN',
                'tarikh_label' => 'TARIKH PENERIMAAN PERMOHONAN BANTUAN GUAMAN',
                'tarikh_col' => 'tarikh_permohonan',
                'kategori_col' => 'kategori_kes_borang',
                'has_status' => false,
                'file' => 'laporan_permohonan_bantuan_guaman',
            ],
            'pendaftaran-fail' => [
                'title' => 'LAPORAN PENDAFTARAN FAIL KES',
                'tarikh_label' => 'TARIKH PENDAFTARAN FAIL JBG',
                'tarikh_col' => 'tarikh_perakuan',
                'kategori_col' => 'kategori_kes',
                'has_status' => false,
                'file' => 'laporan_pendaftaran_fail_kes',
            ],
            'status-fail' => [
                'title' => 'LAPORAN STATUS FAIL KES',
                'tarikh_label' => 'TARIKH PENDAFTARAN FAIL JBG',
                'tarikh_col' => 'tarikh_perakuan',
                'kategori_col' => 'kategori_kes',
                'has_status' => true,
                'file' => 'laporan_status_fail_kes',
            ],
        ][$type];
    }

    public static function has(string $type): bool
    {
        return in_array($type, ['permohonan', 'pendaftaran-fail', 'status-fail'], true);
    }

    /** Ordered [label, resolver] column list for a report. */
    public static function columns(string $type): array
    {
        return match ($type) {
            'permohonan' => self::permohonanColumns(),
            'pendaftaran-fail' => self::pendaftaranColumns(),
            'status-fail' => self::statusFailColumns(),
        };
    }

    /** Header label row. */
    public static function headers(string $type): array
    {
        return array_map(fn ($c) => $c[0], self::columns($type));
    }

    /**
     * Kesilapan Penjanaan Nombor Fail export columns (legacy
     * export_kesilapan_nombor_fail.php — 36 cols + BIL). Reuses the shared
     * cell formatters; adds the alasan_kesilapan_no_fail column.
     */
    public static function kesilapanColumns(): array
    {
        return [
            ['CAWANGAN', fn ($r) => self::na($r->cawangan)],
            ['NO. FAIL JBG', fn ($r) => self::na($r->no_fail)],
            ['TARIKH KHIDMAT NASIHAT', fn ($r) => self::na($r->tarikh_khidmat_nasihat)],
            ['TARIKH PENERIMAAN PERMOHONAN BANTUAN GUAMAN', fn ($r) => self::date($r->tarikh_permohonan)],
            ['BULAN PENERIMAAN BORANG 1', fn ($r) => self::month($r->tarikh_permohonan)],
            ['TAHUN PENERIMAAN BORANG 1', fn ($r) => self::year($r->tarikh_permohonan)],
            ['NAMA ORANG YANG DIBANTU', fn ($r) => self::na($r->nama)],
            ['NO. KAD PENGENALAN', fn ($r) => self::nokp($r->nokp)],
            ['UMUR', fn ($r) => self::na($r->umur)],
            ['JANTINA', fn ($r) => self::na($r->jantina)],
            ['KAUM', fn ($r) => self::na($r->bangsa)],
            ['ETNIK/ SUKU KAUM', fn ($r) => self::na($r->etnik)],
            ['AGAMA', fn ($r) => self::na($r->agama)],
            ['AGAMA (LAIN-LAIN)', fn ($r) => self::na($r->agamaLain)],
            ['STATUS OKU', fn ($r) => self::na($r->oku)],
            ['KATEGORI BIDANG KUASA', fn ($r) => self::na($r->kategori_kes2)],
            ['KELULUSAN MENTERI', fn ($r) => self::na($r->kelulusan)],
            ['KEPUTUSAN MENTERI', fn ($r) => self::na($r->keputusan_menteri)],
            ['KEPUTUSAN PERMOHONAN', fn ($r) => self::na($r->keputusan)],
            ['TARIKH PERAKUAN BANTUAN GUAMAN (BORANG II)', fn ($r) => self::date($r->tarikh_perakuan)],
            ['BULAN BORANG II', fn ($r) => self::month($r->tarikh_perakuan)],
            ['TAHUN BORANG II', fn ($r) => self::year($r->tarikh_perakuan)],
            ['TARIKH PEMBERITAHUAN PEMBERIAN PERAKUAN BANTUAN GUAMAN (BORANG IV)', fn ($r) => self::date($r->tarikh_pemberitahuan_perakuan)],
            ['BULAN BORANG IV', fn ($r) => self::month($r->tarikh_pemberitahuan_perakuan)],
            ['TAHUN BORANG IV', fn ($r) => self::year($r->tarikh_pemberitahuan_perakuan)],
            ['PEGAWAI PENYIASAT', fn ($r) => self::na($r->nama_pegawai_penyiasat)],
            ['JENIS ORANG YANG DIBANTU', fn ($r) => self::na($r->jenis_oyd)],
            ['KATEGORI KES', fn ($r) => self::na($r->kategori_kes)],
            ['JENIS KATEGORI', fn ($r) => self::na($r->jenis_kategori)],
            ['JENIS JENAYAH DALAM BIDANG KUASA', fn ($r) => self::na($r->jenis_jenayah)],
            ['JENIS KES', fn ($r) => self::na($r->jenis_kes_text ?? null)],
            ['JENIS KES (JIKA LAIN-LAIN)', fn ($r) => self::na($r->jenis_kes_lain ?? null)],
            ['TARIKH TUTUP FAIL', fn ($r) => self::date($r->tarikh_tutup_fail)],
            ['SEBAB TUTUP FAIL', fn ($r) => self::na($r->sebab_tutup_fail)],
            ['ALASAN KESILAPAN NOMBOR FAIL', fn ($r) => self::na($r->alasan_kesilapan_no_fail)],
            ['STATUS', fn ($r) => self::na($r->status)],
        ];
    }

    /** Resolve a kesilapan row (BIL injected) to an ordered value list. */
    public static function kesilapanRow(object $r, int $bil): array
    {
        $out = [$bil];
        foreach (self::kesilapanColumns() as $c) {
            $out[] = $c[1]($r);
        }

        return $out;
    }

    /** Resolve one data row (BIL injected by caller) to an ordered value list. */
    public static function row(object $r, string $type, int $bil): array
    {
        $out = [$bil];
        foreach (self::columns($type) as $c) {
            $out[] = $c[1]($r);
        }

        return $out;
    }

    /**
     * Title + filter-summary "envelope" rows printed before the header row.
     * $filters: ['dari','hingga','kategori','cawangan','status'].
     */
    public static function envelope(string $type, array $filters): array
    {
        $meta = self::meta($type);
        $dari = $filters['dari'] ?? null;
        $hingga = $filters['hingga'] ?? null;

        $tarikh = ($dari || $hingga)
            ? $meta['tarikh_label'].': '.($dari ?: '...').' hingga '.($hingga ?: '...')
            : $meta['tarikh_label'].': Semua Tarikh';

        $rows = [
            [$meta['title']],
            [''],
            [$tarikh],
            ['KATEGORI KES: '.(($filters['kategori'] ?? '') !== '' ? $filters['kategori'] : 'Semua Kategori Kes')],
            ['CAWANGAN: '.(($filters['cawangan'] ?? '') !== '' ? $filters['cawangan'] : 'Semua Cawangan')],
        ];

        if ($meta['has_status']) {
            $rows[] = ['STATUS PEMFAILAN KES: '.(($filters['status'] ?? '') !== '' ? $filters['status'] : 'Semua')];
        }

        $rows[] = [''];

        return $rows;
    }

    // ---- cell formatters (pure) ----------------------------------------

    /** Value or "-Tiada Maklumat-"; Carbon dates rendered d/m/Y. */
    public static function na($v): string
    {
        if ($v instanceof Carbon) {
            return $v->format('d/m/Y');
        }
        $s = trim((string) ($v ?? ''));

        return ($s === '' || $s === '0000-00-00') ? self::NO_DATA : $s;
    }

    /** Strict d/m/Y date, "-Tiada Maklumat-" when absent/invalid. */
    public static function date($v): string
    {
        return self::parse($v)?->format('d/m/Y') ?? self::NO_DATA;
    }

    public static function month($v): string
    {
        return self::parse($v)?->format('m') ?? self::NO_DATA;
    }

    public static function year($v): string
    {
        return self::parse($v)?->format('Y') ?? self::NO_DATA;
    }

    /** NoKP as an Excel text formula so long ICs stay text, not 1.23E+11. */
    public static function nokp($v): string
    {
        $digits = preg_replace('/\D/', '', (string) ($v ?? ''));

        return $digits === '' ? self::NO_DATA : '="'.$digits.'"';
    }

    /** ALASAN PERMOHONAN DITOLAK decode. */
    public static function reason($v): string
    {
        $code = trim((string) ($v ?? ''));

        return self::REASON_MAP[$code] ?? self::NO_DATA;
    }

    /** Computed STATUS PEMFAILAN for the status-fail report. */
    public static function statusPemfailan(object $r): string
    {
        if (($r->status ?? null) === 'Fail Tutup') {
            return 'Fail Tutup';
        }
        if (! self::blank($r->tarikh_selesai ?? null)) {
            return 'Selesai';
        }
        if (! self::blank($r->tarikh_pemfailan_kes ?? null)) {
            return 'Pemfailan Selesai';
        }

        return 'Belum Difailkan';
    }

    private static function blank($v): bool
    {
        if ($v instanceof Carbon) {
            return false;
        }

        return trim((string) ($v ?? '')) === '';
    }

    private static function parse($v): ?Carbon
    {
        if ($v instanceof Carbon) {
            return $v;
        }
        $s = trim((string) ($v ?? ''));
        if ($s === '' || str_starts_with($s, '0000-00-00')) {
            return null;
        }
        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- column definitions (verbatim legacy order) --------------------

    private static function permohonanColumns(): array
    {
        return [
            ['CAWANGAN', fn ($r) => self::na($r->cawangan)],
            ['TARIKH KHIDMAT NASIHAT', fn ($r) => self::date($r->tarikh_khidmat_nasihat)],
            ['BULAN KHIDMAT NASIHAT', fn ($r) => self::month($r->tarikh_khidmat_nasihat)],
            ['TAHUN KHIDMAT NASIHAT', fn ($r) => self::year($r->tarikh_khidmat_nasihat)],
            ['KATEGORI KES BORANG', fn ($r) => self::na($r->kategori_kes_borang)],
            ['TARIKH PENERIMAAN BORANG', fn ($r) => self::date($r->tarikh_permohonan)],
            ['BULAN PENERIMAAN BORANG', fn ($r) => self::month($r->tarikh_permohonan)],
            ['TAHUN PENERIMAAN BORANG', fn ($r) => self::year($r->tarikh_permohonan)],
            ['NAMA PEMOHON', fn ($r) => self::na($r->nama)],
            ['NO. KAD PENGENALAN', fn ($r) => self::nokp($r->nokp)],
            ['UMUR', fn ($r) => self::na($r->umur)],
            ['JANTINA', fn ($r) => self::na($r->jantina)],
            ['KAUM', fn ($r) => self::na($r->bangsa)],
            ['ETNIK/SUKU KAUM', fn ($r) => self::na($r->etnik)],
            ['AGAMA', fn ($r) => self::na($r->agama)],
            ['AGAMA LAIN-LAIN', fn ($r) => self::na($r->agamaLain)],
            ['STATUS OKU', fn ($r) => self::na($r->oku)],
            ['KAEDAH PENERIMAAN BORANG', fn ($r) => self::na($r->kaedah_penerimaan)],
            ['NAMA IBU BAPA / PENJAGA / WAKIL SAH', fn ($r) => self::na($r->nama_penjaga)],
            ['NO. KAD PENGENALAN IBU BAPA/PENJAGA/ WAKIL SAH', fn ($r) => self::nokp($r->nokp_penjaga)],
            ['BORANG PERMOHONAN DIDAFTARKAN OLEH', fn ($r) => self::na($r->didaftarkan_oleh)],
            ['KATEGORI BIDANG KUASA', fn ($r) => self::na($r->kategori_kes2)],
            ['KELULUSAN MENTERI', fn ($r) => self::na($r->kelulusan)],
            ['KEPUTUSAN MENTERI', fn ($r) => self::na($r->keputusan_menteri)],
            ['KEPUTUSAN PERMOHONAN', fn ($r) => self::na($r->keputusan)],
            ['ALASAN PERMOHONAN DITOLAK', fn ($r) => self::reason($r->reason)],
            ['ALASAN PERMOHONAN DITOLAK (JIKA LAIN-LAIN)', fn ($r) => self::na($r->alasan)],
            ['TARIKH PEMAKLUMAN KEPUTUSAN PERMOHONAN', fn ($r) => self::date($r->tarikh_pemakluman)],
            ['BULAN PEMAKLUMAN KEPUTUSAN PERMOHONAN', fn ($r) => self::month($r->tarikh_pemakluman)],
            ['TAHUN PEMAKLUMAN KEPUTUSAN PERMOHONAN', fn ($r) => self::year($r->tarikh_pemakluman)],
            ['KAEDAH PEMAKLUMAN KEPUTUSAN KEPADA PEMOHON', fn ($r) => self::na($r->kaedah_pemakluman)],
            ['SUMBANGAN', fn ($r) => self::na($r->sumbangan)],
            ['NILAI SUMBANGAN', fn ($r) => self::na($r->nilai_sumbangan)],
            ['PEMBATALAN KELULUSAN BORANG 1', fn ($r) => self::na($r->pembatalan_borang_1)],
            ['ALASAN PEMBATALAN KELULUSAN BORANG 1', fn ($r) => self::na($r->alasan_pembatalan ?? null)],
            ['TARIKH PERAKUAN BANTUAN GUAMAN (BORANG II)', fn ($r) => self::date($r->tarikh_perakuan)],
            ['BULAN BORANG II', fn ($r) => self::month($r->tarikh_perakuan)],
            ['TAHUN BORANG II', fn ($r) => self::year($r->tarikh_perakuan)],
            ['TARIKH PEMBERITAHUAN PEMBERIAN PERAKUAN BANTUAN GUAMAN (BORANG IV)', fn ($r) => self::date($r->tarikh_pemberitahuan_perakuan)],
            ['BULAN BORANG IV', fn ($r) => self::month($r->tarikh_pemberitahuan_perakuan)],
            ['TAHUN BORANG IV', fn ($r) => self::year($r->tarikh_pemberitahuan_perakuan)],
            ['PEGAWAI PENYIASAT', fn ($r) => self::na($r->nama_pegawai_penyiasat)],
            ['JENIS ORANG YANG DIBANTU', fn ($r) => self::na($r->jenis_oyd)],
            ['KATEGORI KES YANG DIDAFTARKAN', fn ($r) => self::na($r->kategori_kes)],
            ['JENIS KATEGORI', fn ($r) => self::na($r->jenis_kategori)],
            ['JENIS JENAYAH DALAM BIDANG KUASA', fn ($r) => self::na($r->jenis_jenayah)],
            ['JENIS KES', fn ($r) => self::na($r->jenis_kes_text ?? null)],
            ['JENIS KES (JIKA LAIN-LAIN)', fn ($r) => self::na($r->jenis_kes_lain ?? null)],
            ['STATUS', fn ($r) => self::na($r->status)],
        ];
    }

    private static function pendaftaranColumns(): array
    {
        return [
            ['CAWANGAN', fn ($r) => self::na($r->cawangan)],
            ['NO. FAIL JBG', fn ($r) => self::na($r->no_fail)],
            ['TARIKH PERAKUAN BANTUAN GUAMAN (BORANG II)', fn ($r) => self::date($r->tarikh_perakuan)],
            ['BULAN BORANG II', fn ($r) => self::month($r->tarikh_perakuan)],
            ['TAHUN BORANG II', fn ($r) => self::year($r->tarikh_perakuan)],
            ['NAMA ORANG YANG DIBANTU', fn ($r) => self::na($r->nama)],
            ['NO. KAD PENGENALAN', fn ($r) => self::nokp($r->nokp)],
            ['UMUR', fn ($r) => self::na($r->umur)],
            ['JANTINA', fn ($r) => self::na($r->jantina)],
            ['KAUM', fn ($r) => self::na($r->bangsa)],
            ['ETNIK/ SUKU KAUM', fn ($r) => self::na($r->etnik)],
            ['AGAMA', fn ($r) => self::na($r->agama)],
            ['AGAMA (LAIN-LAIN)', fn ($r) => self::na($r->agamaLain)],
            ['STATUS OKU', fn ($r) => self::na($r->oku)],
            ['KATEGORI BIDANG KUASA', fn ($r) => self::na($r->kategori_kes2)],
            ['JENIS ORANG YANG DIBANTU', fn ($r) => self::na($r->jenis_oyd)],
            ['KATEGORI KES YANG DIDAFTARKAN', fn ($r) => self::na($r->kategori_kes)],
            ['JENIS KATEGORI', fn ($r) => self::na($r->jenis_kategori)],
            ['JENIS JENAYAH DALAM BIDANG KUASA', fn ($r) => self::na($r->jenis_jenayah)],
            ['JENIS KES', fn ($r) => self::na($r->jenis_kes_text ?? null)],
            ['JENIS KES (JIKA LAIN-LAIN)', fn ($r) => self::na($r->jenis_kes_lain ?? null)],
            ['NAMA MAHKAMAH', fn ($r) => self::na($r->nama_mahkamah)],
            ['NO. KES MAHKAMAH', fn ($r) => self::na($r->no_mahkamah)],
            ['TARIKH PEMFAILAN KE MAHKAMAH', fn ($r) => self::na($r->tarikh_pemfailan_kes)],
            ['PEGAWAI PENGANTARA', fn ($r) => self::na($r->nama_pegawai)],
            ['PEGAWAI PENGENDALI KES', fn ($r) => self::na($r->nama_pegawai_yang_dapat_kes)],
            ['STATUS', fn ($r) => self::na($r->status)],
        ];
    }

    private static function statusFailColumns(): array
    {
        return [
            ['CAWANGAN', fn ($r) => self::na($r->cawangan)],
            ['NO. FAIL JBG', fn ($r) => self::na($r->no_fail)],
            ['TARIKH KHIDMAT NASIHAT', fn ($r) => self::na($r->tarikh_khidmat_nasihat)],
            ['TARIKH PENERIMAAN PERMOHONAN BANTUAN GUAMAN', fn ($r) => self::date($r->tarikh_permohonan)],
            ['BULAN PENERIMAAN BORANG 1', fn ($r) => self::month($r->tarikh_permohonan)],
            ['TAHUN PENERIMAAN BORANG 1', fn ($r) => self::year($r->tarikh_permohonan)],
            ['NAMA ORANG YANG DIBANTU', fn ($r) => self::na($r->nama)],
            ['NO. KAD PENGENALAN', fn ($r) => self::nokp($r->nokp)],
            ['UMUR', fn ($r) => self::na($r->umur)],
            ['JANTINA', fn ($r) => self::na($r->jantina)],
            ['KAUM', fn ($r) => self::na($r->bangsa)],
            ['ETNIK/ SUKU KAUM', fn ($r) => self::na($r->etnik)],
            ['AGAMA', fn ($r) => self::na($r->agama)],
            ['AGAMA (LAIN-LAIN)', fn ($r) => self::na($r->agamaLain)],
            ['STATUS OKU', fn ($r) => self::na($r->oku)],
            ['KATEGORI BIDANG KUASA', fn ($r) => self::na($r->kategori_kes2)],
            ['KELULUSAN MENTERI', fn ($r) => self::na($r->kelulusan)],
            ['KEPUTUSAN MENTERI', fn ($r) => self::na($r->keputusan_menteri)],
            ['KEPUTUSAN PERMOHONAN', fn ($r) => self::na($r->keputusan)],
            ['TARIKH PERAKUAN BANTUAN GUAMAN (BORANG II)', fn ($r) => self::date($r->tarikh_perakuan)],
            ['BULAN BORANG II', fn ($r) => self::month($r->tarikh_perakuan)],
            ['TAHUN BORANG II', fn ($r) => self::year($r->tarikh_perakuan)],
            ['TARIKH PEMBERITAHUAN PEMBERIAN PERAKUAN BANTUAN GUAMAN (BORANG IV)', fn ($r) => self::date($r->tarikh_pemberitahuan_perakuan)],
            ['BULAN BORANG IV', fn ($r) => self::month($r->tarikh_pemberitahuan_perakuan)],
            ['TAHUN BORANG IV', fn ($r) => self::year($r->tarikh_pemberitahuan_perakuan)],
            ['PEGAWAI PENYIASAT', fn ($r) => self::na($r->nama_pegawai_penyiasat)],
            ['JENIS ORANG YANG DIBANTU', fn ($r) => self::na($r->jenis_oyd)],
            ['KATEGORI KES YANG DIDAFTARKAN', fn ($r) => self::na($r->kategori_kes)],
            ['JENIS KATEGORI', fn ($r) => self::na($r->jenis_kategori)],
            ['JENIS JENAYAH DALAM BIDANG KUASA', fn ($r) => self::na($r->jenis_jenayah)],
            ['JENIS KES', fn ($r) => self::na($r->jenis_kes_text ?? null)],
            ['JENIS KES (JIKA LAIN-LAIN)', fn ($r) => self::na($r->jenis_kes_lain ?? null)],
            ['PIHAK PENGENDALI KES', fn ($r) => self::na($r->agih_kepada)],
            ['PEGAWAI PENGENDALI KES / PEGUAM PANEL', fn ($r) => self::na($r->nama_pegawai_yang_dapat_kes)],
            ['NAMA MAHKAMAH', fn ($r) => self::na($r->nama_mahkamah)],
            ['NO. KES MAHKAMAH', fn ($r) => self::na($r->no_mahkamah)],
            ['TARIKH PEMFAILAN KE MAHKAMAH', fn ($r) => self::na($r->tarikh_pemfailan_kes)],
            ['STATUS PEMFAILAN', fn ($r) => self::statusPemfailan($r)],
            ['CARA PENYELESAIAN KES', fn ($r) => self::na($r->sebab_selesai)],
            ['CARA PENYELESAIAN KES (LAIN-LAIN)', fn ($r) => self::na($r->alasan_selesai)],
            ['CATATAN', fn ($r) => self::na($r->catatan_penyelesaian ?? null)],
            ['TARIKH SELESAI KES', fn ($r) => self::date($r->tarikh_selesai)],
            ['BULAN SELESAI KES', fn ($r) => self::month($r->tarikh_selesai)],
            ['TAHUN SELESAI KES', fn ($r) => self::year($r->tarikh_selesai)],
            ['TARIKH PERINTAH / KEPUTUSAN MAHKAMAH', fn ($r) => self::na($r->tarikh_perintah)],
            ['TARIKH PERINTAH BERSIH DITERIMA OLEH JBG', fn ($r) => self::na($r->tarikh_perintah_bersih)],
            ['TARIKH SERAHAN PERINTAH BERSIH ORANG YANG DIBANTU', fn ($r) => self::na($r->tarikh_serahan_perintah)],
            ['TARIKH PEMFAILAN PEMBATALAN PERAKUAN KEPADA ORANG YANG DIBANTU', fn ($r) => self::na($r->tarikh_pemberitahuan_oyd)],
            ['TARIKH PEMFAILAN PEMBATALAN PERAKUAN KE MAHKAMAH', fn ($r) => self::na($r->tarikh_pemberitahuan_mahkamah)],
            ['TARIKH TUTUP FAIL', fn ($r) => self::date($r->tarikh_tutup_fail)],
            ['SEBAB TUTUP FAIL', fn ($r) => self::na($r->sebab_tutup_fail)],
            ['ALASAN PEMINDAHAN FAIL ke CAWANGAN LAIN', fn ($r) => self::na($r->alasan_pemindahan_fail)],
            ['STATUS', fn ($r) => self::na($r->status)],
        ];
    }
}
