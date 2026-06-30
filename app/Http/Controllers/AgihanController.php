<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Agihan (case assignment) — workload (beban tugas) view. Case assignment itself now goes
// through the 3-tier spine (AgihanSpineController); the legacy single-step path was retired.
class AgihanController extends Controller
{
    public function beban(): View
    {
        // Workload per panel lawyer (assigned-case count). Small lawyer set → simple per-row count.
        $counts = Form::query()
            ->whereNotNull('nama_pegawai_yang_dapat_kes')->where('nama_pegawai_yang_dapat_kes', '!=', '')
            ->select('nama_pegawai_yang_dapat_kes', DB::raw('COUNT(*) as n'))
            ->groupBy('nama_pegawai_yang_dapat_kes')
            ->pluck('n', 'nama_pegawai_yang_dapat_kes');

        $lawyers = PeguamPanel::orderBy('nama_peguam')->get()->map(fn ($p) => [
            'id' => $p->id,
            'nama' => $p->nama_peguam,
            'firma' => $p->nama_firma,
            'kes' => (int) ($counts[$p->nama_peguam] ?? 0),
        ]);

        return view('agihan.beban', ['lawyers' => $lawyers, 'unassigned' => Form::whereNull('nama_pegawai_yang_dapat_kes')->orWhere('nama_pegawai_yang_dapat_kes', '')->count()]);
    }
}
