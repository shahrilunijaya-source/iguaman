<?php

namespace App\Http\Controllers;

use App\Models\RefJawatan;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** ref_jawatan — staff job-title reference. Single-field CRUD, inline on the index page. */
class JawatanController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q']);

        $jawatan = RefJawatan::query()
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where('nama', 'like', "%{$v}%"))
            ->orderBy('nama')
            ->paginate(50)
            ->withQueryString();

        return view('jawatan.index', ['jawatan' => $jawatan, 'filters' => $filters]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('ref_jawatan', 'nama')],
        ]);

        $jawatan = RefJawatan::create(['nama' => $data['nama'], 'aktif' => true]);

        Audit::log('ref_jawatan', $jawatan->id, Audit::INSERT, "Jawatan ditambah: {$jawatan->nama}");

        return redirect()->route('jawatan.index')->with('status', 'Jawatan ditambah.');
    }

    public function update(Request $request, RefJawatan $jawatan): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('ref_jawatan', 'nama')->ignore($jawatan)],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $jawatan->update(['nama' => $data['nama'], 'aktif' => $request->boolean('aktif', true)]);

        Audit::log('ref_jawatan', $jawatan->id, Audit::UPDATE, "Jawatan dikemaskini: {$jawatan->nama}");

        return redirect()->route('jawatan.index')->with('status', 'Jawatan dikemaskini.');
    }

    public function destroy(RefJawatan $jawatan): RedirectResponse
    {
        $nama = $jawatan->nama;
        $id = $jawatan->id;
        $jawatan->delete();

        Audit::log('ref_jawatan', $id, Audit::DELETE, "Jawatan dipadam: {$nama}");

        return redirect()->route('jawatan.index')->with('status', 'Jawatan dipadam.');
    }
}
