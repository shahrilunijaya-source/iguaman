<?php

namespace App\Http\Controllers;

use App\Models\MahkamahSivil;
use App\Models\MahkamahSyariah;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Mahkamah reference maintenance — civil (mahkamah_sivil) + syariah (mahkamah_syariah) court registry.
// Single controller serves both via the {jenis} segment ('sivil' | 'syariah').
class MahkamahRefController extends Controller
{
    /** Resolve the Eloquent model class for the given jenis segment. */
    private function modelClass(string $jenis): string
    {
        abort_unless(in_array($jenis, ['sivil', 'syariah'], true), 404);

        return $jenis === 'syariah' ? MahkamahSyariah::class : MahkamahSivil::class;
    }

    /** Audit table name matches the resolved table. */
    private function tableName(string $jenis): string
    {
        return $jenis === 'syariah' ? 'mahkamah_syariah' : 'mahkamah_sivil';
    }

    private function rules(): array
    {
        return [
            'nama_mahkamah' => ['required', 'string', 'max:70'],
            'negeri_mahkamah' => ['required', 'string', 'max:70'],
            'lokaliti_mahkamah' => ['required', 'string', 'max:50'],
            'jenis_mahkamah' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function index(string $jenis, Request $request): View
    {
        $model = $this->modelClass($jenis);
        $filters = $request->only(['negeri', 'q']);

        $mahkamah = $model::query()
            ->when($filters['negeri'] ?? null, fn ($w, $v) => $w->where('negeri_mahkamah', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($s) => $s
                ->where('nama_mahkamah', 'like', "%{$v}%")->orWhere('negeri_mahkamah', 'like', "%{$v}%")))
            ->orderBy('nama_mahkamah')
            ->paginate(25)
            ->withQueryString();

        return view('mahkamah-ref.index', [
            'jenis' => $jenis,
            'mahkamah' => $mahkamah,
            'filters' => $filters,
            'negeriList' => $model::query()->whereNotNull('negeri_mahkamah')->where('negeri_mahkamah', '!=', '')->distinct()->orderBy('negeri_mahkamah')->pluck('negeri_mahkamah'),
        ]);
    }

    public function create(string $jenis): View
    {
        $model = $this->modelClass($jenis);

        return view('mahkamah-ref.form', ['jenis' => $jenis, 'mahkamah' => new $model, 'mode' => 'create']);
    }

    public function store(string $jenis, Request $request): RedirectResponse
    {
        $model = $this->modelClass($jenis);
        $mahkamah = $model::create($request->validate($this->rules()));
        Audit::log($this->tableName($jenis), $mahkamah->id, Audit::INSERT, "Mahkamah ditambah: {$mahkamah->nama_mahkamah}");

        return redirect()->route('mahkamah-ref.index', ['jenis' => $jenis])->with('status', 'Mahkamah ditambah.');
    }

    public function edit(string $jenis, int $id): View
    {
        $model = $this->modelClass($jenis);
        $mahkamah = $model::findOrFail($id);

        return view('mahkamah-ref.form', ['jenis' => $jenis, 'mahkamah' => $mahkamah, 'mode' => 'edit']);
    }

    public function update(string $jenis, int $id, Request $request): RedirectResponse
    {
        $model = $this->modelClass($jenis);
        $mahkamah = $model::findOrFail($id);
        $mahkamah->update($request->validate($this->rules()));
        Audit::log($this->tableName($jenis), $mahkamah->id, Audit::UPDATE, "Mahkamah dikemaskini: {$mahkamah->nama_mahkamah}");

        return redirect()->route('mahkamah-ref.index', ['jenis' => $jenis])->with('status', 'Mahkamah dikemaskini.');
    }

    public function destroy(string $jenis, int $id): RedirectResponse
    {
        $model = $this->modelClass($jenis);
        $mahkamah = $model::findOrFail($id);
        $nama = $mahkamah->nama_mahkamah;
        $mahkamah->delete();
        Audit::log($this->tableName($jenis), $id, Audit::DELETE, "Mahkamah dipadam: {$nama}");

        return redirect()->route('mahkamah-ref.index', ['jenis' => $jenis])->with('status', 'Mahkamah dipadam.');
    }
}
