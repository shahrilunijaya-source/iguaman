<?php

namespace App\Http\Controllers;

use App\Http\Requests\KhidmatNasihatRequest;
use App\Http\Requests\KhidmatSaringanRequest;
use App\Http\Requests\PindahKnRequest;
use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\MahkamahSivil;
use App\Models\MahkamahSyariah;
use App\Models\PemindahanCawangan;
use App\Models\RefKategoriKn;
use App\Models\RefNegeri;
use App\Support\Audit;
use App\Support\KhidmatNasihatService;
use App\Support\KnFormMapper;
use App\Support\LejarTuntutanService;
use App\Support\SlotAvailabilityService;
use App\Support\TransferCawanganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Khidmat Nasihat (legal-advisory applications) — batch 9.
 *
 *  - index/show (slice 1): read-only list + detail, gated permission:khidmat.view.
 *  - create/store/edit/update (slice 2): the staff-driven "Permohonan Baharu"
 *    wizard (jenis_permohonan = DIRI_SENDIRI), gated permission:khidmat.manage.
 *    A final submit computes the fee (KhidmatBayaran), books an appointment slot
 *    (SlotAvailabilityService → temu_janji, both-way link), and sets status BAHARU.
 *  - saringan/saringanSemak + Sebagai-Wakil variants (slice 3): the 3-modal
 *    eligibility gate (screening → income declaration → terms), and the
 *    SEBAGAI_WAKIL contexts penjara/JKM/mahkamah (penjara+JKM are fee-exempt).
 */
