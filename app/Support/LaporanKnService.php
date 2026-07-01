<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\MaklumBalas;
use App\Models\RefKategoriKn;
use App\Models\RefSubkategoriKn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Khidmat Nasihat statistical reports - batch 12 slice 2.
 *
 * Branch-scoped aggregation queries behind the 8 KN reports. KhidmatNasihat has
 * NO CawanganScope (it keys on cawangan_id, not the legacy `cawangan` string
 * column), so branch isolation is applied EXPLICITLY here, reusing the same
 * resolution rule as {@see KhidmatProsesService::branchFilter()}:
 *
 *   - a staff officer pinned to a branch string (and without cawangan.view-all)
 *     is forced to that branch's cawangan_id - they cannot widen via the filter;
 *   - a cawangan.view-all / no-branch user (or a lawyer) sees ALL branches and
 *     MAY narrow to one branch via the `cawangan` (branch name) filter.
 *
 * Month/year grouping is MONTH(created_at) / YEAR(created_at) on khidmat_nasihat.
 * Reports 2 & 7 join maklum_balas -> khidmat_nasihat and filter on the KN's
 * cawangan_id + created_at.
 */
class LaporanKnService
{
    /** Soalan 1 buckets (how the applicant heard of JBG) -> display label. */
    public const CARA_BUCKETS = [
        'soalan_1a' => 'Portal',
        'soalan_1b' => 'Media Sosial',
        'soalan_1c' => 'Rujukan Keluarga / Rakan',
        'soalan_1d' => 'Jabatan / Agensi',
        'soalan_1e' => 'Lain-lain',
    ];

    /** Satisfaction levels (soalan_2a) in display order. */
    public const KEPUASAN_LEVELS = ['CEMERLANG', 'BAIK', 'KURANG_MEMUASKAN'];

    /** Gender columns for the kaum × jantina pivot. */
    public const JANTINA = ['Lelaki', 'Perempuan'];

    public function __construct(private KhidmatProsesService $proses) {}

    /**
     * Resolve the cawangan_id a request is limited to (null = all branches).
     *
     * A pinned officer is forced to their branch and may not widen. A view-all /
     * no-branch user defaults to all branches but may narrow to one branch via
     * the supplied branch name.
     */
    public function resolveBranchFilter(?User $user, ?string $cawanganName): ?int
    {
        $pinned = $this->proses->branchFilter($user);
        if ($pinned !== null) {
            return $pinned;
        }

        if (filled($cawanganName)) {
            return Cawangan::where('nama', $cawanganName)->value('id');
        }

        return null;
    }

    /** Branch dropdown list (only meaningful for view-all users). */
    public function cawanganList(): Collection
    {
        return Cawangan::orderBy('nama')->pluck('nama', 'id');
    }

    // ---- Detail reports (1 Pandangan UU, 6 Pendaftaran) -------------------

    /**
     * Detail-list query, branch-scoped + filtered. Eager-loads relations used by
     * both detail reports to avoid N+1 (kategori/subkategori/cawangan/temuJanji).
     *
     * @param  array{cawangan_id?:int|null,id_kategori?:int|string,id_subkategori?:int|string,bulan?:int|string,tahun?:int|string}  $f
     */
    public function detailQuery(array $f): Builder
    {
        $branchId = $f['cawangan_id'] ?? null;

        return KhidmatNasihat::query()
            ->with(['cawangan', 'kategori', 'subkategori', 'temuJanji'])
            ->when($branchId !== null, fn ($w) => $w->where('cawangan_id', $branchId))
            ->when($f['id_kategori'] ?? null, fn ($w, $v) => $w->where('id_kategori', $v))
            ->when($f['id_subkategori'] ?? null, fn ($w, $v) => $w->where('id_subkategori', $v))
            ->when($f['bulan'] ?? null, fn ($w, $v) => $w->whereMonth('created_at', $v))
            ->when($f['tahun'] ?? null, fn ($w, $v) => $w->whereYear('created_at', $v))
            ->orderByDesc('id');
    }

    // ---- Bucket-aggregate reports (2 Cara Mengetahui, 7 Kepuasan) ---------

