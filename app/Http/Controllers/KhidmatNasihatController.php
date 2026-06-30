<?php

namespace App\Http\Controllers;

use App\Http\Requests\KhidmatNasihatRequest;
use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\RefNegeri;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Support\Audit;
use App\Support\KhidmatBayaran;
use App\Support\SlotAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Khidmat Nasihat (legal-advisory applications) — batch 9.
 *
 *  - index/show (slice 1): read-only list + detail, gated permission:khidmat.view.
 *  - create/store/edit/update (slice 2): the staff-driven "Permohonan Baharu"
 *    wizard (jenis_permohonan = DIRI_SENDIRI), gated permission:khidmat.manage.
 *    A final submit computes the fee (KhidmatBayaran), books an appointment slot
 *    (SlotAvailabilityService → temu_janji, both-way link), and sets status BAHARU.
 */
class KhidmatNasihatController extends Controller
{
    public function __construct(private readonly SlotAvailabilityService $slots) {}

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

    public function show(KhidmatNasihat $khidmat): View
    {
        $khidmat->load(['pengguna', 'cawangan', 'kategori', 'subkategori', 'temuJanji']);

        return view('khidmat-nasihat.show', ['khidmat' => $khidmat]);
    }

    public function create(): View
    {
        return view('khidmat-nasihat.form', $this->formData(new KhidmatNasihat([
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'is_percuma' => false,
        ]), 'create'));
    }

    public function store(KhidmatNasihatRequest $request): RedirectResponse
    {
        $khidmat = DB::transaction(function () use ($request) {
            $data = $this->mapInput($request);
            $khidmat = KhidmatNasihat::create($data);
            $khidmat->update(['no_permohonan' => $this->nextNoPermohonan($khidmat)]);

            if ($request->isHantar()) {
                $this->bookSlot($khidmat, $request);
            }

            return $khidmat;
        });

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::INSERT,
            "Permohonan Khidmat Nasihat baharu: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        return redirect()->route('khidmat.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf disimpan.');
    }

    public function edit(KhidmatNasihat $khidmat): View
    {
        abort_unless($khidmat->status_kn === KhidmatNasihat::STATUS_DRAF, 403, 'Hanya draf boleh dikemaskini.');

        return view('khidmat-nasihat.form', $this->formData($khidmat, 'edit'));
    }

    public function update(KhidmatNasihatRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        abort_unless($khidmat->status_kn === KhidmatNasihat::STATUS_DRAF, 403, 'Hanya draf boleh dikemaskini.');

        DB::transaction(function () use ($request, $khidmat) {
            $khidmat->update($this->mapInput($request));

            if ($request->isHantar() && $khidmat->id_temu_janji === null) {
                $this->bookSlot($khidmat, $request);
            }
        });

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE,
            "Kemaskini Khidmat Nasihat: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        return redirect()->route('khidmat.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf dikemaskini.');
    }

    // ---- Internals ----

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

