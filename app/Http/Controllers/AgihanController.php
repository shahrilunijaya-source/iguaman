<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Support\PeguamShortlistService;
use Illuminate\View\View;

// Agihan (case assignment) - workload (beban tugas) view. Case assignment itself now goes
// through the 3-tier spine (AgihanSpineController); the legacy single-step path was retired.
class AgihanController extends Controller
{
    public function __construct(private readonly PeguamShortlistService $shortlist) {}

    public function beban(): View
    {
        // W11: open-caseload counts come from the shared shortlist service (single source
        // of truth shared with the PPUU pick + external-lawyer assignment).
        $counts = $this->shortlist->bebanByNama();

        $lawyers = PeguamPanel::orderBy('nama_peguam')->get()->map(fn ($p) => [
            'id' => $p->id,
            'nama' => $p->nama_peguam,
            'firma' => $p->nama_firma,
            'kes' => (int) ($counts[$p->nama_peguam] ?? 0),
        ]);

        return view('agihan.beban', ['lawyers' => $lawyers, 'unassigned' => Form::whereNull('nama_pegawai_yang_dapat_kes')->orWhere('nama_pegawai_yang_dapat_kes', '')->count()]);
    }
}
