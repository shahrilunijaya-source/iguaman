<?php

namespace App\Http\Controllers;

use App\Models\PegawaiJbg;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Pegawai JBG officer registry — managed list (pegawai_jbg). Supervisory roles only (routes).
class PegawaiController extends Controller
{
    private function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:50'],
            'cawangan' => ['nullable', 'string', 'max:50'],
            'jawatan' => ['nullable', 'string', 'max:50'],
            'bahagian' => ['nullable', 'string', 'max:50'],
            'jenis_pegawai' => ['nullable', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'in:0,1'],
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['cawangan', 'status', 'q']);

        $pegawai = PegawaiJbg::query()
            ->when($filters['cawangan'] ?? null, fn ($w, $v) => $w->where('cawangan', $v))
            ->when(($filters['status'] ?? null) !== null && ($filters['status'] ?? '') !== '', fn ($w) => $w->where('status_aktif', $filters['status']))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($s) => $s
                ->where('nama', 'like', "%{$v}%")->orWhere('jawatan', 'like', "%{$v}%")))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('pegawai.index', [
            'pegawai' => $pegawai,
            'filters' => $filters,
            'cawanganList' => PegawaiJbg::query()->whereNotNull('cawangan')->where('cawangan', '!=', '')->distinct()->orderBy('cawangan')->pluck('cawangan'),
        ]);
    }

    public function create(): View
    {
        return view('pegawai.form', ['pegawai' => new PegawaiJbg, 'mode' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $pegawai = PegawaiJbg::create($request->validate($this->rules()));
        Audit::log('pegawai_jbg', $pegawai->id, Audit::INSERT, "Pegawai ditambah: {$pegawai->nama}");

        return redirect()->route('pegawai.index')->with('status', 'Pegawai ditambah.');
    }

    public function edit(PegawaiJbg $pegawai): View
    {
        return view('pegawai.form', ['pegawai' => $pegawai, 'mode' => 'edit']);
    }

    public function update(Request $request, PegawaiJbg $pegawai): RedirectResponse
    {
        $pegawai->update($request->validate($this->rules()));
        Audit::log('pegawai_jbg', $pegawai->id, Audit::UPDATE, "Pegawai dikemaskini: {$pegawai->nama}");

        return redirect()->route('pegawai.index')->with('status', 'Pegawai dikemaskini.');
    }

    public function destroy(PegawaiJbg $pegawai): RedirectResponse
    {
        $nama = $pegawai->nama;
        $id = $pegawai->id;
        $pegawai->delete();
        Audit::log('pegawai_jbg', $id, Audit::DELETE, "Pegawai dipadam: {$nama}");

        return redirect()->route('pegawai.index')->with('status', 'Pegawai dipadam.');
    }
}
