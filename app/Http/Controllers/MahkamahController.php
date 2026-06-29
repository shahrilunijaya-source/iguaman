<?php

namespace App\Http\Controllers;

use App\Http\Requests\MahkamahRequest;
use App\Models\Form;
use App\Models\LaporanKes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Kes Mahkamah (court) lifecycle actions on a case: filing, outcome, costs,
// plus court case reports (laporan_kes, child of forms via id_kes).
class MahkamahController extends Controller
{
    public function edit(Form $kes): View
    {
        $kes->load('laporanKes');

        return view('kes.mahkamah', ['kes' => $kes]);
    }

    public function update(MahkamahRequest $request, Form $kes): RedirectResponse
    {
        $kes->update($request->validated() + ['tarikh_KPKemaskini' => now()]);

        return redirect()->route('kes.show', $kes)->with('status', 'Maklumat mahkamah dikemaskini.');
    }

    public function storeLaporan(Request $request, Form $kes): RedirectResponse
    {
        $data = $request->validate([
            'no_kes' => ['nullable', 'string', 'max:30'],
            'pihak_pihak' => ['nullable', 'string', 'max:255'],
            'nama_pegawai' => ['nullable', 'string', 'max:255'],
            'tarikh_sebutan' => ['nullable', 'date'],
            'isu' => ['nullable', 'string', 'max:255'],
            'status_kes' => ['nullable', 'string', 'max:100'],
            'fakta_ringkas' => ['nullable', 'string'],
            'ringkasan' => ['nullable', 'string'],
        ]);

        LaporanKes::create($data + [
            'id_kes' => (string) $kes->id,
            'no_fail' => $kes->no_fail,
        ]);

        return redirect()->route('mahkamah.edit', $kes)->with('status', 'Laporan kes ditambah.');
    }

    public function destroyLaporan(Form $kes, LaporanKes $laporan): RedirectResponse
    {
        abort_unless((string) $laporan->id_kes === (string) $kes->id, 404);

        $laporan->delete();

        return redirect()->route('mahkamah.edit', $kes)->with('status', 'Laporan kes dipadam.');
    }
}