class KhidmatNasihatController extends Controller
{
    public function __construct(
        private readonly SlotAvailabilityService $slots,
        private readonly KhidmatNasihatService $service,
        private readonly LejarTuntutanService $lejar,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status_kn', 'status_bayaran']);

        $khidmat = KhidmatNasihat::query()
            ->with(['cawangan', 'kategori'])
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($q) => $q
                ->where('no_permohonan', 'like', "%{$v}%")
                ->orWhere('nama_mangsa', 'like', "%{$v}%")))
            ->when($filters['status_kn'] ?? null, fn ($w, $v) => $w->where('status_kn', $v))
            ->when(($filters['status_bayaran'] ?? '') !== '', fn ($w) => $w->where('status_bayaran', $filters['status_bayaran'] === '1'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('khidmat-nasihat.index', [
            'khidmat' => $khidmat,
            'filters' => $filters,
            'statusList' => KhidmatNasihat::STATUS_KN,
        ]);
    }

    public function show(KhidmatNasihat $khidmat): View|RedirectResponse
    {
        if ($block = $this->assertBranchAccess($khidmat)) {
            return $block;
        }

        $khidmat->load(['pengguna', 'cawangan', 'kategori', 'subkategori', 'temuJanji']);

        return view('khidmat-nasihat.show', ['khidmat' => $khidmat]);
    }

    /** W3 — transfer form: pick a destination branch for this advisory. Gated permission:khidmat.manage. */
    public function pindahForm(Request $request, KhidmatNasihat $khidmat): View|RedirectResponse
    {
        if ($block = $this->assertBranchAccess($khidmat)) {
            return $block;
        }

        $pending = PemindahanCawangan::where('jenis_rekod', PemindahanCawangan::JENIS_KN)
            ->where('id_rekod', $khidmat->id)
            ->where('status', PemindahanCawangan::STATUS_DIPINDAH)
            ->first();

        return view('khidmat-nasihat.pindah', [
            'khidmat' => $khidmat,
            'cawanganList' => Cawangan::orderBy('nama')->get(['id', 'nama']),
            'pending' => $pending,
        ]);
    }

    /** W3 — execute the KN transfer. The service moves cawangan_id + records the move. */
    public function pindah(PindahKnRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $data = $request->validated();

        try {
            app(TransferCawanganService::class)->pindahKn($khidmat, (int) $data['cawangan_tujuan_id'], $data['sebab'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('khidmat.show', $khidmat)->with('status', 'Khidmat Nasihat dipindahkan. Menunggu cawangan tujuan mengesahkan terima.');
    }

    /**
     * Eligibility 3-modal screening page (slice 3 — FE khidmatnasihat/index).
     * A citizen-context applicant must clear: (1) saringan/jenis + income
     * declaration, (2) eligibility questions, (3) terms & conditions — before
     * the create wizard opens. Staff-driven here; gated khidmat.manage.
     */
    public function saringan(): View
    {
        return view('khidmat-nasihat.saringan', [
            'outcome' => session('saringan'),
        ]);
    }

    /**
     * Server-side screening gate. Disqualifying answers (prior advice / excluded
     * matter) or unaccepted terms BLOCK the applicant; a clean pass stores the
     * outcome in the session and forwards to the create wizard.
     */
    public function saringanSemak(KhidmatSaringanRequest $request): RedirectResponse
    {
        $jenis = $request->input('saringan_jenis') === 'pendamping_jenayah'
            ? KhidmatNasihat::SARINGAN_PENDAMPING
            : KhidmatNasihat::SARINGAN_SIVIL_SYARIAH;

        $isSivilSyariah = $jenis === KhidmatNasihat::SARINGAN_SIVIL_SYARIAH;

        // Eligibility questions: both must be answered "Ya" to remain eligible.
        $eligible = $request->input('tiada_nasihat_terdahulu') === 'Ya'
            && $request->input('tiada_perkara_dikecualikan') === 'Ya';

        if (! $eligible) {
            return redirect()->route('khidmat.saringan')
                ->with('saringan_gagal', 'Anda tidak layak memohon kerana tidak memenuhi syarat kelayakan.');
        }

        // Income gate (sivil/syariah only): "Tidak" (income > RM50k) → Laluan Sumbangan (RM260).
        $sumbangan = $isSivilSyariah && $request->input('pendapatan_bawah_had') === 'Tidak';

        $request->session()->put('saringan', [
            'jenis' => $jenis,
            'lulus' => true,
            'sumbangan' => $sumbangan,
        ]);

        return redirect()->route('khidmat.create');
    }

    /**
     * Enforce the eligibility screening as a hard gate before a citizen-context
     * final submit. Only DIRI_SENDIRI applications (the FE saringan flow) are
     * gated — SEBAGAI_WAKIL is officer-driven and skips citizen screening, and a
     * draft save is allowed through. The pass flag is read from the SESSION
     * (authoritative), never the client-supplied hidden field, so a tampered
     * POST cannot fake a pass.
     */
    private function assertSaringanGate(KhidmatNasihatRequest $request): void
    {
        if ($request->isWakil() || ! $request->isHantar()) {
            return;
        }

        abort_unless(
            session('saringan.lulus') === true,
            403,
            'Saringan kelayakan diperlukan sebelum permohonan dihantar.'
        );
    }

    public function create(): View
    {
        $outcome = session('saringan');

        return view('khidmat-nasihat.form', $this->formData(new KhidmatNasihat([
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'is_percuma' => false,
            'saringan_jenis' => $outcome['jenis'] ?? null,
            'saringan_lulus' => (bool) ($outcome['lulus'] ?? false),
            'is_laluan_sumbangan' => (bool) ($outcome['sumbangan'] ?? false),
        ]), 'create'));
    }

    public function store(KhidmatNasihatRequest $request): RedirectResponse
    {
        $this->assertSaringanGate($request);

        $khidmat = DB::transaction(function () use ($request) {
            $kn = $this->service->create(KnFormMapper::map($request));

            if ($request->isHantar()) {
                $this->service->bookSlot(
                    $kn,
                    $request->validated()['tarikh_temu_janji'],
                    $request->validated()['masa_temu_janji'],
                    $request->user()->name,
                );
            }

            return $kn;
        });

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::INSERT,
            "Permohonan Khidmat Nasihat baharu: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        $this->service->storeWaiver($khidmat, $request);

        return redirect()->route('khidmat.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf disimpan.');
    }

    public function edit(KhidmatNasihat $khidmat): View|RedirectResponse
    {
        if ($block = $this->assertBranchAccess($khidmat)) {
            return $block;
        }

        abort_unless($khidmat->status_kn === KhidmatNasihat::STATUS_DRAF, 403, 'Hanya draf boleh dikemaskini.');

        return view('khidmat-nasihat.form', $this->formData($khidmat, 'edit'));
    }

    public function update(KhidmatNasihatRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        if ($block = $this->assertBranchAccess($khidmat)) {
            return $block;
        }

        abort_unless($khidmat->status_kn === KhidmatNasihat::STATUS_DRAF, 403, 'Hanya draf boleh dikemaskini.');

        DB::transaction(function () use ($request, $khidmat) {
            $khidmat->update(KnFormMapper::map($request));

            if ($request->isHantar() && $khidmat->id_temu_janji === null) {
                $this->service->bookSlot(
                    $khidmat,
                    $request->validated()['tarikh_temu_janji'],
                    $request->validated()['masa_temu_janji'],
                    $request->user()->name,
                );
            }
        });

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE,
            "Kemaskini Khidmat Nasihat: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        $this->service->storeWaiver($khidmat, $request);

        return redirect()->route('khidmat.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf dikemaskini.');
    }

    /**
     * W2 — record a manual (counter) payment of the KN intake fee: receipt details +
     * an optional resit upload. Flips the KN payment flag and stamps the central ledger
     * row (DIBAYAR). Gated permission:khidmat.proses. Schema future-proofed for a live
     * iPayment gateway by the open kaedah_bayaran set (TUNAI today, IPAYMENT later).
     */
    public function rekodBayaran(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        if ($block = $this->assertBranchAccess($khidmat)) {
            return $block;
        }

        abort_if($khidmat->is_percuma || (float) $khidmat->jumlah_bayaran <= 0, 403,
            'Tiada bayaran untuk direkod bagi permohonan ini.');
        abort_if((bool) $khidmat->status_bayaran, 403, 'Bayaran telah direkod.');

        $data = $request->validate([
            'nombor_resit' => ['required', 'string', 'max:50'],
            'tarikh_resit' => ['required', 'date'],
            'kaedah_bayaran' => ['required', Rule::in(['TUNAI', 'KAD', 'BANK_IN', 'EWALLET', 'IPAYMENT'])],
            'rujukan_bayaran' => ['nullable', 'string', 'max:100'],
            'lampiran_resit' => ['nullable', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        DB::transaction(function () use ($request, $khidmat, $data) {
            if ($request->hasFile('lampiran_resit')) {
                $row = $this->service->storeLampiran($khidmat, $request->file('lampiran_resit'), 'Resit Bayaran');
                $khidmat->update(['id_lampiran_resit' => $row->id]);
            }

            $this->lejar->rekodBayaranKn($khidmat, [
                'nombor_resit' => $data['nombor_resit'],
                'tarikh_resit' => $data['tarikh_resit'],
                'kaedah_bayaran' => $data['kaedah_bayaran'],
                'rujukan_bayaran' => $data['rujukan_bayaran'] ?? null,
            ], $request->user()->name);
        });

        return redirect()->route('khidmat.show', $khidmat)->with('status', 'Bayaran direkod.');
    }

    // ---- Internals ----

    /**
     * Branch read/write guard for a route-model-bound KN. KhidmatNasihat has no
     * CawanganScope, so a branch-pinned officer (without cawangan.view-all) could
     * otherwise read/edit any branch's KN by id (cross-branch IDOR). Mirrors the
     * D2 dual-branch rule: own-branch OR origin-of-a-transfer (cawangan_asal_id).
     * Returns a redirect to bounce the request, or null when access is allowed.
     */
    private function assertBranchAccess(KhidmatNasihat $khidmat): ?RedirectResponse
    {
        $user = request()->user();

        if ($user && $user->isStaff() && filled($user->cawangan) && ! $user->can('cawangan.view-all')) {
            $branchId = Cawangan::where('nama', $user->cawangan)->value('id');

            if ($branchId !== null
                && (int) $khidmat->cawangan_id !== $branchId
                && (int) $khidmat->cawangan_asal_id !== $branchId) {
                return redirect()->route('khidmat.index')->with('error', 'Khidmat Nasihat ini bukan di bawah cawangan anda.');
            }
        }

        return null;
    }

    /** Shared view payload for create + edit. */
    private function formData(KhidmatNasihat $khidmat, string $mode): array
    {
        $kategoriList = RefKategoriKn::where('aktif', true)
            ->with(['kategoriKes' => fn ($q) => $q->where('aktif', true)->orderBy('nama')->with([
                'subkategori' => fn ($q) => $q->where('aktif', true)->orderBy('nama'),
            ])])
            ->orderBy('jenis_kategori')
            ->get();

        // Flat tree for the client-side cascade (kategori -> kes -> subkategori),
        // so the wizard doesn't depend on the gated selenggara CRUD endpoints.
        $kategoriTree = $kategoriList->map(fn ($k) => [
            'id' => $k->id,
            'kes' => $k->kategoriKes->map(fn ($kes) => [
                'id' => $kes->id,
                'nama' => $kes->nama,
                'sub' => $kes->subkategori->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama])->values(),
            ])->values(),
        ])->keyBy('id');

        $cawanganList = Cawangan::where('status_aktif', true)
            ->orderBy('nama')->get(['id', 'nama', 'kod', 'jenis', 'negeri_id']);

        return [
            'khidmat' => $khidmat,
            'mode' => $mode,
            // All branches keyed for the DIRI_SENDIRI step; grouped by jenis for wakil.
            'cawanganList' => $cawanganList,
            'cawanganByJenis' => $cawanganList->groupBy('jenis'),
            'kategoriList' => $kategoriList,
            'kategoriTree' => $kategoriTree,
            'negeriList' => RefNegeri::orderBy('nama')->pluck('nama', 'id')->all(),
            'mahkamahSivilList' => MahkamahSivil::orderBy('nama_mahkamah')->get(['id', 'nama_mahkamah', 'negeri_mahkamah']),
            'mahkamahSyariahList' => MahkamahSyariah::orderBy('nama_mahkamah')->get(['id', 'nama_mahkamah', 'negeri_mahkamah']),
        ];
    }
}
