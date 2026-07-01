<?php

namespace App\Http\Controllers;

use App\Http\Requests\OydRequest;
use App\Models\Oyd;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// OYD (Orang Yang Dibantu) registry - beneficiary master records (butiran_oyd).
class OydController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->input('q');

        $oyd = Oyd::query()
            ->when($q, function ($w, $v) {
                $w->where(fn ($s) => $s->where('nama_oyd', 'like', "%{$v}%")
                    ->orWhere('kp_oyd', 'like', "%{$v}%")
                    ->orWhere('notelefon_oyd', 'like', "%{$v}%"));
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('oyd.index', ['oyd' => $oyd, 'q' => $q]);
    }

    public function show(Oyd $oyd): View
    {
        return view('oyd.show', ['oyd' => $oyd]);
    }

    public function create(): View
    {
        return view('oyd.form', ['oyd' => new Oyd, 'mode' => 'create']);
    }

    public function store(OydRequest $request): RedirectResponse
    {
        $oyd = Oyd::create($request->validated() + [
            'createdBy_oyd' => $request->user()->name,
            'createdDate_oyd' => now(),
        ]);

        Audit::log('butiran_oyd', $oyd->id, Audit::INSERT, "Rekod OYD baharu: {$oyd->nama_oyd} ({$oyd->kp_oyd})");

        return redirect()->route('oyd.show', $oyd)->with('status', 'Rekod OYD ditambah.');
    }

    public function edit(Oyd $oyd): View
    {
        return view('oyd.form', ['oyd' => $oyd, 'mode' => 'edit']);
    }

    public function update(OydRequest $request, Oyd $oyd): RedirectResponse
    {
        $oyd->update($request->validated() + [
            'modifiedBy_oyd' => $request->user()->name,
            'modifiedDate_oyd' => now(),
        ]);

        Audit::log('butiran_oyd', $oyd->id, Audit::UPDATE, "Kemaskini rekod OYD: {$oyd->nama_oyd} ({$oyd->kp_oyd})");

        return redirect()->route('oyd.show', $oyd)->with('status', 'Rekod OYD dikemaskini.');
    }
}
