<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Support\Audit;
use App\Support\NoFailGenerator;
use App\Support\PerakuanService;
use App\Support\StatusAgihan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * W9 - Pembelaan Awam (public criminal defence) register.
 *
 * Per decision D3, pembelaan cases are tagged `forms` rows (is_pembelaan_awam = 1), not a
 * separate table. This controller owns intake + the criminal-defence register; assignment,
 * lifecycle and closure flow through the shared 3-tier spine (AgihanSpineController) and
 * KeputusanController, exactly like a litigation case. Civil lists are kept clean via the
 * Form::litigasi() scope; this register uses Form::pembelaan().
 */
class PembelaanAwamController extends Controller
{
    /** Branch-scoped pembelaan register with status / search filters. */
    public function index(Request $request): View
    {
        $filters = $request->only(['cawangan', 'status', 'q']);

        $kes = Form::query()
            ->pembelaan()
            ->when($filters['cawangan'] ?? null, fn ($w, $v) => $w->where('cawangan', $v))
            ->when($filters['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
            ->when($filters['q'] ?? null, function ($w, $v) {
                $w->where(function ($s) use ($v) {
                    $s->where('nama', 'like', "%{$v}%")
                        ->orWhere('nokp', 'like', "%{$v}%")
                        ->orWhere('no_fail', 'like', "%{$v}%")
                        ->orWhere('no_pertuduhan', 'like', "%{$v}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('pembelaan-awam.index', [
            'kes' => $kes,
            'filters' => $filters,
            'cawanganList' => $this->cawanganList(),
        ]);
    }

    public function create(): View
    {
        return view('pembelaan-awam.form', [
            'cawanganList' => $this->cawanganList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'nokp' => ['nullable', 'string', 'max:20'],
            'cawangan' => ['required', 'string', 'max:50'],
            'jenis_kes' => ['nullable', 'string', 'max:5'],
            'kategori_kes' => ['nullable', 'string', 'max:100'],
            'jenis_pemohonan_pembelaan' => ['nullable', 'string', 'max:80'],
            'no_pertuduhan' => ['nullable', 'string', 'max:100'],
            'seksyen_kesalahan' => ['nullable', 'string', 'max:150'],
            'mahkamah_pembelaan' => ['nullable', 'string', 'max:150'],
            'tarikh_pertuduhan' => ['nullable', 'date'],
            'tarikh_permohonan' => ['nullable', 'date'],
            'is_segera' => ['nullable', 'boolean'],
        ]);

        $data['is_pembelaan_awam'] = true;
        $data['is_segera'] = (bool) ($data['is_segera'] ?? false);
        $data['created_at'] = now();
        $data['tarikh_permohonan'] = $data['tarikh_permohonan'] ?? now()->toDateString();
        $data['tarikh_daftar'] = now()->toDateString();
        $data['didaftarkan_oleh'] = $request->user()->name;
        $data['diterima'] = ''; // NOT NULL in legacy schema

        // PROC-02: create + file-number assignment + audit are atomic - a failure after create
        // must not leave a registered case with a blank no_fail (an orphan with no file number).
        $kes = DB::transaction(function () use ($data) {
            $kes = Form::create($data);

            // Distinct PBA file-number series for criminal-defence files.
            $kes->update(['no_fail' => app(NoFailGenerator::class)->generatePembelaan($kes)]);

            Audit::log('forms', $kes->id, Audit::INSERT, "Pembelaan Awam didaftarkan: {$kes->nama} ({$kes->no_fail})");

            return $kes;
        });

        return redirect()->route('pembelaan.show', $kes)
            ->with('status', 'Permohonan Pembelaan Awam direkodkan. No. Fail: '.$kes->no_fail);
    }

    public function show(Form $kes): View
    {
        abort_unless((bool) $kes->is_pembelaan_awam, 404);

        $kes->load(['sejarahPeguamPanel', 'lampiran']);

        return view('pembelaan-awam.show', [
            'kes' => $kes,
            'statusAgihan' => StatusAgihan::label($kes->status_agihan),
        ]);
    }

    /** W14 - issue an interim legal-aid certificate (SEGERA cases; override via perm). */
    public function keluarInterim(Request $request, Form $kes, PerakuanService $svc): RedirectResponse
    {
        abort_unless((bool) $kes->is_pembelaan_awam, 404);

        // A manager may override the SEGERA requirement.
        $override = $request->user()->can('pembelaan.manage') && $request->boolean('override');
        $svc->keluarInterim($kes, $request->user(), $override);

        return back()->with('status', 'Perakuan interim dikeluarkan. No. Perakuan: '.$kes->fresh()->no_perakuan);
    }

    /** W14 - finalise an interim certificate to muktamad. */
    public function muktamad(Request $request, Form $kes, PerakuanService $svc): RedirectResponse
    {
        abort_unless((bool) $kes->is_pembelaan_awam, 404);

        $svc->muktamadkan($kes, $request->user());

        return back()->with('status', 'Perakuan dimuktamadkan.');
    }

    /** Distinct cawangan names already present on forms (mirrors KesController). */
    private function cawanganList()
    {
        return Form::query()
            ->whereNotNull('cawangan')->where('cawangan', '!=', '')
            ->distinct()->orderBy('cawangan')->pluck('cawangan');
    }
}