    /**
     * Report 2 - count of each soalan_1 bucket across maklum_balas joined to KN.
     *
     * @param  array{cawangan_id?:int|null,bulan?:int|string,tahun?:int|string}  $f
     * @return array<string,int> bucket-key => count (every CARA_BUCKETS key present)
     */
    public function caraMengetahuiCounts(array $f): array
    {
        $sums = $this->feedbackQuery($f)
            ->selectRaw(
                'SUM(maklum_balas.soalan_1a) a, SUM(maklum_balas.soalan_1b) b, '.
                'SUM(maklum_balas.soalan_1c) c, SUM(maklum_balas.soalan_1d) d, '.
                'SUM(maklum_balas.soalan_1e) e'
            )
            ->first();

        return [
            'soalan_1a' => (int) ($sums->a ?? 0),
            'soalan_1b' => (int) ($sums->b ?? 0),
            'soalan_1c' => (int) ($sums->c ?? 0),
            'soalan_1d' => (int) ($sums->d ?? 0),
            'soalan_1e' => (int) ($sums->e ?? 0),
        ];
    }

    /**
     * Report 7 - count of each satisfaction level across maklum_balas joined to KN.
     *
     * @param  array{cawangan_id?:int|null,bulan?:int|string,tahun?:int|string}  $f
     * @return array<string,int> level => count (every KEPUASAN_LEVELS key present)
     */
    public function kepuasanCounts(array $f): array
    {
        $rows = $this->feedbackQuery($f)
            ->selectRaw('maklum_balas.soalan_2a as level, COUNT(*) as total')
            ->groupBy('maklum_balas.soalan_2a')
            ->pluck('total', 'level');

        $counts = [];
        foreach (self::KEPUASAN_LEVELS as $level) {
            $counts[$level] = (int) ($rows[$level] ?? 0);
        }

        return $counts;
    }

    /**
     * maklum_balas rows joined to their KN, branch-scoped + date-filtered on the
     * KN (created_at / cawangan_id), so the same scoping rule applies everywhere.
     *
     * @param  array{cawangan_id?:int|null,bulan?:int|string,tahun?:int|string}  $f
     */
    private function feedbackQuery(array $f): Builder
    {
        $branchId = $f['cawangan_id'] ?? null;

        return MaklumBalas::query()
            ->join('khidmat_nasihat', 'khidmat_nasihat.id', '=', 'maklum_balas.khidmat_nasihat_id')
            ->when($branchId !== null, fn ($w) => $w->where('khidmat_nasihat.cawangan_id', $branchId))
            ->when($f['bulan'] ?? null, fn ($w, $v) => $w->whereMonth('khidmat_nasihat.created_at', $v))
            ->when($f['tahun'] ?? null, fn ($w, $v) => $w->whereYear('khidmat_nasihat.created_at', $v));
    }

    // ---- Month pivots (3 Cawangan, 4 Kategori, 5 Subkategori) -------------

    /**
     * Report 3 - branch (rows) × 12 months count of KN.
     *
     * @param  array{cawangan_id?:int|null,tahun?:int|string,id_kategori?:int|string}  $f
     * @return list<array{label:string,months:array<int,int>,total:int}>
     */
    public function pivotByCawangan(array $f): array
    {
        $rows = $this->monthPivotBase($f)
            ->whereNotNull('khidmat_nasihat.cawangan_id')
            ->when($f['id_kategori'] ?? null, fn ($w, $v) => $w->where('khidmat_nasihat.id_kategori', $v))
            ->join('cawangan', 'cawangan.id', '=', 'khidmat_nasihat.cawangan_id')
            ->selectRaw('cawangan.nama as label, MONTH(khidmat_nasihat.created_at) as bulan, COUNT(*) as total')
            ->groupBy('cawangan.nama', 'bulan')
            ->orderBy('cawangan.nama')
            ->get();

        return $this->shapeMonthPivot($rows);
    }

    /**
     * Report 4 - kategori (rows) × 12 months count of KN.
     *
     * @param  array{cawangan_id?:int|null,tahun?:int|string}  $f
     * @return list<array{label:string,months:array<int,int>,total:int}>
     */
    public function pivotByKategori(array $f): array
    {
        $rows = $this->monthPivotBase($f)
            ->whereNotNull('khidmat_nasihat.id_kategori')
            ->join('ref_kategori_kn', 'ref_kategori_kn.id', '=', 'khidmat_nasihat.id_kategori')
            ->selectRaw('ref_kategori_kn.jenis_kategori as label, MONTH(khidmat_nasihat.created_at) as bulan, COUNT(*) as total')
            ->groupBy('ref_kategori_kn.jenis_kategori', 'bulan')
            ->orderBy('ref_kategori_kn.jenis_kategori')
            ->get();

        return $this->shapeMonthPivot($rows);
    }

