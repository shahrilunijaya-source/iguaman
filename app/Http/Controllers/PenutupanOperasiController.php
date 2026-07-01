<?php

namespace App\Http\Controllers;

use App\Models\Bilik;
use App\Models\Cawangan;
use App\Models\PenutupanOperasi;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penutupan Operasi - per-branch (+optional room) operational closures (date ranges).
 * Dates inside an active range are excluded by SlotAvailabilityService + SlotGenerator.
 * Gated permission:slot.manage. CRUD shape mirrors CutiController.
 */
class PenutupanOperasiController extends Controller
{
    private function rules(): array
    {
        return [
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'bilik_id' => ['nullable', 'integer', 'exists:bilik,id'],
            'tarikh_mula' => ['required', 'date'],
            'tarikh_tamat' => ['required', 'date', 'after_or_equal:tarikh_mula'],
            'sebab' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['cawangan_id', 'akan']);

        $penutupan = PenutupanOperasi::query()
            ->with(['cawangan:id,nama', 'bilik:id,nama_bilik'])
            ->when($filters['cawangan_id'] ?? null, fn ($w, $v) => $w->where('cawangan_id', $v))
            ->when(($filters['akan'] ?? '') === '1', fn ($w) => $w->whereDate('tarikh_tamat', '>=', now()->toDateString()))
            ->orderByDesc('tarikh_mula')
            ->paginate(25)
            ->withQueryString();

        return view('penutupan.index', [
            'penutupan' => $penutupan,
            'filters' => $filters,
            'cawanganList' => Cawangan::orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        return view('penutupan.form', [
            'penutupan' => new PenutupanOperasi,
            'cawanganList' => Cawangan::with('bilik:id,cawangan_id,nama_bilik')->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $this->assertBilikBelongsToCawangan($data);

        $penutupan = PenutupanOperasi::create($data);

        Audit::log('penutupan_operasi', $penutupan->id, Audit::INSERT,
            "Penutupan operasi ditambah ({$penutupan->tarikh_mula->format('d/m/Y')} – {$penutupan->tarikh_tamat->format('d/m/Y')})");

        return redirect()->route('penutupan.index')->with('status', 'Penutupan operasi ditambah.');
    }

    public function destroy(PenutupanOperasi $penutupan): RedirectResponse
    {
        $id = $penutupan->id;
        $penutupan->delete();

        Audit::log('penutupan_operasi', $id, Audit::DELETE, 'Penutupan operasi dipadam.');

        return redirect()->route('penutupan.index')->with('status', 'Penutupan operasi dipadam.');
    }

    /** A room-scoped closure must reference a room of the same branch. */
    private function assertBilikBelongsToCawangan(array $data): void
    {
        if (empty($data['bilik_id'])) {
            return;
        }

        $bilik = Bilik::find($data['bilik_id']);
        abort_unless($bilik && $bilik->cawangan_id === (int) $data['cawangan_id'], 422, 'Bilik bukan milik cawangan ini.');
    }
}
