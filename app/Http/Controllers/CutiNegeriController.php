<?php

namespace App\Http\Controllers;

use App\Models\RefCuti;
use App\Models\RefNegeri;
use App\Support\Audit;
use App\Support\CutiNegeri;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cuti Negeri - state-specific public-holiday master (Batch 10 slice 3, FE
 * kalendar/kalendar-cuti-negeri).
 *
 * Shares the legacy ref_cuti table + the 16-slot CutiNegeri bitmask with Cuti
 * Umum; the only difference is scope. A "negeri" holiday applies to a SUBSET of
 * the 16 states (multi-state selector), so this surface lists/edits the
 * non-nationwide rows - rows whose idnegeri is not the all-16 string. Nationwide
 * holidays stay on the Cuti Umum surface. Reuses permission:selenggara.cuti.
 */
class CutiNegeriController extends Controller
{
    private function rules(): array
    {
        return [
            'nama_cuti' => ['required', 'string', 'max:255'],
            'tarikh_mula' => ['required', 'date'],
            'tarikh_tamat' => ['required', 'date', 'after_or_equal:tarikh_mula'],
            'negeri' => ['required', 'array', 'min:1'],
            'negeri.*' => ['integer', 'between:1,'.CutiNegeri::SLOTS],
        ];
    }

    /** id => nama map for the 16 states (RefNegeri, falling back to the legacy order). */
    private function negeriList(): array
    {
        $rows = RefNegeri::orderBy('id')->pluck('nama', 'id')->all();

        return $rows ?: CutiNegeri::STATES;
    }

    /** The idnegeri string for "all 16 states" (= nationwide = Cuti Umum). */
    private function allStatesString(): string
    {
        return CutiNegeri::encode(range(1, CutiNegeri::SLOTS));
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'akan']);

        $cuti = RefCuti::query()
            ->where('idnegeri', '!=', $this->allStatesString())
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where('nama_cuti', 'like', "%{$v}%"))
            ->when(($filters['akan'] ?? '') === '1', fn ($w) => $w->whereDate('tarikh_tamat', '>=', now()->toDateString()))
            ->orderByDesc('tarikh_mula')
            ->paginate(25)
            ->withQueryString();

        return view('cuti-negeri.index', [
            'cuti' => $cuti,
            'filters' => $filters,
            'negeriList' => $this->negeriList(),
        ]);
    }

    public function create(): View
    {
        return view('cuti-negeri.form', [
            'cuti' => new RefCuti,
            'mode' => 'create',
            'negeriList' => $this->negeriList(),
            'selected' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $cuti = RefCuti::create([
            'nama_cuti' => $data['nama_cuti'],
            'tarikh_mula' => $data['tarikh_mula'],
            'tarikh_tamat' => $data['tarikh_tamat'],
            'idnegeri' => CutiNegeri::encode($data['negeri']),
            'created' => now()->toDateString(),
        ]);

        Audit::log('ref_cuti', $cuti->id_cuti, Audit::INSERT, "Cuti negeri ditambah: {$cuti->nama_cuti}");

        return redirect()->route('cuti-negeri.index')->with('status', 'Cuti negeri ditambah.');
    }

    public function edit(RefCuti $cuti): View
    {
        return view('cuti-negeri.form', [
            'cuti' => $cuti,
            'mode' => 'edit',
            'negeriList' => $this->negeriList(),
            'selected' => CutiNegeri::decode($cuti->idnegeri),
        ]);
    }

    public function update(Request $request, RefCuti $cuti): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $cuti->update([
            'nama_cuti' => $data['nama_cuti'],
            'tarikh_mula' => $data['tarikh_mula'],
            'tarikh_tamat' => $data['tarikh_tamat'],
            'idnegeri' => CutiNegeri::encode($data['negeri']),
        ]);

        Audit::log('ref_cuti', $cuti->id_cuti, Audit::UPDATE, "Cuti negeri dikemaskini: {$cuti->nama_cuti}");

        return redirect()->route('cuti-negeri.index')->with('status', 'Cuti negeri dikemaskini.');
    }

    public function destroy(RefCuti $cuti): RedirectResponse
    {
        $nama = $cuti->nama_cuti;
        $id = $cuti->id_cuti;
        $cuti->delete();

        Audit::log('ref_cuti', $id, Audit::DELETE, "Cuti negeri dipadam: {$nama}");

        return redirect()->route('cuti-negeri.index')->with('status', 'Cuti negeri dipadam.');
    }
}
