<?php

namespace App\Http\Controllers;

use App\Http\Requests\PengantaraanRequest;
use App\Models\Cawangan;
use App\Models\Form;
use App\Models\RefKes;
use App\Models\SejarahSidang;
use App\Models\User;
use App\Support\PengantaraanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

// Pengantaraan (mediation) — standalone intake (W18), per-case lifecycle (assignment,
// hearing, outcome), hearing reschedule history (sejarah_sidang), and mediator
// assignment + ledger (W19). A mediation is a tagged `forms` row (D11).
class PengantaraanController extends Controller
{
    public function __construct(private readonly PengantaraanService $svc) {}

    /** W18 — mediation worklist (standalone + litigation-derived), branch-scoped. */
    public function senarai(Request $request): View
    {
        $filters = $request->only(['sumber', 'q']);

        $senarai = Form::query()
            ->where(fn ($w) => $w->whereNotNull('no_pengantaraan')->orWhereNotNull('sumber_pengantaraan'))
            ->when($filters['sumber'] ?? null, fn ($w, $v) => $w->where('sumber_pengantaraan', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($q) => $q
                ->where('nama', 'like', "%{$v}%")
                ->orWhere('nokp', 'like', "%{$v}%")
                ->orWhere('no_pengantaraan', 'like', "%{$v}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('pengantaraan.index', ['senarai' => $senarai, 'filters' => $filters]);
    }

    /** W18 — standalone (TERUS) intake form. */
    public function create(): View
    {
        return view('pengantaraan.create', [
            'cawanganList' => Cawangan::where('status_aktif', true)->orderBy('nama')->pluck('nama'),
            'jenisList' => RefKes::query()->whereNotNull('jenis_kes')->where('jenis_kes', '!=', '')->distinct()->orderBy('jenis_kes')->pluck('jenis_kes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nokp' => ['nullable', 'string', 'max:20'],
            'cawangan' => ['required', 'string', 'max:255'],
            'jenis_kes' => ['nullable', 'string', 'max:10'],
            'kategori_kes' => ['nullable', 'string', 'max:100'],
            'pengantaraan_kategori_kes' => ['nullable', 'string', 'max:255'],
            'tarikh_permohonan' => ['nullable', 'date'],
        ]);

        $form = $this->svc->daftarTerus($data, $request->user());

        return redirect()->route('pengantaraan.edit', $form)
            ->with('status', "Pengantaraan didaftarkan. No: {$form->no_pengantaraan}.");
    }

    public function edit(Form $kes): View
    {
        $kes->load('sejarahSidang');

        return view('kes.pengantaraan', [
            'kes' => $kes,
            'pegawaiList' => $this->pegawaiList(),
        ]);
    }

    public function update(PengantaraanRequest $request, Form $kes): RedirectResponse
    {
        $kes->update($request->validated() + ['tarikh_KPKemaskini' => now()]);

        // W18 litigation path: an active mediation on a litigation case gets tagged
        // LITIGASI + its own no_pengantaraan (kept distinct from no_fail).
        if (filled($kes->status_pengantaraan)) {
            $this->svc->tandakanLitigasi($kes, $request->user());
        }

        return redirect()->route('kes.show', $kes)->with('status', 'Maklumat pengantaraan dikemaskini.');
    }

    /** W19 — assign a mediator (staff officer) + open a MEDIASI claim-ledger row. */
    public function agihPengantara(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'id_pegawai_pengantara' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->svc->agihPengantara($kes, (int) $data['id_pegawai_pengantara'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pengantaraan.edit', $kes)->with('status', 'Pegawai pengantara ditetapkan.');
    }

    /** Record a hearing postponement: log to sejarah_sidang + move the case hearing date. */
    public function tangguhSidang(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'tarikh_sidang' => ['required', 'date'],
            'alasan_tangguh' => ['nullable', 'string', 'max:50'],
        ]);

        SejarahSidang::create([
            'id_kes' => $kes->id,
            'tarikh_sidang' => $data['tarikh_sidang'],
            'alasan_tangguh' => $data['alasan_tangguh'] ?? null,
            'dikemaskini_oleh' => $request->user()->name,
        ]);

        $kes->update([
            'tarikh_sidang' => $data['tarikh_sidang'],
            'status_sidang' => 'Tangguh',
        ]);

        return redirect()->route('pengantaraan.edit', $kes)->with('status', 'Sidang ditangguh dan direkodkan.');
    }

    /** Active staff users assignable as mediator (id => name). */
    private function pegawaiList()
    {
        return User::query()
            ->where('user_type', User::TYPE_STAFF)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
