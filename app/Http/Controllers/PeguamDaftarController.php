<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeguamDaftarRequest;
use App\Models\ButiranPeguamPanel2;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

// Public lawyer panel application. Creates a butiran_peguam_panel_2 row (permohonan_status='0')
// that staff endorse + decide in PermohonanPeguamController. No login required to apply.
class PeguamDaftarController extends Controller
{
    public function create(): View
    {
        return view('peguam.daftar');
    }

    public function store(PeguamDaftarRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['website']); // honeypot, never persisted

        $permohonan = ButiranPeguamPanel2::create($data + [
            'permohonan_status' => '0',
            'tarikhMohon' => now(),
            // NOT NULL columns in legacy schema with no default — coerce blanks.
            'tahunPengalamanSyarie' => $data['tahunPengalamanSyarie'] ?? '0',
        ]);

        return redirect()
            ->route('peguam.daftar')
            ->with('daftar_selesai', true)
            ->with('daftar_ref', $permohonan->id);
    }
}
