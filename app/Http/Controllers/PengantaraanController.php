<?php

namespace App\Http\Controllers;

use App\Http\Requests\PengantaraanRequest;
use App\Models\Form;
use App\Models\SejarahSidang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Pengantaraan (mediation) lifecycle actions on a case: assignment, hearing, outcome,
// plus hearing reschedule history (sejarah_sidang).
class PengantaraanController extends Controller
{
    public function edit(Form $kes): View
    {
        $kes->load('sejarahSidang');

        return view('kes.pengantaraan', ['kes' => $kes]);
    }

    public function update(PengantaraanRequest $request, Form $kes): RedirectResponse
    {
        $kes->update($request->validated() + ['tarikh_KPKemaskini' => now()]);

        return redirect()->route('kes.show', $kes)->with('status', 'Maklumat pengantaraan dikemaskini.');
    }

    /** Record a hearing postponement: log to sejarah_sidang + move the case hearing date. */
    public function tangguhSidang(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'tarikh_sidang' => ['required', 'date'],
            'alasan_tangguh' => ['nullable', 'string', 'max:50'],
        ]);

        SejarahSidang::create([
            'id_kes' => $kes->id,
            'tarikh_sidang' => $data['tarikh_sidang'],
            'alasan_tangguh' => $data['alasan_tangguh'] ?? null,
            'dikemaskini_oleh' => $request->user()->name,
        ]);

        $kes->update([
            'tarikh_sidang' => $data['tarikh_sidang'],
            'status_sidang' => 'Tangguh',
        ]);

        return redirect()->route('pengantaraan.edit', $kes)->with('status', 'Sidang ditangguh dan direkodkan.');
    }
}
