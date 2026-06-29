<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Kes (Case) backbone over the `forms` spine — list/filter/search + detail.
// Foundation for the rekod-kes domain (permohonan/pengantaraan/mahkamah build on this).
class KesController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['cawangan', 'status', 'kategori', 'q']);

        $kes = Form::query()
            ->when($filters['cawangan'] ?? null, fn ($w, $v) => $w->where('cawangan', $v))
            ->when($filters['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
            ->when($filters['kategori'] ?? null, fn ($w, $v) => $w->where('kategori_kes', $v))
            ->when($filters['q'] ?? null, function ($w, $v) {
                $w->where(function ($s) use ($v) {
                    $s->where('nama', 'like', "%{$v}%")
                        ->orWhere('nokp', 'like', "%{$v}%")
                        ->orWhere('no_fail', 'like', "%{$v}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('kes.index', [
            'kes' => $kes,
            'filters' => $filters,
            'cawanganList' => Form::query()->whereNotNull('cawangan')->where('cawangan', '!=', '')->distinct()->orderBy('cawangan')->pluck('cawangan'),
            'statusList' => Form::query()->whereNotNull('status')->where('status', '!=', '')->distinct()->orderBy('status')->pluck('status'),
            'kategoriList' => Form::query()->whereNotNull('kategori_kes')->where('kategori_kes', '!=', '')->distinct()->orderBy('kategori_kes')->pluck('kategori_kes'),
        ]);
    }

    public function show(Form $kes): View
    {
        $kes->load(['laporanKes', 'sejarahPegawai', 'sejarahPeguamPanel', 'sejarahSidang']);

        return view('kes.show', ['kes' => $kes]);
    }
}
