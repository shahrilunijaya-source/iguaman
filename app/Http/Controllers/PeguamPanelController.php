<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
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
}
