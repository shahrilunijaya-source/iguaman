<?php

namespace App\Http\Controllers;

use App\Models\ButiranPeguamPanel2;
use App\Models\ButiranPeguamPanel6;
use App\Support\PengkhususanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff review of lawyer Bidang Pengkhususan add/drop requests (legacy senarai-kemaskini-kes
 * + maklumat-kemaskini-kes). Pengarah recommends, Ketua Pengarah finalises. All transitions
 * run through PengkhususanService.
 */
class KemaskiniBidangController extends Controller
{
    /** Pending add/drop requests, with the requesting lawyer's name. */
    public function index(): View
    {
        $rows = ButiranPeguamPanel6::query()
            ->whereIn('checkbox_value_status', array_merge(ButiranPeguamPanel6::PENGARAH_PENDING, ButiranPeguamPanel6::KP_PENDING))
            ->orderByDesc('id')
            ->paginate(30);

        // Resolve lawyer names for the listed ICs.
        $names = ButiranPeguamPanel2::whereIn('kpBaru', $rows->pluck('kpBaru')->unique())->pluck('namaPeguam', 'kpBaru');

        return view('kemaskini-bidang.index', ['rows' => $rows, 'names' => $names]);
    }

    /** Pengarah recommends (3→7 / 4→9) or rejects a request. */
    public function pengarah(Request $request, ButiranPeguamPanel6 $row, PengkhususanService $svc): RedirectResponse
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:sokong,tolak'],
            'ulasan' => ['nullable', 'string', 'max:500'],
        ]);

        $svc->pengarahReview($row, $data['keputusan'] === 'sokong', $data['ulasan'] ?? null, $request->user());

        return back()->with('status', 'Sokongan Pengarah direkodkan.');
    }

    /** Ketua Pengarah finalises (7→delete / 9→active) or rejects. */
    public function kp(Request $request, ButiranPeguamPanel6 $row, PengkhususanService $svc): RedirectResponse
    {
        $data = $request->validate(['keputusan' => ['required', 'in:lulus,tolak']]);

        $svc->kpDecide($row, $data['keputusan'] === 'lulus', $request->user());

        return back()->with('status', 'Keputusan Ketua Pengarah direkodkan.');
    }
}
