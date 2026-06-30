<?php

namespace App\Http\Controllers;

use App\Models\Bilik;
use App\Models\Cawangan;
use App\Models\SlotTemuJanji;
use App\Support\Audit;
use App\Support\SlotGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Slot auto-generation + per-branch session config ("penetapan sesi janji temu").
 * Gated permission:slot.manage. Generation/teardown delegated to SlotGenerator;
 * controller stays thin (validate, dispatch, audit, redirect).
 */
class SlotGenerationController extends Controller
{
    public function __construct(private readonly SlotGenerator $generator) {}

    /** Slot admin page: branch picker + session config + generate/teardown forms. */
    public function index(Request $request): View
    {
        $cawanganList = Cawangan::orderBy('nama')->get(['id', 'nama', 'negeri_id', 'hari_minggu', 'masa_buka', 'masa_tutup', 'tempoh_slot_minit']);

        $selected = null;
        $bilikList = collect();
        $summary = [];
        if ($request->filled('cawangan_id')) {
            $selected = Cawangan::with('bilik')->find($request->integer('cawangan_id'));
            if ($selected) {
                $bilikList = $selected->bilik;
                $summary = $this->branchSummary($selected->id);
            }
        }

        return view('slot.index', [
            'cawanganList' => $cawanganList,
            'selected' => $selected,
            'bilikList' => $bilikList,
            'summary' => $summary,
        ]);
    }

    /** Save the branch session config (weekend days, hours, slot length). */
    public function updateSession(Request $request, Cawangan $cawangan): RedirectResponse
    {
        $data = $request->validate([
            'hari_minggu' => ['nullable', 'array'],
            'hari_minggu.*' => ['integer', 'between:1,7'],
            'masa_buka' => ['nullable', 'date_format:H:i'],
            'masa_tutup' => ['nullable', 'date_format:H:i', 'after:masa_buka'],
            'tempoh_slot_minit' => ['required', 'integer', 'between:5,240'],
        ]);

        $cawangan->update([
            'hari_minggu' => empty($data['hari_minggu']) ? null : implode(',', $data['hari_minggu']),
            'masa_buka' => $data['masa_buka'] ?: null,
            'masa_tutup' => $data['masa_tutup'] ?: null,
            'tempoh_slot_minit' => $data['tempoh_slot_minit'],
        ]);

        Audit::log('cawangan', $cawangan->id, Audit::UPDATE, "Penetapan sesi dikemaskini: {$cawangan->nama}");

        return redirect()->route('slot.index', ['cawangan_id' => $cawangan->id])->with('status', 'Penetapan sesi disimpan.');
    }

    /** Generate slots for a branch (+optional room) over a date range. */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'bilik_id' => ['nullable', 'integer', 'exists:bilik,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $cawangan = Cawangan::findOrFail($data['cawangan_id']);
        $bilik = null;
        if (! empty($data['bilik_id'])) {
            $bilik = Bilik::findOrFail($data['bilik_id']);
            abort_unless($bilik->cawangan_id === $cawangan->id, 404);
        }

        $result = $this->generator->generate($cawangan, $bilik, $data['from'], $data['to']);

        Audit::log('slot_temu_janji', $cawangan->id, Audit::INSERT,
            "Slot dijana: {$result['created']} baharu ({$cawangan->nama}, {$data['from']} – {$data['to']})");

        return redirect()->route('slot.index', ['cawangan_id' => $cawangan->id])
            ->with('status', "Penjanaan selesai: {$result['created']} slot baharu, {$result['existing']} sedia ada, {$result['skipped_days']} hari dilangkau.");
    }

    /** Delete ungenerated/unbooked slots for a branch over a date range (teardown). */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'bilik_id' => ['nullable', 'integer', 'exists:bilik,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $cawangan = Cawangan::findOrFail($data['cawangan_id']);

        $deleted = SlotTemuJanji::query()
            ->where('cawangan_id', $cawangan->id)
            ->when(empty($data['bilik_id']),
                fn ($q) => $q->whereNull('bilik_id'),
                fn ($q) => $q->where('bilik_id', $data['bilik_id']))
            ->whereBetween('tarikh_slot', [$data['from'], $data['to']])
            ->where('is_temujanji', false) // never delete booked slots
            ->delete();

        Audit::log('slot_temu_janji', $cawangan->id, Audit::DELETE,
            "Slot dipadam: {$deleted} ({$cawangan->nama}, {$data['from']} – {$data['to']})");

        return redirect()->route('slot.index', ['cawangan_id' => $cawangan->id])
            ->with('status', "{$deleted} slot belum ditempah dipadam.");
    }

    /** Open/booked counts for the next ~90 days, for the page summary. */
    private function branchSummary(int $cawanganId): array
    {
        $rows = SlotTemuJanji::query()
            ->where('cawangan_id', $cawanganId)
            ->selectRaw('COUNT(*) as jumlah, SUM(is_temujanji = 1) as ditempah, MIN(tarikh_slot) as mula, MAX(tarikh_slot) as tamat')
            ->first();

        return [
            'jumlah' => (int) ($rows->jumlah ?? 0),
            'ditempah' => (int) ($rows->ditempah ?? 0),
            'mula' => $rows->mula ?? null,
            'tamat' => $rows->tamat ?? null,
        ];
    }
}
