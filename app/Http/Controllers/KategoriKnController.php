<?php

namespace App\Http\Controllers;

use App\Models\RefKategoriKesKn;
use App\Models\RefKategoriKn;
use App\Models\RefSubkategoriKn;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Khidmat Nasihat 3-level category tree:
 *   kategori (L1) -> kategori_kes (L2) -> subkategori (L3).
 * Drill-down index pages, inline add. Deleting a parent cascades its children.
 */
class KategoriKnController extends Controller
{
    // ---- L1: kategori ----

    public function index(): View
    {
        return view('kategori-kn.index', [
            'kategori' => RefKategoriKn::withCount('kategoriKes')->orderBy('jenis_kategori')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['jenis_kategori' => ['required', 'string', 'max:255']]);
        $row = RefKategoriKn::create(['jenis_kategori' => $data['jenis_kategori'], 'aktif' => true]);
        Audit::log('ref_kategori_kn', $row->id, Audit::INSERT, "Kategori KN ditambah: {$row->jenis_kategori}");

        return back()->with('status', 'Kategori ditambah.');
    }

    public function update(Request $request, RefKategoriKn $kategori): RedirectResponse
    {
        $data = $request->validate(['jenis_kategori' => ['required', 'string', 'max:255']]);
        $kategori->update(['jenis_kategori' => $data['jenis_kategori'], 'aktif' => $request->boolean('aktif', true)]);
        Audit::log('ref_kategori_kn', $kategori->id, Audit::UPDATE, "Kategori KN dikemaskini: {$kategori->jenis_kategori}");

        return back()->with('status', 'Kategori dikemaskini.');
    }

    public function destroy(RefKategoriKn $kategori): RedirectResponse
    {
        $id = $kategori->id;
        $nama = $kategori->jenis_kategori;
        $kategori->delete();
        Audit::log('ref_kategori_kn', $id, Audit::DELETE, "Kategori KN dipadam: {$nama}");

        return redirect()->route('kategori-kn.index')->with('status', 'Kategori dipadam.');
    }

    // ---- L2: kategori kes ----

    public function kes(RefKategoriKn $kategori): View
    {
        return view('kategori-kn.kes', [
            'kategori' => $kategori,
            'kesList' => $kategori->kategoriKes()->withCount('subkategori')->orderBy('nama')->get(),
        ]);
    }

    public function storeKes(Request $request, RefKategoriKn $kategori): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $row = $kategori->kategoriKes()->create(['nama' => $data['nama'], 'aktif' => true]);
        Audit::log('ref_kategori_kes_kn', $row->id, Audit::INSERT, "Kategori kes ditambah: {$row->nama}");

        return back()->with('status', 'Kategori kes ditambah.');
    }

    public function updateKes(Request $request, RefKategoriKesKn $kes): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $kes->update(['nama' => $data['nama'], 'aktif' => $request->boolean('aktif', true)]);
        Audit::log('ref_kategori_kes_kn', $kes->id, Audit::UPDATE, "Kategori kes dikemaskini: {$kes->nama}");

        return back()->with('status', 'Kategori kes dikemaskini.');
    }

    public function destroyKes(RefKategoriKesKn $kes): RedirectResponse
    {
        $id = $kes->id;
        $nama = $kes->nama;
        $kategoriId = $kes->kategori_id;
        $kes->delete();
        Audit::log('ref_kategori_kes_kn', $id, Audit::DELETE, "Kategori kes dipadam: {$nama}");

        return redirect()->route('kategori-kn.kes', $kategoriId)->with('status', 'Kategori kes dipadam.');
    }

    // ---- L3: subkategori ----

    public function sub(RefKategoriKesKn $kes): View
    {
        return view('kategori-kn.sub', [
            'kes' => $kes->load('kategori'),
            'subList' => $kes->subkategori()->orderBy('nama')->get(),
        ]);
    }

    public function storeSub(Request $request, RefKategoriKesKn $kes): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $row = $kes->subkategori()->create(['nama' => $data['nama'], 'aktif' => true]);
        Audit::log('ref_subkategori_kn', $row->id, Audit::INSERT, "Subkategori ditambah: {$row->nama}");

        return back()->with('status', 'Subkategori ditambah.');
    }

    public function updateSub(Request $request, RefSubkategoriKn $sub): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $sub->update(['nama' => $data['nama'], 'aktif' => $request->boolean('aktif', true)]);
        Audit::log('ref_subkategori_kn', $sub->id, Audit::UPDATE, "Subkategori dikemaskini: {$sub->nama}");

        return back()->with('status', 'Subkategori dikemaskini.');
    }

    public function destroySub(RefSubkategoriKn $sub): RedirectResponse
    {
        $id = $sub->id;
        $nama = $sub->nama;
        $kesId = $sub->kategori_kes_id;
        $sub->delete();
        Audit::log('ref_subkategori_kn', $id, Audit::DELETE, "Subkategori dipadam: {$nama}");

        return redirect()->route('kategori-kn.sub', $kesId)->with('status', 'Subkategori dipadam.');
    }
}
