<?php

namespace App\Http\Controllers;

use App\Models\Bilik;
use App\Models\Cawangan;
use App\Models\RefNegeri;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cawangan master (JBG / JKM / Penjara) + rooms (bilik). Foundation for the
 * Khidmat Nasihat / Janji Temu batches. `nama` mirrors the legacy branch string.
 */
class CawanganController extends Controller
{
    private function rules(?Cawangan $cawangan = null): array
    {
        return [
            'jenis' => ['required', Rule::in(Cawangan::JENIS)],
            'kod' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:255', Rule::unique('cawangan', 'nama')->ignore($cawangan)],
            'negeri_id' => ['nullable', 'integer', 'exists:ref_negeri,id'],
            'alamat1' => ['nullable', 'string', 'max:255'],
            'alamat2' => ['nullable', 'string', 'max:255'],
            'alamat3' => ['nullable', 'string', 'max:255'],
            'poskod' => ['nullable', 'string', 'max:10'],
            'no_tel' => ['nullable', 'string', 'max:30'],
            'status_aktif' => ['nullable', 'boolean'],
        ];
    }

    private function negeriList(): array
    {
        return RefNegeri::orderBy('nama')->pluck('nama', 'id')->all();
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'jenis']);

        $cawangan = Cawangan::query()
            ->with('negeri')
            ->withCount('bilik')
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where('nama', 'like', "%{$v}%"))
            ->when($filters['jenis'] ?? null, fn ($w, $v) => $w->where('jenis', $v))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('cawangan.index', ['cawangan' => $cawangan, 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('cawangan.form', [
            'cawangan' => new Cawangan(['jenis' => 'JBG', 'status_aktif' => true]),
            'mode' => 'create',
            'negeriList' => $this->negeriList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['status_aktif'] = $request->boolean('status_aktif');

        $cawangan = Cawangan::create($data);

        Audit::log('cawangan', $cawangan->id, Audit::INSERT, "Cawangan ditambah: {$cawangan->nama}");

        return redirect()->route('cawangan.edit', $cawangan)->with('status', 'Cawangan ditambah.');
    }

    public function edit(Cawangan $cawangan): View
    {
        return view('cawangan.form', [
            'cawangan' => $cawangan->load('bilik'),
            'mode' => 'edit',
            'negeriList' => $this->negeriList(),
        ]);
    }

    public function update(Request $request, Cawangan $cawangan): RedirectResponse
    {
        $data = $request->validate($this->rules($cawangan));
        $data['status_aktif'] = $request->boolean('status_aktif');

        $cawangan->update($data);

        Audit::log('cawangan', $cawangan->id, Audit::UPDATE, "Cawangan dikemaskini: {$cawangan->nama}");

        return redirect()->route('cawangan.edit', $cawangan)->with('status', 'Cawangan dikemaskini.');
    }

    public function destroy(Cawangan $cawangan): RedirectResponse
    {
        $nama = $cawangan->nama;
        $id = $cawangan->id;
        $cawangan->delete();

        Audit::log('cawangan', $id, Audit::DELETE, "Cawangan dipadam: {$nama}");

        return redirect()->route('cawangan.index')->with('status', 'Cawangan dipadam.');
    }

    // ---- Bilik (rooms) - managed inline on the cawangan edit page ----

    public function storeBilik(Request $request, Cawangan $cawangan): RedirectResponse
    {
        $data = $request->validate(['nama_bilik' => ['required', 'string', 'max:255']]);

        $bilik = $cawangan->bilik()->create(['nama_bilik' => $data['nama_bilik'], 'status_aktif' => true]);

        Audit::log('bilik', $bilik->id, Audit::INSERT, "Bilik ditambah: {$bilik->nama_bilik} ({$cawangan->nama})");

        return redirect()->route('cawangan.edit', $cawangan)->with('status', 'Bilik ditambah.');
    }

    public function destroyBilik(Cawangan $cawangan, Bilik $bilik): RedirectResponse
    {
        abort_unless($bilik->cawangan_id === $cawangan->id, 404);

        $nama = $bilik->nama_bilik;
        $id = $bilik->id;
        $bilik->delete();

        Audit::log('bilik', $id, Audit::DELETE, "Bilik dipadam: {$nama} ({$cawangan->nama})");

        return redirect()->route('cawangan.edit', $cawangan)->with('status', 'Bilik dipadam.');
    }
}
