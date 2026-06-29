<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
