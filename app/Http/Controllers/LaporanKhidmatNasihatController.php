<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\PandanganUuExport;
use App\Exports\PendaftaranKnExport;
use App\Support\LaporanKnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan Khidmat Nasihat — 8 statistical reports over the legal-advisory
 * subsystem (batch 12 slice 2). Separate from the rekod-kes LaporanController.
 *
 * Thin controller: it resolves the request filters + branch scope (via
 * LaporanKnService, which respects KN's explicit cawangan_id isolation) and
 * delegates every aggregation to the service. Detail reports (Pandangan UU,
 * Pendaftaran) export to .xlsx (maatwebsite) and print via the table view's
 * print CSS. All actions are gated by permission:laporan.view at the route.
 */
class LaporanKhidmatNasihatController extends Controller
{
    public function __construct(private LaporanKnService $service) {}

    /** Landing page linking the 8 reports. */
    public function index(): View
    {
        return view('laporan-kn.index');
    }

    // ---- Report 1: Pandangan Undang-Undang (detail + Excel) --------------

    public function pandanganUu(Request $request): View
    {
        $filters = $this->detailFilters($request);
        $rows = $this->service->detailQuery($filters)->paginate(30)->withQueryString();

        return view('laporan-kn.pandangan-uu', $this->detailViewData($request, $rows));
    }

    public function pandanganUuExcel(Request $request): BinaryFileResponse
    {
        $query = $this->service->detailQuery($this->detailFilters($request));

        return Excel::download(new PandanganUuExport($query), 'pandangan-uu-'.now()->format('Ymd-His').'.xlsx');
    }

    // ---- Report 6: Pendaftaran Khidmat Nasihat (detail + Excel) ----------

    public function pendaftaran(Request $request): View
    {
        $filters = $this->detailFilters($request);
        $rows = $this->service->detailQuery($filters)->paginate(30)->withQueryString();

        return view('laporan-kn.pendaftaran', $this->detailViewData($request, $rows));
    }

    public function pendaftaranExcel(Request $request): BinaryFileResponse
    {
        $query = $this->service->detailQuery($this->detailFilters($request));

        return Excel::download(new PendaftaranKnExport($query), 'pendaftaran-kn-'.now()->format('Ymd-His').'.xlsx');
    }

    // ---- Report 2: Cara Mengetahui JBG (pie + table) ---------------------

    public function caraMengetahui(Request $request): View
    {
        $counts = $this->service->caraMengetahuiCounts($this->feedbackFilters($request));

        return view('laporan-kn.cara-mengetahui', array_merge($this->baseViewData($request), [
            'counts' => $counts,
            'buckets' => LaporanKnService::CARA_BUCKETS,
        ]));
    }

    // ---- Report 7: Tahap Kepuasan Pelanggan (pie + table) ----------------

    public function kepuasan(Request $request): View
    {
        $counts = $this->service->kepuasanCounts($this->feedbackFilters($request));

        return view('laporan-kn.kepuasan', array_merge($this->baseViewData($request), [
            'counts' => $counts,
        ]));
    }

    // ---- Report 3: Mengikut Cawangan (stacked bar + table) ---------------

    public function mengikutCawangan(Request $request): View
    {
        $pivot = $this->service->pivotByCawangan($this->pivotFilters($request));

        return view('laporan-kn.mengikut-cawangan', array_merge($this->baseViewData($request), [
            'pivot' => $pivot,
        ]));
    }

    // ---- Report 4: Mengikut Kategori Kes (stacked bar + table) -----------

    public function mengikutKategori(Request $request): View
    {
        $pivot = $this->service->pivotByKategori($this->pivotFilters($request));

        return view('laporan-kn.mengikut-kategori', array_merge($this->baseViewData($request), [
            'pivot' => $pivot,
        ]));
    }

    // ---- Report 5: Mengikut Sub Kategori (table only) --------------------

    public function mengikutSubkategori(Request $request): View
    {
        $pivot = $this->service->pivotBySubkategori($this->pivotFilters($request));

        return view('laporan-kn.mengikut-subkategori', array_merge($this->baseViewData($request), [
            'pivot' => $pivot,
        ]));
    }

    // ---- Report 8: Mengikut Kaum/Jantina (stacked bar + table) -----------

    public function kaumJantina(Request $request): View
    {
        $pivot = $this->service->pivotByKaumJantina($this->feedbackFilters($request) + [
            'id_kategori' => $request->input('id_kategori'),
        ]);

        return view('laporan-kn.kaum-jantina', array_merge($this->baseViewData($request), [
            'pivot' => $pivot,
            'jantina' => LaporanKnService::JANTINA,
        ]));
    }

    // ---- Filter assembly --------------------------------------------------

    /** Branch id this request is limited to (pinned officer or chosen branch). */
    private function branchId(Request $request): ?int
    {
        return $this->service->resolveBranchFilter($request->user(), $request->input('cawangan'));
    }

    /** Filters for the detail reports (1 + 6). */
    private function detailFilters(Request $request): array
    {
        return [
            'cawangan_id' => $this->branchId($request),
            'id_kategori' => $request->input('id_kategori'),
            'id_subkategori' => $request->input('id_subkategori'),
            'bulan' => $request->input('bulan'),
            'tahun' => $request->input('tahun'),
        ];
    }

    /** Filters for the maklum_balas-backed reports (2 + 7 + 8 base). */
    private function feedbackFilters(Request $request): array
    {
        return [
            'cawangan_id' => $this->branchId($request),
            'bulan' => $request->input('bulan'),
            'tahun' => $request->input('tahun'),
        ];
    }

    /** Filters for the month-pivot reports (3 + 4 + 5). */
    private function pivotFilters(Request $request): array
    {
        return [
            'cawangan_id' => $this->branchId($request),
            'tahun' => $request->input('tahun', now()->year),
            'id_kategori' => $request->input('id_kategori'),
        ];
    }

    /** Shared view payload: filter dropdown options + current filter values. */
    private function baseViewData(Request $request): array
    {
        return [
            'filters' => $request->only(['cawangan', 'bulan', 'tahun', 'id_kategori', 'id_subkategori']),
            'cawanganList' => $this->service->cawanganList(),
            'kategoriList' => $this->service->kategoriList(),
            'subkategoriList' => $this->service->subkategoriList(),
            'canChooseBranch' => $this->branchId($request) === null
                || ! $request->user()?->cawangan,
        ];
    }

    /** Detail-report view payload (base + the paginated rows). */
    private function detailViewData(Request $request, $rows): array
    {
        return array_merge($this->baseViewData($request), ['rows' => $rows]);
    }
}