    /**
     * Report 5 - subkategori (rows) × 12 months count of KN.
     *
     * @param  array{cawangan_id?:int|null,tahun?:int|string,id_kategori?:int|string}  $f
     * @return list<array{label:string,months:array<int,int>,total:int}>
     */
    public function pivotBySubkategori(array $f): array
    {
        $rows = $this->monthPivotBase($f)
            ->whereNotNull('khidmat_nasihat.id_subkategori')
            ->when($f['id_kategori'] ?? null, fn ($w, $v) => $w->where('khidmat_nasihat.id_kategori', $v))
            ->join('ref_subkategori_kn', 'ref_subkategori_kn.id', '=', 'khidmat_nasihat.id_subkategori')
            ->selectRaw('ref_subkategori_kn.nama as label, MONTH(khidmat_nasihat.created_at) as bulan, COUNT(*) as total')
            ->groupBy('ref_subkategori_kn.nama', 'bulan')
            ->orderBy('ref_subkategori_kn.nama')
            ->get();

        return $this->shapeMonthPivot($rows);
    }

    /** Branch-scoped + year-filtered base for the month pivots. */
    private function monthPivotBase(array $f): Builder
    {
        $branchId = $f['cawangan_id'] ?? null;

        return KhidmatNasihat::query()
            ->when($branchId !== null, fn ($w) => $w->where('khidmat_nasihat.cawangan_id', $branchId))
            ->when($f['tahun'] ?? null, fn ($w, $v) => $w->whereYear('khidmat_nasihat.created_at', $v));
    }

    /**
     * Fold flat {label,bulan,total} rows into one entry per label with a dense
     * 1..12 month map and a row total.
     *
     * @param  Collection<int,object>  $rows
     * @return list<array{label:string,months:array<int,int>,total:int}>
     */
    private function shapeMonthPivot($rows): array
    {
        $pivot = [];
        foreach ($rows as $r) {
            $label = (string) $r->label;
            if (! isset($pivot[$label])) {
                $pivot[$label] = ['label' => $label, 'months' => array_fill(1, 12, 0), 'total' => 0];
            }
            $pivot[$label]['months'][(int) $r->bulan] = (int) $r->total;
            $pivot[$label]['total'] += (int) $r->total;
        }

        return array_values($pivot);
    }

    // ---- Report 8: Kaum × Jantina pivot ----------------------------------

    /**
     * Report 8 - bangsa (rows) × jantina (Lelaki/Perempuan cols) count of KN.
     *
     * @param  array{cawangan_id?:int|null,bulan?:int|string,tahun?:int|string,id_kategori?:int|string}  $f
     * @return list<array{label:string,Lelaki:int,Perempuan:int,total:int}>
     */
    public function pivotByKaumJantina(array $f): array
    {
        $branchId = $f['cawangan_id'] ?? null;

        $rows = KhidmatNasihat::query()
            ->whereNotNull('bangsa')->where('bangsa', '!=', '')
            ->when($branchId !== null, fn ($w) => $w->where('cawangan_id', $branchId))
            ->when($f['bulan'] ?? null, fn ($w, $v) => $w->whereMonth('created_at', $v))
            ->when($f['tahun'] ?? null, fn ($w, $v) => $w->whereYear('created_at', $v))
            ->when($f['id_kategori'] ?? null, fn ($w, $v) => $w->where('id_kategori', $v))
            ->selectRaw('bangsa as label, jantina_mangsa, COUNT(*) as total')
            ->groupBy('bangsa', 'jantina_mangsa')
            ->orderBy('bangsa')
            ->get();

        $pivot = [];
        foreach ($rows as $r) {
            $label = (string) $r->label;
            if (! isset($pivot[$label])) {
                $pivot[$label] = ['label' => $label, 'Lelaki' => 0, 'Perempuan' => 0, 'total' => 0];
            }
            $col = in_array($r->jantina_mangsa, self::JANTINA, true) ? $r->jantina_mangsa : null;
            if ($col !== null) {
                $pivot[$label][$col] += (int) $r->total;
            }
            $pivot[$label]['total'] += (int) $r->total;
        }

        return array_values($pivot);
    }

    // ---- Filter option lists ---------------------------------------------

    /** Active kategori options for filter dropdowns. */
    public function kategoriList(): Collection
    {
        return RefKategoriKn::orderBy('jenis_kategori')->pluck('jenis_kategori', 'id');
    }

    /** Active subkategori options for filter dropdowns. */
    public function subkategoriList(): Collection
    {
        return RefSubkategoriKn::orderBy('nama')->pluck('nama', 'id');
    }
}
