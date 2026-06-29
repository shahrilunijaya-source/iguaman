<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Support\Audit;
use App\Support\PeguamLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Staff view of a panel lawyer: master record + detailed profile (butiran) + assigned cases.
class PeguamPanelController extends Controller
{
    public function show(PeguamPanel $peguam): View
    {
        $peguam->load('butiran');

        $kes = Form::where('nama_pegawai_yang_dapat_kes', $peguam->nama_peguam)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'nama', 'no_fail', 'status', 'kategori_kes']);

        return view('peguam-panel.show', [
            'peguam' => $peguam,
            'b' => $peguam->butiran,
            'kes' => $kes,
        ]);
    }

    public function edit(PeguamPanel $peguam): View
    {
        return view('peguam-panel.form', ['peguam' => $peguam]);
    }

    public function update(Request $request, PeguamPanel $peguam): RedirectResponse
    {
        $data = $request->validate([
            'nama_peguam' => ['required', 'string', 'max:150'],
            'kp_peguam' => ['required', 'string', 'max:20'],
            'tel_peguam' => ['nullable', 'string', 'max:20'],
            'emel_peguam' => ['nullable', 'email', 'max:255'],
            'nama_firma' => ['nullable', 'string', 'max:255'],
            'alamat_firma_1' => ['nullable', 'string', 'max:255'],
            'alamat_firma_2' => ['nullable', 'string', 'max:255'],
            'alamat_firma_3' => ['nullable', 'string', 'max:255'],
            'poskod_firma' => ['nullable', 'string', 'max:10'],
            'negeri_firma' => ['nullable', 'string', 'max:100'],
            'tel_firma' => ['nullable', 'string', 'max:20'],
        ]);

        $peguam->update($data);
        Audit::log('peguam_panel', $peguam->id, Audit::UPDATE, "Kemaskini peguam panel: {$peguam->nama_peguam}");

        return redirect()->route('peguam-panel.show', $peguam)->with('status', 'Rekod peguam panel dikemaskini.');
    }

    /** Deactivate a panel lawyer with justification → triggers death-redistribution of active cases. */
    public function nyahaktif(Request $request, PeguamPanel $peguam, PeguamLifecycleService $svc): RedirectResponse
    {
        $data = $request->validate([
            'sebab' => ['required', Rule::in(PeguamPanel::SEBAB_LIST)],
            'sebabLain' => ['nullable', 'string', 'max:200', 'required_if:sebab,'.PeguamPanel::SEBAB_LAIN],
        ]);

        $sebab = $data['sebab'] === PeguamPanel::SEBAB_LAIN
            ? PeguamPanel::SEBAB_LAIN.': '.$data['sebabLain']
            : $data['sebab'];

        $n = $svc->deactivate($peguam, $request->user(), $sebab);

        return redirect()->route('peguam-panel.show', $peguam)
            ->with('status', "Peguam dinyahaktifkan. {$n} kes aktif telah dikembalikan untuk agihan semula.");
    }

    /** Reactivate a deactivated panel lawyer. */
    public function aktifSemula(Request $request, PeguamPanel $peguam, PeguamLifecycleService $svc): RedirectResponse
    {
        $svc->reactivate($peguam, $request->user());

        return redirect()->route('peguam-panel.show', $peguam)->with('status', 'Peguam diaktifkan semula.');
    }
}