        return [
            'khidmat' => $khidmat,
            'mode' => $mode,
            'cawanganList' => Cawangan::where('status_aktif', true)->orderBy('nama')->get(['id', 'nama', 'kod', 'negeri_id']),
            'kategoriList' => $kategoriList,
            'kategoriTree' => $kategoriTree,
            'negeriList' => RefNegeri::orderBy('nama')->pluck('nama', 'id')->all(),
        ];
    }

    /** Validated input → khidmat_nasihat columns (incl. computed fee + status). */
    private function mapInput(KhidmatNasihatRequest $request): array
    {
        $v = $request->validated();
        $isPercuma = $request->boolean('is_percuma');
        $kategori = RefKategoriKn::find($v['id_kategori'] ?? null);

        $fee = KhidmatBayaran::kira($kategori?->jenis_kategori, $v['jumlah_pendapatan'] ?? null, $isPercuma);

        return [
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => $v['nama_mangsa'],
            'id_pengenalan_mangsa' => $v['id_pengenalan_mangsa'] ?? null,
            'jenis_pengenalan_mangsa' => $v['jenis_pengenalan_mangsa'] ?? null,
            'jantina_mangsa' => $v['jantina_mangsa'] ?? null,
            'umur_mangsa' => $v['umur_mangsa'] ?? null,
            'bangsa' => $v['bangsa'] ?? null,
            'agama' => $v['agama'] ?? null,
            'tarikh_lahir_mangsa' => $v['tarikh_lahir_mangsa'] ?? null,
            'nama_wakil' => $v['nama_wakil'] ?? null,
            'alamat_surat1' => $v['alamat_surat1'] ?? null,
            'alamat_surat2' => $v['alamat_surat2'] ?? null,
            'alamat_surat3' => $v['alamat_surat3'] ?? null,
            'poskod' => $v['poskod'] ?? null,
            'cawangan_id' => $v['cawangan_id'],
            'id_kategori' => $v['id_kategori'] ?? null,
            'id_subkategori' => $v['id_subkategori'] ?? null,
            'id_negeri' => $v['id_negeri'] ?? null,
            'jenis_kes' => $v['jenis_kes'] ?? null,
            'jumlah_pendapatan' => $v['jumlah_pendapatan'] ?? null,
            'ulasan_permohonan' => $v['ulasan_permohonan'] ?? null,
            'jumlah_bayaran' => $fee,
            'is_percuma' => $isPercuma,
            'perakuan' => $request->isHantar() ? $request->boolean('perakuan') : false,
            'status_kn' => $request->isHantar() ? KhidmatNasihat::STATUS_BAHARU : KhidmatNasihat::STATUS_DRAF,
            'cipta_oleh' => $request->user()->name,
            'kemaskini_oleh' => $request->user()->name,
        ];
    }

    /**
     * Book the chosen slot: create a temu_janji (MENUNGGU), link it both ways,
     * and flip the slot's is_temujanji flag. Validates the slot is still open.
     */
    private function bookSlot(KhidmatNasihat $khidmat, KhidmatNasihatRequest $request): void
    {
        $tarikh = $request->validated()['tarikh_temu_janji'];
        $masa = $request->validated()['masa_temu_janji'];

        $slot = SlotTemuJanji::query()
            ->where('cawangan_id', $khidmat->cawangan_id)
            ->whereDate('tarikh_slot', $tarikh)
            ->whereRaw("DATE_FORMAT(masa_mula, '%H:%i') = ?", [$masa])
            ->where('is_temujanji', false)
            ->where('status_aktif', true)
            ->lockForUpdate()
            ->first();

        abort_if($slot === null, 422, 'Slot temu janji tidak lagi tersedia. Sila pilih masa lain.');

        $temu = TemuJanji::create([
            'id_khidmat_nasihat' => $khidmat->id,
            'slot_temu_janji_id' => $slot->id,
            'cawangan_id' => $khidmat->cawangan_id,
            'tarikh_temu_janji' => $slot->tarikh_slot,
            'masa_mula' => $slot->masa_mula,
            'masa_akhir' => $slot->masa_akhir,
            'status' => 'MENUNGGU',
            'cipta_oleh' => $request->user()->name,
        ]);

        $slot->update(['is_temujanji' => true]);
        $khidmat->update(['id_temu_janji' => $temu->id]);
    }

    /** KN/{cawangan-kod}/{year}/{seq} — seq running per (cawangan, year). */
    private function nextNoPermohonan(KhidmatNasihat $khidmat): string
    {
        $cawangan = $khidmat->cawangan_id ? Cawangan::find($khidmat->cawangan_id) : null;
        $kod = $cawangan?->kod ?: 'JBG';
        $year = now()->year;

        $seq = KhidmatNasihat::where('cawangan_id', $khidmat->cawangan_id)
            ->whereYear('created_at', $year)
            ->where('id', '<=', $khidmat->id)
            ->count();

        return sprintf('KN/%s/%d/%04d', $kod, $year, max(1, $seq));
    }
}
