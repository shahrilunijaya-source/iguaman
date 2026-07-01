<?php

namespace App\Http\Controllers;

use App\Models\RefKes;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Jenis Kes maintenance - case-type reference master (ref_kes).
class RefKesController extends Controller
{
    private function rules(): array
    {
        return [
            'id_kes' => ['required', 'string', 'max:20'],
            'jenis_kes' => ['required', 'string', 'max:5'],
            'kategori_kes' => ['nullable', 'string', 'max:100'],
            'deskripsi' => ['required', 'string', 'max:500'],
            'aktif_kes' => ['nullable', 'in:1,0'],
            'tarikh_kuatkuasa' => ['nullable', 'date'],
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['aktif', 'q']);

        $refKes = RefKes::query()
            ->when(($filters['aktif'] ?? null) !== null && ($filters['aktif'] ?? '') !== '', fn ($w) => $w->where('aktif_kes', $filters['aktif']))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($s) => $s
                ->where('jenis_kes', 'like', "%{$v}%")
                ->orWhere('kategori_kes', 'like', "%{$v}%")
                ->orWhere('deskripsi', 'like', "%{$v}%")))
            ->orderBy('jenis_kes')
            ->paginate(25)
            ->withQueryString();

        return view('ref-kes.index', [
            'refKes' => $refKes,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('ref-kes.form', ['refKes' => new RefKes, 'mode' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $refKes = RefKes::create($request->validate($this->rules()));
        Audit::log('ref_kes', $refKes->id, Audit::INSERT, "Jenis kes ditambah: {$refKes->jenis_kes}");

        return redirect()->route('ref-kes.index')->with('status', 'Jenis kes ditambah.');
    }

    public function edit(RefKes $ref_kes): View
    {
        return view('ref-kes.form', ['refKes' => $ref_kes, 'mode' => 'edit']);
    }

    public function update(Request $request, RefKes $ref_kes): RedirectResponse
    {
        $ref_kes->update($request->validate($this->rules()));
        Audit::log('ref_kes', $ref_kes->id, Audit::UPDATE, "Jenis kes dikemaskini: {$ref_kes->jenis_kes}");

        return redirect()->route('ref-kes.index')->with('status', 'Jenis kes dikemaskini.');
    }

    public function destroy(RefKes $ref_kes): RedirectResponse
    {
        $jenis = $ref_kes->jenis_kes;
        $id = $ref_kes->id;
        $ref_kes->delete();
        Audit::log('ref_kes', $id, Audit::DELETE, "Jenis kes dipadam: {$jenis}");

        return redirect()->route('ref-kes.index')->with('status', 'Jenis kes dipadam.');
    }
}
