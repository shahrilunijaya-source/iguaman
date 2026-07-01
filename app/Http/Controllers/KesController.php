<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermohonanRequest;
use App\Http\Requests\PindahKesRequest;
use App\Models\Cawangan;
use App\Models\Form;
use App\Models\PemindahanCawangan;
use App\Models\RefKes;
use App\Support\KesService;
use App\Support\TransferCawanganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

// Kes (Case) backbone over the `forms` spine — list/filter/search, detail, and permohonan CRUD.
// Foundation for the rekod-kes domain (pengantaraan/mahkamah build on this).
class KesController extends Controller
{
    // PERF-05: filter dropdowns re-scanned forms with DISTINCT on nearly every page.
    // Cache per user — the Form-based lists are branch-scoped (CawanganScope).
    private const LIST_TTL = 300;

    public function index(Request $request): View
    {
        $filters = $request->only(['cawangan', 'status', 'kategori', 'q']);

        $kes = Form::query()
            ->litigasi() // W9: Pembelaan Awam rows have their own register (pembelaan.index).
            ->when($filters['cawangan'] ?? null, fn ($w, $v) => $w->where('cawangan', $v))
            ->when($filters['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
            ->when($filters['kategori'] ?? null, fn ($w, $v) => $w->where('kategori_kes', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->carian($v))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('kes.index', [
            'kes' => $kes,
            'filters' => $filters,
            'cawanganList' => $this->cawanganList(),
            'statusList' => $this->statusList(),
            'kategoriList' => $this->kategoriList(),
        ]);
    }

    public function show(Form $kes): View
    {
        $kes->load(['laporanKes', 'sejarahPegawai', 'sejarahPeguamPanel', 'sejarahSidang', 'lampiran']);

        return view('kes.show', ['kes' => $kes]);
    }

    public function create(): View
    {
        return view('kes.form', $this->formData(new Form) + ['mode' => 'create']);
    }

    public function store(PermohonanRequest $request, KesService $svc): RedirectResponse
    {
        $kes = $svc->cipta($request->validated(), $request->user());

        return redirect()->route('kes.show', $kes)->with('status', 'Permohonan baharu direkodkan. No. Fail: '.$kes->no_fail);
    }

    /** AJAX duplicate-IC guard (legacy check_nokp.php) — returns prior applications for an IC. */
    public function checkNokp(Request $request): JsonResponse
    {
        $nokp = trim((string) $request->query('nokp', ''));

        if ($nokp === '') {
            return response()->json(['exists' => false, 'records' => []]);
        }

        $records = Form::where('nokp', $nokp)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'nama', 'no_fail', 'jenis_kes', 'status'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'nama' => $f->nama ?: '-',
                'no_fail' => $f->no_fail ?: '-Tiada Maklumat-',
                'jenis_kes' => $f->jenis_kes ?: '-Tiada Maklumat-',
                'status' => $f->status ?: '-',
            ]);

        return response()->json(['exists' => $records->isNotEmpty(), 'records' => $records]);
    }

    /** Closed-files list (Senarai Fail Tutup). */
    public function tutup(Request $request): View
    {
        $filters = $request->only(['cawangan', 'q']);

        $kes = Form::query()
            ->litigasi() // W9: closed Pembelaan Awam files surface in their own register.
            ->whereNotNull('tarikh_tutup_fail')
            ->when($filters['cawangan'] ?? null, fn ($w, $v) => $w->where('cawangan', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->carian($v))
            ->orderByDesc('tarikh_tutup_fail')
            ->paginate(20)
            ->withQueryString();

        return view('kes.index', [
            'kes' => $kes,
            'filters' => $filters,
            'cawanganList' => $this->cawanganList(),
            'statusList' => collect(),
            'kategoriList' => $this->kategoriList(),
            'tutup' => true,
        ]);
    }

    public function edit(Form $kes): View
    {
        return view('kes.form', $this->formData($kes) + ['mode' => 'edit']);
    }

    public function update(PermohonanRequest $request, Form $kes, KesService $svc): RedirectResponse
    {
        $svc->kemaskini($kes, $request->validated());

        return redirect()->route('kes.show', $kes)->with('status', 'Kes dikemaskini.');
    }

    /** W7 — transfer form: pick a destination branch for this case. Gated permission:kes.pindah. */
    public function pindahForm(Form $kes): View
    {
        $pending = PemindahanCawangan::where('jenis_rekod', PemindahanCawangan::JENIS_KES)
            ->where('id_rekod', $kes->id)
            ->where('status', PemindahanCawangan::STATUS_DIPINDAH)
            ->first();

        return view('kes.pindah', [
            'kes' => $kes,
            'cawanganList' => Cawangan::orderBy('nama')->get(['id', 'nama']),
            'pending' => $pending,
        ]);
    }

    /** W7 — execute the case transfer. The service moves the branch label + records the move. */
    public function pindah(PindahKesRequest $request, Form $kes): RedirectResponse
    {
        $data = $request->validated();

        try {
            app(TransferCawanganService::class)->pindahKes($kes, (int) $data['cawangan_tujuan_id'], $data['sebab'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('kes.show', $kes)->with('status', 'Kes dipindahkan. Menunggu cawangan tujuan mengesahkan terima.');
    }

    private function formData(Form $kes): array
    {
        return [
            'kes' => $kes,
            'cawanganList' => $this->cawanganList(),
            'kategoriList' => $this->kategoriList(),
            'jenisList' => Cache::remember('kes:jenis', self::LIST_TTL,
                fn () => RefKes::query()->whereNotNull('jenis_kes')->where('jenis_kes', '!=', '')->distinct()->orderBy('jenis_kes')->pluck('jenis_kes')),
        ];
    }

    private function cawanganList()
    {
        return Cache::remember('kes:cawangan:'.auth()->id(), self::LIST_TTL,
            fn () => Form::query()->whereNotNull('cawangan')->where('cawangan', '!=', '')->distinct()->orderBy('cawangan')->pluck('cawangan'));
    }

    private function kategoriList()
    {
        return Cache::remember('kes:kategori:'.auth()->id(), self::LIST_TTL,
            fn () => Form::query()->whereNotNull('kategori_kes')->where('kategori_kes', '!=', '')->distinct()->orderBy('kategori_kes')->pluck('kategori_kes'));
    }

    private function statusList()
    {
        return Cache::remember('kes:status:'.auth()->id(), self::LIST_TTL,
            fn () => Form::query()->whereNotNull('status')->where('status', '!=', '')->distinct()->orderBy('status')->pluck('status'));
    }
}
