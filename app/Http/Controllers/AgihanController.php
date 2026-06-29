<?php

namespace App\Http\Controllers;

use App\Mail\KesDitawarkanMail;
use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

// Agihan (case assignment) — staff assigns/reassigns a panel lawyer to a case.
// Reassignment logs the previous lawyer to sejarah_peguam_panel. Plus workload (beban tugas).
class AgihanController extends Controller
{
    public function form(Form $kes): View
    {
        return view('kes.agihan', [
            'kes' => $kes,
            'peguamList' => PeguamPanel::orderBy('nama_peguam')->get(),
            'sejarah' => SejarahPeguamPanel::where('id_kes', $kes->id)->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'peguam_id' => ['required', 'integer', 'exists:peguam_panel,id'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        $peguam = PeguamPanel::findOrFail($data['peguam_id']);
        $isReassign = filled($kes->nama_pegawai_yang_dapat_kes);

        // Log the outgoing lawyer when reassigning (agihan semula).
        if ($isReassign) {
            SejarahPeguamPanel::create([
                'id_kes' => $kes->id,
                'nama_pp_lama' => $kes->nama_pegawai_yang_dapat_kes,
                'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
                'status' => 'S',
                'alasan' => $data['alasan'] ?? null,
                'kp_pp_lama' => null,
                'modifiedBy' => $request->user()->name,
                'modifiedDate' => now(),
                'status_agihan' => 'S', // semula — column is varchar(2)
            ]);
        }

        $kes->update([
            'nama_pegawai_yang_dapat_kes' => $peguam->nama_peguam,
            'agih_kepada' => $peguam->nama_peguam,
            'tarikh_penugasan_peguam_panel' => now()->toDateString(),
            'status_agihan' => 'Ditawarkan', // offered — lawyer accepts/rejects in their area
        ]);

        $verb = $isReassign ? 'ditawarkan semula' : 'ditawarkan';

        Audit::log('forms', $kes->id, Audit::UPDATE, "Kes {$verb} kepada {$peguam->nama_peguam}.");

        // Notify the lawyer of the offer (mail driver is 'log' in dev). Never block assignment on mail failure.
        if (filled($peguam->emel_peguam) && str_contains($peguam->emel_peguam, '@')) {
            try {
                Mail::to($peguam->emel_peguam)->send(new KesDitawarkanMail($kes, $peguam));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('kes.show', $kes)->with('status', "Kes {$verb} kepada {$peguam->nama_peguam}. Menunggu peguam menerima.");
    }

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
